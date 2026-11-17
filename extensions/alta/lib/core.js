export const ALTA = 'https://alta.ge';
export const MAX_PHOTOS = 8;
export const MAX_CANDIDATES = 5;

const CODE = /^[A-Za-z0-9_-]{1,64}$/;

export function validCode(value) {
    const code = String(value ?? '').trim();

    return CODE.test(code) ? code : null;
}

export function suggestionsUrl(code) {
    return `${ALTA}/api/proxy/v1/Products/search/suggestions?query=${encodeURIComponent(code)}`;
}

export function detailsUrl(id) {
    return `${ALTA}/api/proxy/v1/Products/details?productId=${Number(id)}`;
}

function positiveId(value) {
    const number = Number(value);

    return Number.isSafeInteger(number) && number > 0 ? number : null;
}

function barcodeOf(product) {
    return product?.barCode === undefined || product?.barCode === null ? '' : String(product.barCode).trim();
}

export function candidates(suggestions, code) {
    const products = Array.isArray(suggestions) ? suggestions : suggestions?.products;

    if (!Array.isArray(products)) {
        throw new Error('invalid_answer');
    }

    const exact = [];
    const unknown = [];

    for (const product of products) {
        const id = positiveId(product?.id);

        if (id === null) {
            continue;
        }

        const barcode = barcodeOf(product);

        if (barcode === code) {
            exact.push(id);
        } else if (barcode === '') {
            unknown.push(id);
        }
    }

    return [...new Set([...exact, ...unknown])].slice(0, MAX_CANDIDATES);
}

export function matches(details, code) {
    return positiveId(details?.product?.id) !== null && barcodeOf(details.product) === code;
}

export function buildRecord(details, quantity) {
    const product = details?.product;
    const price = Number(product?.price);

    if (typeof product?.name !== 'string' || product.name.trim() === '') {
        throw new Error('no_name');
    }

    if (!Number.isFinite(price) || price <= 0) {
        throw new Error('no_price');
    }

    const record = structuredClone(details);
    record.product.storageQuantity = Math.max(0, Math.trunc(Number(quantity) || 0));
    record.availabilityInStores = [];

    return record;
}

function photoUrlAllowed(value) {
    let url;

    try {
        url = new URL(value);
    } catch {
        return false;
    }

    let path;

    try {
        path = decodeURIComponent(url.pathname);
    } catch {
        return false;
    }

    return url.protocol === 'https:'
        && ['imgstore.alta.ge', 'alta.ge'].includes(url.hostname)
        && !url.username && !url.password && !url.port
        && !value.includes('?') && !value.includes('#')
        && url.pathname.startsWith('/images/')
        && !path.includes('\\')
        && !/(?:^|\/)\.{1,2}(?:\/|$)/.test(path)
        && !/[\x00-\x20\x7f]/.test(path)
        && /\.(?:jpe?g|png|webp|gif)$/i.test(url.pathname);
}

export function photoUrls(product) {
    const seen = [];

    for (const raw of [product?.imageUrl, ...(Array.isArray(product?.images) ? product.images : [])]) {
        const url = String(raw ?? '').trim();

        if (url.startsWith('http') && !seen.includes(url)) {
            seen.push(url);
        }
    }

    const urls = seen.slice(0, MAX_PHOTOS);

    return {urls, usable: urls.length > 0 && urls.every(photoUrlAllowed)};
}

export function parseFeed(data) {
    if (!data || typeof data !== 'object' || !Array.isArray(data.items)) {
        throw new Error('invalid_feed');
    }

    const items = [];
    const seen = new Set();

    for (const row of data.items) {
        const code = validCode(row?.item);
        const quantity = Number(row?.quantity);

        if (code === null || !Number.isSafeInteger(quantity) || quantity < 0 || seen.has(code)) {
            continue;
        }

        seen.add(code);
        items.push({code, quantity, onSite: row.on_site === true});
    }

    return {
        items,
        syncedAt: typeof data.synced_at === 'string' ? data.synced_at : null,
        minimum: Number(data.minimum_quantity) || null,
    };
}

export function pick(items, mode, limit) {
    const chosen = items.filter(item => mode === 'all' || (mode === 'new' ? !item.onSite : item.onSite));

    return limit > 0 ? chosen.slice(0, limit) : chosen;
}
