import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
window.elioImages = import.meta.glob('../images/**', { eager: true, query: '?url', import: 'default' });

const readFavorites = () => {
    try {
        return JSON.parse(localStorage.getItem('elio.favorites') || '{}');
    } catch {
        return {};
    }
};

const writeFavorites = (favorites) => {
    try {
        localStorage.setItem('elio.favorites', JSON.stringify(favorites));
    } catch {}
};

Alpine.store('ui', {
    catalog: false,
    cart: false,
    auth: false,
    closeAll() {
        this.catalog = false;
        this.cart = false;
        this.auth = false;
    },
});

Alpine.store('toast', {
    message: '',
    withCart: false,
    timer: null,
    show(message, withCart = false, duration = 2600) {
        this.message = message;
        this.withCart = withCart;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => (this.message = ''), duration);
    },
});

const toFavoriteMap = (ids) => Object.fromEntries(ids.map((id) => [id, true]));

const serverFavorites = Array.isArray(window.elioFavorites) ? window.elioFavorites : null;

Alpine.store('favorites', {
    signedIn: serverFavorites !== null,
    ids: serverFavorites ? toFavoriteMap(serverFavorites) : readFavorites(),
    get count() {
        return Object.values(this.ids).filter(Boolean).length;
    },
    has(id) {
        return !!this.ids[id];
    },
    toggle(id, addedMessage, removedMessage) {
        const isOn = !this.ids[id];
        this.ids = { ...this.ids, [id]: isOn };

        if (this.signedIn) {
            Livewire.dispatch('favorite-toggle', { id, on: isOn });
        } else {
            writeFavorites(this.ids);

            if (isOn) {
                Livewire.dispatch('favorite-toggle', { id, on: true });
            }
        }

        Alpine.store('toast').show(isOn ? addedMessage : removedMessage, false, 2000);
    },
});

Alpine.store('cart', {
    items: {},
    qty(id) {
        return Object.entries(this.items)
            .filter(([key]) => key === String(id) || key.startsWith(`${id}:`))
            .reduce((sum, [, quantity]) => sum + quantity, 0);
    },
});

window.addEventListener('favorites-updated', (event) => {
    Alpine.store('favorites').ids = toFavoriteMap(event.detail.ids || []);
});

document.addEventListener('livewire:initialized', () => {
    const store = Alpine.store('favorites');
    const guestFavorites = readFavorites();
    const guestIds = Object.keys(guestFavorites).filter((id) => guestFavorites[id]).map(Number);

    if (!store.signedIn || !guestIds.length) {
        return;
    }

    store.ids = { ...store.ids, ...toFavoriteMap(guestIds) };
    writeFavorites({});
    Livewire.dispatch('favorites-merge', { ids: guestIds });
});

window.addEventListener('cart-updated', (event) => {
    Alpine.store('cart').items = event.detail.items || {};
});

window.addEventListener('toast', (event) => {
    Alpine.store('toast').show(event.detail.message, !!event.detail.withCart);
});

window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        Alpine.store('ui').closeAll();
    }
});

Alpine.data('stickyHeader', () => ({
    scrolled: false,
    showTop: false,
    init() {
        let queued = false;
        const update = () => {
            queued = false;
            const y = window.scrollY;

            if (!this.scrolled && y > 160) {
                this.scrolled = true;
            } else if (this.scrolled && y < 24) {
                this.scrolled = false;
            }

            this.showTop = y > 1200;
        };
        update();
        window.addEventListener('scroll', () => {
            if (!queued) {
                queued = true;
                requestAnimationFrame(update);
            }
        }, { passive: true });
    },
}));

Alpine.data('heroSlider', (count, interval = 7000) => ({
    slide: 0,
    count,
    timer: null,
    init() {
        this.restart();
    },
    go(index) {
        this.slide = (index + this.count) % this.count;
        this.restart();
    },
    restart() {
        clearInterval(this.timer);
        this.timer = setInterval(() => (this.slide = (this.slide + 1) % this.count), interval);
    },
    destroy() {
        clearInterval(this.timer);
    },
}));

Alpine.data('countdown', (endsAt = null) => ({
    hours: '00',
    minutes: '00',
    seconds: '00',
    timer: null,
    init() {
        const pad = (value) => String(value).padStart(2, '0');
        const tick = () => {
            const now = new Date();
            const end = new Date(now);
            end.setHours(24, 0, 0, 0);
            if (endsAt && new Date(endsAt) < end) {
                end.setTime(new Date(endsAt).getTime());
            }
            const left = Math.max(0, Math.floor((end - now) / 1000));
            this.hours = pad(Math.floor(left / 3600));
            this.minutes = pad(Math.floor((left % 3600) / 60));
            this.seconds = pad(left % 60);
        };
        tick();
        this.timer = setInterval(tick, 1000);
    },
    destroy() {
        clearInterval(this.timer);
    },
}));

const carousel = () => ({
    scroll(direction) {
        const track = this.$refs.track;
        if (!track) {
            return;
        }
        const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
        track.scrollBy({ left: direction * (track.clientWidth - 12 + gap), behavior: 'smooth' });
    },
});

Alpine.data('carousel', carousel);

Alpine.data('productSection', () => ({
    ...carousel(),
    active: '',
    select(value) {
        this.active = value;
        this.$nextTick(() => this.$refs.track && this.$refs.track.scrollTo({ left: 0 }));
    },
    visible(sub) {
        return this.active === '' || this.active === sub;
    },
}));

const money = (amount) => `${Math.round(amount).toLocaleString('en-US').replace(/,/g, ' ')} ₾`;

Alpine.data('productPage', (config) => ({
    ...config,
    qty: 1,
    image: 0,
    showBar: false,
    money,
    init() {
        const update = () => {
            const button = this.$refs.mainBuy;
            this.showBar = !!button && button.getBoundingClientRect().bottom < 70;
        };
        update();
        window.addEventListener('scroll', update, { passive: true });
    },
    get colorOption() {
        return this.colors[this.color] ?? null;
    },
    get storageOption() {
        return this.storages[this.storage] ?? null;
    },
    get unitPrice() {
        return this.storageOption?.price ?? this.basePrice;
    },
    get unitOld() {
        return this.storageOption?.price != null ? this.storageOption.old : this.baseOld;
    },
    get total() {
        return this.unitPrice * this.qty;
    },
    get variantLabel() {
        return [this.colorOption?.label, this.storageOption?.label].filter(Boolean).join(' · ');
    },
    storageDiff(option) {
        if (option.price == null || option === this.storageOption) {
            return this.selectedLabel;
        }
        const diff = option.price - this.unitPrice;
        return `${diff > 0 ? '+' : '−'}${money(Math.abs(diff))}`;
    },
    optionIds() {
        return [this.colorOption?.id, this.storageOption?.id].filter(Boolean);
    },
    stepImage(step) {
        this.image = (this.image + step + this.images) % this.images;
    },
    add() {
        Livewire.dispatch('cart-add', { id: this.id, qty: this.qty, options: this.optionIds() });
        this.qty = 1;
    },
}));

const searchStem = (word) => {
    if (word.length >= 6) {
        return word.slice(0, Math.max(4, word.length - 3));
    }

    return word.length >= 4 ? word.slice(0, -1) : word;
};

const searchScore = (text, term) => {
    const haystack = String(text ?? '').toLowerCase();

    if (haystack.split(/\s+/).some((word) => word.startsWith(term))) {
        return 3;
    }

    if (haystack.includes(term)) {
        return 2;
    }

    const words = term.split(/\s+/).filter(Boolean);

    return words.length && words.every((word) => haystack.includes(searchStem(word))) ? 1 : 0;
};

Alpine.data('searchBox', (products, categories, resultsUrl) => ({
    query: '',
    focused: false,
    get term() {
        return this.query.trim().toLowerCase();
    },
    get open() {
        return this.focused && this.term !== '';
    },
    get productMatches() {
        if (!this.term) {
            return [];
        }
        return products
            .map((product) => ({ product, score: searchScore(`${product.name} ${product.brand} ${product.subLabel}`, this.term) }))
            .filter((match) => match.score > 0)
            .sort((a, b) => b.score - a.score)
            .map((match) => match.product);
    },
    get categoryMatches() {
        if (!this.term) {
            return [];
        }
        return categories
            .map((category) => ({ ...category, score: Math.max(searchScore(category.name, this.term), searchScore(category.short, this.term)) }))
            .filter((category) => category.score > 0)
            .sort((a, b) => b.score - a.score || b.count - a.count || a.depth - b.depth)
            .slice(0, 8);
    },
    clear() {
        this.query = '';
    },
    submit() {
        const query = this.query.trim();
        if (!query) {
            return;
        }
        this.focused = false;
        this.query = '';
        Livewire.navigate(`${resultsUrl}?q=${encodeURIComponent(query)}`);
    },
    jumpTo(product) {
        this.focused = false;
        this.query = '';
        Livewire.navigate(product.url);
    },
    openCategory(category) {
        this.focused = false;
        this.query = '';
        Livewire.navigate(category.url);
    },
}));

Alpine.data('catalogDrawer', (categories) => ({
    categories,
    index: 0,
    pane: 'list',
    get active() {
        return this.categories[this.index];
    },
    openAt(index) {
        this.index = index ?? 0;
        this.pane = index === undefined || index === null ? 'list' : 'sub';
        Alpine.store('ui').catalog = true;
    },
}));

document.addEventListener('alpine:initialized', () => {
    if (window.elioFlash) {
        Alpine.store('toast').show(window.elioFlash, false, 4000);
        window.elioFlash = null;
    }
});

Livewire.start();
