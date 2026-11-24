import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { animate } from 'motion';
import 'number-flow';
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

const flows = new WeakMap();

const syncFlow = (holder) => {
    const value = Number(holder.dataset.flow);

    if (!Number.isFinite(value)) {
        return;
    }

    let state = flows.get(holder);

    if (!state || !holder.contains(state.flow)) {
        const flow = document.createElement('number-flow');
        flow.locales = 'ka-GE';
        flow.format = { maximumFractionDigits: 0 };
        flow.numberPrefix = holder.dataset.flowPrefix ?? '';
        flow.numberSuffix = holder.dataset.flowSuffix ?? ' ₾';
        const from = state ? state.value : value;
        holder.replaceChildren(flow);
        flow.update(from);
        state = { flow, value: from };
        flows.set(holder, state);
    }

    if (state.value !== value) {
        state.value = value;
        requestAnimationFrame(() => state.flow.update(value));
    }
};

const syncFlowsIn = (root) => {
    if (root.nodeType !== 1) {
        return;
    }

    if (root.matches('[data-flow]')) {
        syncFlow(root);
    }

    root.querySelectorAll('[data-flow]').forEach(syncFlow);
};

new MutationObserver((mutations) => {
    for (const mutation of mutations) {
        if (mutation.type === 'attributes') {
            syncFlow(mutation.target);
            continue;
        }

        const holder = mutation.target.closest?.('[data-flow]');

        if (holder) {
            syncFlow(holder);
        }

        mutation.addedNodes.forEach(syncFlowsIn);
    }
}).observe(document.documentElement, { subtree: true, childList: true, attributes: true, attributeFilter: ['data-flow'] });

document.addEventListener('DOMContentLoaded', () => syncFlowsIn(document.body));

const projectMomentum = (velocity, deceleration = 0.998) => ((velocity / 1000) * deceleration) / (1 - deceleration);

const rubberband = (overshoot, dimension, constant = 0.55) => (overshoot * dimension * constant) / (dimension + constant * Math.abs(overshoot));

Alpine.directive('swipe-close', (el, { value: side, expression }, { evaluateLater, cleanup }) => {
    const close = evaluateLater(expression);
    const sign = side === 'left' ? -1 : 1;
    const scrim = el.previousElementSibling;
    let grab = null;
    let dragging = false;
    let samples = [];
    let offset = 0;
    let motion = null;

    const paint = (x) => {
        offset = x;
        el.style.transform = x ? `translateX(${x}px)` : '';

        if (scrim) {
            scrim.style.opacity = String(Math.max(0, 1 - Math.max(0, x * sign) / el.offsetWidth));
        }
    };

    const reset = () => {
        paint(0);

        if (scrim) {
            scrim.style.opacity = '';
        }
    };

    const down = (event) => {
        if (event.pointerType === 'mouse' || event.button > 0) {
            return;
        }

        motion?.stop();
        motion = null;
        grab = { x: event.clientX - offset, y: event.clientY, id: event.pointerId };
        dragging = false;
        samples = [{ x: event.clientX, t: event.timeStamp }];
    };

    const move = (event) => {
        if (!grab || event.pointerId !== grab.id) {
            return;
        }

        const dx = event.clientX - grab.x;
        const dy = event.clientY - grab.y;

        if (!dragging) {
            if (Math.abs(dx - offset) < 10 && Math.abs(dy) < 10) {
                return;
            }

            if (Math.abs(dy) > Math.abs(dx - offset)) {
                grab = null;

                return;
            }

            dragging = true;
            try {
                el.setPointerCapture(event.pointerId);
            } catch {}
        }

        samples = [...samples.slice(-4), { x: event.clientX, t: event.timeStamp }];
        paint(dx * sign >= 0 ? dx : sign * -rubberband(Math.abs(dx), el.offsetWidth));
    };

    const up = (event) => {
        if (!grab || event.pointerId !== grab.id) {
            return;
        }

        grab = null;

        if (!dragging) {
            return;
        }

        dragging = false;
        const first = samples[0];
        const last = samples[samples.length - 1];
        const velocity = ((last.x - first.x) / Math.max(1, last.t - first.t)) * 1000;
        const width = el.offsetWidth;
        const closing = (offset + projectMomentum(velocity)) * sign > width * 0.4;
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        motion = animate(offset, closing ? sign * width : 0, {
            type: 'spring',
            bounce: closing ? 0 : 0.18,
            duration: reduce ? 0.01 : 0.4,
            velocity,
            onUpdate: paint,
        });

        motion.then(() => {
            motion = null;

            if (closing) {
                close();
                requestAnimationFrame(reset);
            } else {
                reset();
            }
        });
    };

    el.style.touchAction = 'pan-y';
    el.addEventListener('pointerdown', down);
    el.addEventListener('pointermove', move);
    el.addEventListener('pointerup', up);
    el.addEventListener('pointercancel', up);

    cleanup(() => {
        motion?.stop();
        el.removeEventListener('pointerdown', down);
        el.removeEventListener('pointermove', move);
        el.removeEventListener('pointerup', up);
        el.removeEventListener('pointercancel', up);
    });
});

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

let cartFlightOrigin = null;

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-addbtn], [data-cart-origin]');

    if (button) {
        const rect = button.getBoundingClientRect();
        cartFlightOrigin = { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2, at: Date.now() };
    }
}, true);

const flyToCart = () => {
    const target = [...document.querySelectorAll('[data-cart-target]')].find((element) => element.offsetParent !== null);
    const origin = cartFlightOrigin;
    cartFlightOrigin = null;

    if (!target) {
        return;
    }

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const bump = () => target.animate(
        [{ transform: 'scale(1)' }, { transform: 'scale(1.28)' }, { transform: 'scale(0.94)' }, { transform: 'scale(1)' }],
        { duration: 520, easing: 'cubic-bezier(.3, 1.5, .5, 1)' },
    );

    if (reduced || !origin || Date.now() - origin.at > 4000) {
        bump();

        return;
    }

    const end = target.getBoundingClientRect();
    const dx = end.left + end.width / 2 - origin.x;
    const dy = end.top + end.height / 2 - origin.y;
    const dot = document.createElement('span');
    dot.style.cssText = `position:fixed;left:${origin.x - 9}px;top:${origin.y - 9}px;width:18px;height:18px;border-radius:9999px;background:var(--color-brand);box-shadow:0 6px 18px -4px rgba(11,99,246,.7);z-index:90;pointer-events:none`;
    document.body.appendChild(dot);

    dot.animate(
        [
            { transform: 'translate(0, 0) scale(1)', opacity: 1 },
            { transform: `translate(${dx * 0.45}px, ${dy * 0.45 - 70}px) scale(1.15)`, opacity: 1, offset: 0.45 },
            { transform: `translate(${dx}px, ${dy}px) scale(0.35)`, opacity: 0.6 },
        ],
        { duration: 620, easing: 'cubic-bezier(.45, 0, .25, 1)' },
    ).finished.then(() => {
        dot.remove();
        bump();
    });
};

window.addEventListener('toast', (event) => {
    Alpine.store('toast').show(event.detail.message, !!event.detail.withCart);

    if (event.detail.withCart) {
        flyToCart();
    }
});

if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    let spotFrame = 0;
    document.addEventListener('pointermove', (event) => {
        const card = event.target.closest?.('[data-card]');

        if (!card || spotFrame) {
            return;
        }

        spotFrame = requestAnimationFrame(() => {
            spotFrame = 0;
            const box = card.getBoundingClientRect();
            card.style.setProperty('--mx', `${event.clientX - box.left}px`);
            card.style.setProperty('--my', `${event.clientY - box.top}px`);
        });
    }, { passive: true });
}

window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        Alpine.store('ui').closeAll();
    }
});

Alpine.data('stickyHeader', () => ({
    scrolled: false,
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

        };
        update();
        let settle = null;
        const heights = {};
        const root = document.documentElement.style;
        this.$watch('scrolled', (value) => {
            if (heights[value]) {
                root.setProperty('--header-h', `${heights[value]}px`);
            }
        });
        const publishHeight = () => {
            const height = Math.round(this.$el.getBoundingClientRect().height);
            if (height <= 0) {
                return;
            }
            if (!heights[this.scrolled]) {
                heights[this.scrolled] = height;
                root.setProperty('--header-h', `${height}px`);
            }
            clearTimeout(settle);
            settle = setTimeout(() => {
                const settled = Math.round(this.$el.getBoundingClientRect().height);
                heights[this.scrolled] = settled;
                root.setProperty('--header-h', `${settled}px`);
                if (!this.scrolled) {
                    root.setProperty('--header-full', `${settled}px`);
                }
            }, 320);
        };
        publishHeight();
        if (window.ResizeObserver) {
            new ResizeObserver(publishHeight).observe(this.$el);
        }
        window.addEventListener('scroll', () => {
            if (!queued) {
                queued = true;
                requestAnimationFrame(update);
            }
        }, { passive: true });
    },
}));

Alpine.data('backToTop', () => ({
    showTop: false,
    init() {
        let queued = false;
        const update = () => {
            queued = false;
            const visible = window.scrollY > 1200;
            if (visible !== this.showTop) {
                this.showTop = visible;
            }
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

Alpine.data('campaignCountdown', (endsAt) => ({
    days: '0',
    hours: '00',
    minutes: '00',
    seconds: '00',
    timer: null,
    init() {
        const pad = (value) => String(value).padStart(2, '0');
        const end = new Date(endsAt).getTime();
        const tick = () => {
            const left = Math.max(0, Math.floor((end - Date.now()) / 1000));
            this.days = String(Math.floor(left / 86400));
            this.hours = pad(Math.floor((left % 86400) / 3600));
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
    zoom: false,
    showBar: false,
    money,
    init() {
        let queued = false;
        const update = () => {
            queued = false;
            const button = this.$refs.mainBuy;
            const visible = !!button && button.getBoundingClientRect().bottom < 70;
            if (visible !== this.showBar) {
                this.showBar = visible;
            }
        };
        update();
        window.addEventListener('scroll', () => {
            if (!queued) {
                queued = true;
                requestAnimationFrame(update);
            }
        }, { passive: true });
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

const georgianLatin = { ა: 'a', ბ: 'b', გ: 'g', დ: 'd', ე: 'e', ვ: 'v', ზ: 'z', თ: 't', ი: 'i', კ: 'k', ლ: 'l', მ: 'm', ნ: 'n', ო: 'o', პ: 'p', ჟ: 'zh', რ: 'r', ს: 's', ტ: 't', უ: 'u', ფ: 'p', ქ: 'k', ღ: 'gh', ყ: 'k', შ: 'sh', ჩ: 'ch', ც: 'ts', ძ: 'dz', წ: 'ts', ჭ: 'ch', ხ: 'kh', ჯ: 'j', ჰ: 'h' };

const searchKey = (text) => String(text ?? '')
    .toLowerCase()
    .replace(/[ა-ჰ]/g, (letter) => georgianLatin[letter] ?? letter)
    .replace(/ch|ph|th|ck|q|w|y|x|f|c/g, (pair) => ({ ch: 'ch', ph: 'p', th: 't', ck: 'k', q: 'k', w: 'v', y: 'i', x: 'kh', f: 'p', c: 'k' })[pair])
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();

const searchScore = (text, query) => {
    const haystack = searchKey(text);
    const term = searchKey(query);

    if (!term || !haystack) {
        return 0;
    }

    if (haystack.split(/\s+/).some((word) => word.startsWith(term))) {
        return 3;
    }

    if (haystack.includes(term)) {
        return 2;
    }

    const words = term.split(/\s+/).filter(Boolean);

    return words.length && words.every((word) => haystack.includes(searchStem(word))) ? 1 : 0;
};

Alpine.data('searchBox', (categories, resultsUrl, suggestUrl) => ({
    query: '',
    focused: false,
    productMatches: [],
    total: 0,
    loading: false,
    found: {},
    timer: null,
    request: null,
    init() {
        this.$watch('query', () => this.suggest());
    },
    get term() {
        return this.query.trim().toLowerCase();
    },
    get open() {
        return this.focused && this.term !== '';
    },
    suggest() {
        clearTimeout(this.timer);
        this.request?.abort();
        const term = this.term;

        if (!term) {
            this.productMatches = [];
            this.total = 0;
            this.loading = false;
            return;
        }

        if (this.found[term]) {
            ({ products: this.productMatches, total: this.total } = this.found[term]);
            this.loading = false;
            return;
        }

        this.loading = true;
        this.timer = setTimeout(async () => {
            const request = new AbortController();
            this.request = request;

            try {
                const response = await fetch(`${suggestUrl}?q=${encodeURIComponent(term)}`, { signal: request.signal, headers: { Accept: 'application/json' } });
                const answer = response.ok ? await response.json() : { products: [], total: 0 };
                this.found[term] = answer;

                if (this.term === term) {
                    this.productMatches = answer.products;
                    this.total = answer.total;
                    this.loading = false;
                }
            } catch (error) {
                if (error.name !== 'AbortError' && this.term === term) {
                    this.loading = false;
                }
            }
        }, 180);
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
