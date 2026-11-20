import {ZOOMMER} from './core.js';

const TAB_KEY = 'zoommerTab';

export class Blocked extends Error {
    constructor(code) {
        super(code);
        this.code = code;
    }
}

const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

function pageFetch(url) {
    return fetch(url, {
        credentials: 'include',
        headers: {'Accept': 'application/json, text/plain, */*', 'Accept-Language': 'ka', 'os': 'web'},
    }).then(async response => ({
        status: response.status,
        type: response.headers.get('content-type') || '',
        text: await response.text(),
    })).catch(error => ({status: 0, type: '', text: String(error)}));
}

export function createZoommerReader() {
    let last = 0;

    async function waitLoaded(tabId) {
        for (let i = 0; i < 120; i++) {
            const tab = await chrome.tabs.get(tabId);

            if (tab.status === 'complete') {
                return tab;
            }

            await sleep(250);
        }

        throw new Blocked('zoommer_tab_timeout');
    }

    async function tab() {
        const {[TAB_KEY]: saved} = await chrome.storage.session.get(TAB_KEY);

        if (Number.isInteger(saved)) {
            try {
                const current = await chrome.tabs.get(saved);

                if (current.url && new URL(current.url).origin === ZOOMMER && !current.discarded) {
                    return current.status === 'complete' ? current : waitLoaded(current.id);
                }
            } catch {
            }
        }

        const created = await chrome.tabs.create({url: `${ZOOMMER}/`, active: false});
        await chrome.tabs.update(created.id, {autoDiscardable: false});
        await chrome.storage.session.set({[TAB_KEY]: created.id});

        return waitLoaded(created.id);
    }

    async function getJson(url, interval) {
        const wait = last + interval - Date.now();

        if (wait > 0) {
            await sleep(wait);
        }

        last = Date.now();

        const target = await tab();
        let result;

        try {
            [{result}] = await chrome.scripting.executeScript({target: {tabId: target.id}, func: pageFetch, args: [url]});
        } catch {
            throw new Blocked('zoommer_tab_unavailable');
        }

        if (result.status === 429) {
            throw new Blocked('zoommer_rate_limited');
        }

        if (result.status === 401) {
            throw new Blocked('zoommer_signed_out');
        }

        if ([403, 503].includes(result.status) || /text\/html/i.test(result.type)) {
            throw new Blocked('zoommer_check');
        }

        if (result.status === 0) {
            throw new Error('zoommer_offline');
        }

        if (result.status >= 400 && result.status < 500) {
            return null;
        }

        if (result.status < 200 || result.status >= 300) {
            throw new Error(`zoommer_http_${result.status}`);
        }

        if (result.text.trim() === '') {
            return null;
        }

        try {
            return JSON.parse(result.text);
        } catch {
            throw new Blocked('zoommer_check');
        }
    }

    async function show() {
        const target = await tab();
        await chrome.tabs.update(target.id, {active: true});
        await chrome.windows.update(target.windowId, {focused: true});
    }

    return {getJson, show};
}
