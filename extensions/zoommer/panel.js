import {detailsUrl, examine, photoUrls} from './lib/core.js';
import {clearRecords, getMeta, getRecord, putRecord, setMeta, sizes} from './lib/db.js';
import {Blocked, createZoommerReader} from './lib/zoommer.js';
import {collectPhotos} from './lib/photos.js';
import {T} from './lib/strings.js';

const $ = id => document.getElementById(id);
const reader = createZoommerReader();
const MAX_RANGE = 200_000;
const PRODUCT_FAULTS = ['no_name', 'no_price'];

let running = false;
let stopping = false;

function number(id, fallback) {
    const value = Number($(id).value);

    return Number.isFinite(value) ? value : fallback;
}

async function collect(id, job) {
    const answer = await reader.getJson(detailsUrl(id), job.interval);
    const seen = examine(answer, job);

    if (seen.status !== 'keep') {
        return seen;
    }

    const record = structuredClone(answer);
    let warning = null;

    if (job.photos) {
        const {urls, usable} = photoUrls(record.product);

        if (!urls.length) {
            warning = 'photos_none';
        } else if (!usable) {
            warning = 'photos_links';
        } else {
            try {
                record.iapiAssets = await collectPhotos(urls);
            } catch (error) {
                warning = error.message === 'photos_too_large' ? 'photos_too_large' : 'photos_failed';
            }
        }
    }

    return {status: 'done', id: seen.id, record, warning};
}

async function collectPatiently(id, job) {
    for (let attempt = 0; ; attempt++) {
        try {
            return await collect(id, job);
        } catch (error) {
            if (!(error instanceof Blocked) || error.code !== 'zoommer_rate_limited' || attempt >= 2) {
                throw error;
            }

            await countdown(90);
        }
    }
}

function note(job, id, why) {
    job.problems.push({id, why});
    job.problems = job.problems.slice(-300);
}

function nextId(job) {
    if (job.retry.length) {
        return job.retry[0];
    }

    return job.next <= job.to ? job.next : null;
}

function advance(job, id) {
    if (job.retry.length && job.retry[0] === id) {
        job.retry.shift();
    } else {
        job.next = id + 1;
    }
}

async function run() {
    if (running) {
        return;
    }

    running = true;
    stopping = false;
    alert(null);

    const job = await getMeta('job');
    job.state = 'running';
    await setMeta('job', job);
    await render();

    let failures = 0;

    try {
        while (!stopping) {
            if (job.limit > 0 && job.found >= job.limit) {
                job.retry = [];
                job.next = job.to + 1;
                job.stoppedByLimit = true;
                break;
            }

            const id = nextId(job);

            if (id === null) {
                break;
            }

            $('state').textContent = T.state_running(id, job.to);
            let result;

            try {
                result = await collectPatiently(id, job);
                failures = 0;
            } catch (error) {
                if (error instanceof Blocked) {
                    stopping = true;
                    alert(T[error.code]);

                    if (error.code === 'zoommer_check' || error.code === 'zoommer_signed_out') {
                        reader.show().catch(() => {});
                    }

                    break;
                }

                result = {status: 'error', error: error?.message || 'error'};

                if (++failures >= 5) {
                    stopping = true;
                    alert(T.too_many_errors);
                }
            }

            if (result.status === 'done') {
                const known = (await getRecord(result.id)) !== null;
                await putRecord(result.id, result.record);
                job.found += known ? 0 : 1;

                if (result.warning) {
                    note(job, result.id, result.warning);
                }
            } else if (result.status === 'cheap') {
                job.cheap++;
            } else if (result.status === 'out') {
                job.out++;
            } else if (result.status === 'missing') {
                job.missing++;
            } else if (PRODUCT_FAULTS.includes(result.error)) {
                job.failed++;
                note(job, id, result.error);
            } else {
                if (!job.errors.includes(id)) {
                    job.errors.push(id);
                }

                note(job, id, result.error);
            }

            if (result.status !== 'error' || PRODUCT_FAULTS.includes(result.error)) {
                job.errors = job.errors.filter(value => value !== id);
            }

            advance(job, id);
            await setMeta('job', job);
            renderCounts(job);
        }
    } finally {
        running = false;
    }

    job.state = nextId(job) === null ? 'done' : 'paused';
    await setMeta('job', job);
    await render();
}

async function countdown(seconds) {
    for (let left = seconds; left > 0 && !stopping; left--) {
        $('state').textContent = T.state_waiting(left);
        await new Promise(resolve => setTimeout(resolve, 1000));
    }
}

const UPLOAD_MAX = 5 * 1024 * 1024 * 1024;

async function pendingDownloads(job) {
    const collected = (await sizes()).length;

    return Math.max(0, collected - (job?.downloadedCount ?? 0));
}

$('start').addEventListener('click', async () => {
    const from = Math.trunc(number('from', 0));
    const to = Math.trunc(number('to', 0));

    if (from < 1 || to < 1 || from > to) {
        alert(T.bad_range);

        return;
    }

    if (to - from + 1 > MAX_RANGE) {
        alert(T.too_wide(MAX_RANGE));

        return;
    }

    const previous = await getMeta('job');
    const waiting = previous ? await pendingDownloads(previous) : 0;

    if (waiting > 0 && !confirm(T.confirm_new(waiting))) {
        return;
    }

    await clearRecords();
    await setMeta('job', {
        createdAt: new Date().toISOString(),
        from,
        to,
        next: from,
        minPrice: Math.max(0, number('min-price', 0)),
        limit: Math.max(0, Math.trunc(number('limit', 0))),
        interval: Math.max(400, Math.trunc(number('interval', 1000))),
        inStockOnly: $('in-stock').checked,
        photos: $('photos').checked,
        retry: [],
        errors: [],
        problems: [],
        found: 0,
        cheap: 0,
        out: 0,
        missing: 0,
        failed: 0,
        stoppedByLimit: false,
        downloadedCount: 0,
        state: 'paused',
    });

    run();
});

$('pause').addEventListener('click', () => {
    stopping = true;
    $('pause').disabled = true;
});

$('resume').addEventListener('click', () => run());

$('retry').addEventListener('click', async () => {
    const job = await getMeta('job');
    job.retry = [...new Set([...job.retry, ...job.errors])];
    job.errors = [];
    await setMeta('job', job);

    run();
});

function stamp(iso) {
    const date = new Date(iso);
    const pad = value => String(value).padStart(2, '0');

    return `${date.getFullYear()}${pad(date.getMonth() + 1)}${pad(date.getDate())}-${pad(date.getHours())}${pad(date.getMinutes())}`;
}

async function write(ids, put, button) {
    await put('[');

    for (let i = 0; i < ids.length; i++) {
        const record = await getRecord(ids[i]);
        await put((i ? ',' : '') + JSON.stringify(record));

        if (i % 20 === 0) {
            button.textContent = T.writing(i + 1, ids.length);
        }
    }

    await put(']');
}

async function download(button) {
    const job = await getMeta('job');
    const ids = (await sizes()).map(row => row.id);
    const name = `zoommer-${stamp(job.createdAt)}-${job.from}-${job.to}.json`;

    button.disabled = true;

    try {
        if (window.showSaveFilePicker) {
            let handle;

            try {
                handle = await window.showSaveFilePicker({suggestedName: name, types: [{description: 'JSON', accept: {'application/json': ['.json']}}]});
            } catch {
                return;
            }

            const file = await handle.createWritable();

            try {
                await write(ids, text => file.write(text), button);
                await file.close();
            } catch (error) {
                await file.abort().catch(() => {});
                throw error;
            }
        } else {
            const pieces = [];
            await write(ids, text => pieces.push(text), button);
            const url = URL.createObjectURL(new Blob(pieces, {type: 'application/json'}));
            const link = document.createElement('a');
            link.href = url;
            link.download = name;
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 60_000);
        }

        job.downloadedCount = ids.length;
        await setMeta('job', job);
    } catch {
        alert(T.download_failed);
    } finally {
        await renderDownloads();
    }
}

function alert(message) {
    $('alert').hidden = !message;
    $('alert').textContent = message || '';
}

function renderCounts(job) {
    const total = job ? job.to - job.from + 1 : 1;
    const left = job ? Math.max(0, job.to - job.next + 1) + job.retry.length : 0;

    $('progress').max = Math.max(1, total);
    $('progress').value = job ? Math.min(total, total - left) : 0;
    $('counts').innerHTML = '';

    if (!job) {
        return;
    }

    for (const [label, value] of [
        [T.count_done, job.found],
        [T.count_cheap, job.cheap],
        [T.count_out, job.out],
        [T.count_missing, job.missing],
        [T.count_error, job.errors.length + job.failed],
        [T.count_left, job.stoppedByLimit ? 0 : left],
    ]) {
        const li = document.createElement('li');
        const b = document.createElement('b');
        b.textContent = value;
        li.append(b, label);
        $('counts').append(li);
    }

    $('problem-count').textContent = job.problems.length ? `(${job.problems.length})` : '';
    $('problems').innerHTML = '';

    for (const row of job.problems) {
        const li = document.createElement('li');
        const code = document.createElement('code');
        code.textContent = `ID ${row.id}`;
        li.append(code, ' — ', T.problem[row.why] || row.why);
        $('problems').append(li);
    }

    $('retry').hidden = running || !job.errors.length;
}

async function renderDownloads() {
    const job = await getMeta('job');
    const rows = await sizes();
    const bytes = rows.reduce((sum, row) => sum + row.size, 0);
    const done = rows.length > 0 && (job?.downloadedCount ?? 0) >= rows.length;

    $('downloads').innerHTML = '';
    $('size').textContent = rows.length ? T.size(bytes) : T.parts_none;
    $('parts-hint').hidden = bytes <= UPLOAD_MAX;
    $('parts-hint').textContent = T.too_big;
    $('download-info').textContent = running && rows.length ? T.download_after_pause : '';

    if (!rows.length) {
        return;
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.disabled = running;
    button.textContent = T.download(rows.length, bytes) + (done ? ` ${T.downloaded}` : '');

    if (done) {
        button.className = 'secondary';
    }

    button.addEventListener('click', () => download(button));
    $('downloads').append(button);
}

async function render() {
    const job = await getMeta('job');
    const left = job ? nextId(job) !== null : false;

    $('start').disabled = running;
    $('options').disabled = running;
    $('pause').hidden = !running;
    $('pause').disabled = false;
    $('resume').hidden = running || !left;
    $('job').textContent = job ? T.job_info(job) : '';

    if (!running) {
        $('state').textContent = !job ? T.state_idle : (left ? T.state_paused : (job.stoppedByLimit ? T.state_limit : T.state_done));
    }

    renderCounts(job);
    await renderDownloads();
}

(async () => {
    const job = await getMeta('job');

    if (job) {
        $('from').value = job.from;
        $('to').value = job.to;
        $('min-price').value = job.minPrice;
        $('limit').value = job.limit;
        $('interval').value = job.interval;
        $('in-stock').checked = job.inStockOnly;
        $('photos').checked = job.photos;

        if (job.state === 'running') {
            job.state = 'paused';
            await setMeta('job', job);
        }
    }

    await render();
})();
