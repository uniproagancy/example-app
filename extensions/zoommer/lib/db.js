const NAME = 'elio-zoommer';
const VERSION = 1;

let opening = null;

function open() {
    opening ??= new Promise((resolve, reject) => {
        const request = indexedDB.open(NAME, VERSION);

        request.onupgradeneeded = () => {
            const db = request.result;
            db.createObjectStore('records', {keyPath: 'id'});
            db.createObjectStore('sizes', {keyPath: 'id'});
            db.createObjectStore('meta');
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    return opening;
}

function done(request) {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function finished(tx) {
    return new Promise((resolve, reject) => {
        tx.oncomplete = resolve;
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error);
    });
}

async function store(name, mode) {
    return (await open()).transaction(name, mode).objectStore(name);
}

export async function getMeta(key) {
    return done((await store('meta', 'readonly')).get(key));
}

export async function setMeta(key, value) {
    return done((await store('meta', 'readwrite')).put(value, key));
}

export async function getRecord(id) {
    return (await done((await store('records', 'readonly')).get(id)))?.record ?? null;
}

export async function putRecord(id, record) {
    const text = JSON.stringify(record);
    const tx = (await open()).transaction(['records', 'sizes'], 'readwrite');
    tx.objectStore('records').put({id, record});
    tx.objectStore('sizes').put({id, size: new Blob([text]).size});

    await finished(tx);
}

export async function sizes() {
    return done((await store('sizes', 'readonly')).getAll());
}

export async function clearRecords() {
    const tx = (await open()).transaction(['records', 'sizes'], 'readwrite');
    tx.objectStore('records').clear();
    tx.objectStore('sizes').clear();

    await finished(tx);
}
