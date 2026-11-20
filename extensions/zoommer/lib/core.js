export const ZOOMMER = 'https://zoommer.ge';
export const MAX_PHOTOS = 8;
export const PART_MAX = 90_000_000;

export function detailsUrl(id) {
    return `${ZOOMMER}/api/proxy/v1/Products/details?productId=${Number(id)}`;
}

export function positiveId(value) {
    const number = Number(value);

    return Number.isSafeInteger(number) && number > 0 ? number : null;
}

function amount(value) {
    const number = Number(value);

    return Number.isFinite(number) && number > 0 ? number : 0;
}

export function priceOf(product) {
    const current = amount(product?.price);
    const previous = amount(product?.previousPrice);

    return current && previous ? Math.min(current, previous) : (current || previous);
}

export function examine(answer, job) {
    const product = answer?.product;
    const id = positiveId(product?.id);

    if (!product || id === null) {
        return {status: 'missing'};
    }

    if (typeof product.name !== 'string' || product.name.trim() === '') {
        return {status: 'error', error: 'no_name', id};
    }

    const price = priceOf(product);

    if (!price) {
        return {status: 'error', error: 'no_price', id};
    }

    if (price < job.minPrice) {
        return {status: 'cheap', id};
    }

    if (job.inStockOnly && !product.isInStock) {
        return {status: 'out', id};
    }

    return {status: 'keep', id, price};
}

function photoUrlAllowed(value) {
    let url;

    try {
        url = new URL(value);
    } catch {
        return false;
    }

    return url.protocol === 'https:'
        && (url.hostname === 'zoommer.ge' || url.hostname.endsWith('.zoommer.ge'))
        && !url.username && !url.password && !url.port;
}

export function photoUrls(product) {
    const seen = [];

    for (const raw of [product?.imageUrl, ...(Array.isArray(product?.images) ? product.images : [])]) {
        const url = String(raw ?? '').trim();

        if (url !== '' && !seen.includes(url)) {
            seen.push(url);
        }
    }

    const urls = seen.slice(0, MAX_PHOTOS);

    return {urls, usable: urls.length > 0 && urls.every(photoUrlAllowed)};
}

export function parts(sizes) {
    const out = [];
    let current = null;

    for (const {id, size} of sizes) {
        if (!current || (current.bytes + size > PART_MAX && current.ids.length)) {
            current = {ids: [], bytes: 0};
            out.push(current);
        }

        current.ids.push(id);
        current.bytes += size;
    }

    return out;
}
