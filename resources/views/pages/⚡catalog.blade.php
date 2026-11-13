<?php

use App\Support\Catalog;
use App\Support\CategoryTree;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

new class extends Component
{
    public const PER_PAGE = 12;

    public const SORTS = ['popular', 'price_asc', 'price_desc', 'newest'];

    public const FLAGS = ['sale', 'new', 'stock'];

    public const PRICE_STEP = 50;

    #[Locked]
    public ?int $categoryId = null;

    #[Url(except: 'popular')]
    public string $sort = 'popular';

    #[Url(as: 'min')]
    public ?int $minPrice = null;

    #[Url(as: 'max')]
    public ?int $maxPrice = null;

    #[Url(as: 'brand')]
    public array $brands = [];

    #[Url(as: 'only')]
    public array $flags = [];

    #[Url(as: 'f')]
    public array $attributeFilters = [];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'all', except: false)]
    public bool $browseAll = false;

    public int $limit = self::PER_PAGE;

    public function mount(?string $slug = null): void
    {
        if ($slug !== null) {
            $node = CategoryTree::findBySlug($slug);

            if (! $node) {
                $node = CategoryTree::findBySlugInAnyLocale($slug);

                abort_unless($node, 404);

                $this->redirect(CategoryTree::url($node), navigate: true);

                return;
            }

            $this->categoryId = $node['id'];
        }

        $this->normalize();
    }

    public function updated(string $property): void
    {
        $this->normalize();
        $this->limit = self::PER_PAGE;
    }

    public function toggleBrand(string $brand): void
    {
        $this->brands = in_array($brand, $this->brands, true)
            ? array_values(array_diff($this->brands, [$brand]))
            : [...$this->brands, $brand];
        $this->updated('brands');
    }

    public function toggleAttribute(string $attribute, string $value): void
    {
        $selected = (array) ($this->attributeFilters[$attribute] ?? []);
        $selected = in_array($value, $selected, true)
            ? array_values(array_diff($selected, [$value]))
            : [...$selected, $value];

        if ($selected) {
            $this->attributeFilters[$attribute] = $selected;
        } else {
            unset($this->attributeFilters[$attribute]);
        }

        $this->updated('attributeFilters');
    }

    public function toggleFlag(string $flag): void
    {
        $this->flags = in_array($flag, $this->flags, true)
            ? array_values(array_diff($this->flags, [$flag]))
            : [...$this->flags, $flag];
        $this->updated('flags');
    }

    public function setSort(string $sort): void
    {
        $this->sort = $sort;
        $this->updated('sort');
    }

    public function clearPrice(): void
    {
        $this->minPrice = null;
        $this->maxPrice = null;
        $this->updated('minPrice');
    }

    public function clearFilters(): void
    {
        $this->reset('minPrice', 'maxPrice', 'brands', 'flags', 'attributeFilters');
        $this->limit = self::PER_PAGE;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->limit = self::PER_PAGE;
    }

    public function loadMore(): void
    {
        $this->limit += self::PER_PAGE;
    }

    #[Computed]
    public function category(): ?array
    {
        return $this->categoryId ? CategoryTree::find($this->categoryId) : null;
    }

    #[Computed]
    public function baseProducts(): Collection
    {
        return $this->category ? Catalog::productsIn($this->category) : Catalog::products();
    }

    #[Computed]
    public function matched(): Collection
    {
        return Catalog::search($this->baseProducts, $this->search);
    }

    #[Computed]
    public function priceCap(): int
    {
        $max = (float) $this->matched->max('price');

        return max(self::PRICE_STEP, (int) (ceil($max / self::PRICE_STEP) * self::PRICE_STEP));
    }

    #[Computed]
    public function results(): Collection
    {
        $products = $this->matched
            ->when($this->minPrice !== null, fn (Collection $items) => $items->filter(fn (array $product) => $product['price'] >= $this->minPrice))
            ->when($this->maxPrice !== null, fn (Collection $items) => $items->filter(fn (array $product) => $product['price'] <= $this->maxPrice))
            ->when($this->brands, fn (Collection $items) => $items->filter(fn (array $product) => in_array($product['brand'], $this->brands, true)));

        foreach ($this->attributeFilters as $attribute => $values) {
            $products = $products->filter(fn (array $product) => array_intersect((array) $values, $product['filters'][$attribute] ?? []) !== []);
        }

        foreach ($this->flags as $flag) {
            $products = $products->filter(fn (array $product) => $this->matchesFlag($product, $flag));
        }

        return (match ($this->sort) {
            'price_asc' => $products->sortBy('price'),
            'price_desc' => $products->sortByDesc('price'),
            'newest' => $products->sortBy([['isNew', 'desc'], ['id', 'desc']]),
            default => $products,
        })->values();
    }

    #[Computed]
    public function brandFacets(): Collection
    {
        return $this->matched
            ->pluck('brand')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->map(fn (int $count, string $brand) => ['name' => $brand, 'count' => $count])
            ->values();
    }

    #[Computed]
    public function flagCounts(): array
    {
        return collect(self::FLAGS)
            ->mapWithKeys(fn (string $flag) => [$flag => $this->matched->filter(fn (array $product) => $this->matchesFlag($product, $flag))->count()])
            ->all();
    }

    #[Computed]
    public function attributeFacets(): Collection
    {
        $lineage = $this->category ? [$this->category['id'], ...CategoryTree::ancestors($this->category['id'])->pluck('id')->all()] : [];

        return Catalog::filterAttributes()
            ->filter(fn (array $attribute) => $attribute['categories'] === [] || array_intersect($attribute['categories'], $lineage) !== [])
            ->map(function (array $attribute) {
                $counts = $this->matched
                    ->flatMap(fn (array $product) => $product['filters'][$attribute['code']] ?? [])
                    ->countBy();

                $values = collect($attribute['values'])
                    ->filter(fn (array $value) => $counts->has($value['code']))
                    ->map(fn (array $value) => [...$value, 'count' => $counts->get($value['code'])])
                    ->values();

                return [...$attribute, 'values' => $values];
            })
            ->filter(fn (array $attribute) => $attribute['values']->isNotEmpty())
            ->values();
    }

    public function activeCount(): int
    {
        return count($this->brands) + count($this->flags) + collect($this->attributeFilters)->flatten()->count() + ($this->minPrice !== null || $this->maxPrice !== null ? 1 : 0);
    }

    public function searchTerm(): string
    {
        return trim(preg_replace('/\s+/u', ' ', $this->search) ?? '');
    }

    public function isLanding(): bool
    {
        return $this->categoryId === null && ! $this->browseAll && $this->searchTerm() === '' && $this->activeCount() === 0;
    }

    protected function landingTiles(): Collection
    {
        return Catalog::categories()
            ->filter(fn (array $category) => $category['count'] > 0)
            ->sortByDesc('count')
            ->values();
    }

    protected function matchesFlag(array $product, string $flag): bool
    {
        return match ($flag) {
            'sale' => $product['old'] !== null,
            'new' => $product['isNew'],
            'stock' => $product['inStock'],
            default => true,
        };
    }

    protected function normalize(): void
    {
        $this->sort = in_array($this->sort, self::SORTS, true) ? $this->sort : 'popular';
        $this->search = mb_substr($this->search, 0, 80);
        $this->flags = array_values(array_intersect(self::FLAGS, array_map('strval', $this->flags)));
        $this->brands = array_values(array_unique(array_filter(array_map('strval', $this->brands))));
        $known = Catalog::filterAttributes();
        $this->attributeFilters = collect($this->attributeFilters)
            ->filter(fn ($values, $attribute) => is_string($attribute) && $known->has($attribute) && is_array($values))
            ->map(fn (array $values, string $attribute) => array_values(array_intersect(array_keys($known->get($attribute)['values']), array_map('strval', $values))))
            ->filter()
            ->all();
        $this->minPrice = $this->minPrice !== null && $this->minPrice > 0 ? $this->minPrice : null;
        $this->maxPrice = $this->maxPrice !== null && $this->maxPrice > 0 ? $this->maxPrice : null;

        if ($this->minPrice !== null && $this->maxPrice !== null && $this->minPrice > $this->maxPrice) {
            [$this->minPrice, $this->maxPrice] = [$this->maxPrice, $this->minPrice];
        }
    }

    protected function navigation(): array
    {
        $node = $this->category;
        $products = Catalog::products();
        $present = fn (array $item) => [
            'id' => $item['id'],
            'name' => $item['title'],
            'url' => CategoryTree::url($item),
            'count' => Catalog::productsIn($item, $products)->count(),
        ];

        if (! $node) {
            return ['rows' => CategoryTree::roots()->map($present)->all(), 'back' => null];
        }

        $parent = $node['parent_id'] ? CategoryTree::find($node['parent_id']) : null;
        $children = CategoryTree::children($node['id']);
        $rows = $children->isNotEmpty() ? $children : ($parent ? CategoryTree::children($parent['id']) : collect([$node]));
        $backNode = $children->isNotEmpty() ? $parent : ($parent && $parent['parent_id'] ? CategoryTree::find($parent['parent_id']) : null);

        return [
            'rows' => $rows->map($present)->all(),
            'back' => $backNode
                ? ['label' => $backNode['title'], 'url' => CategoryTree::url($backNode)]
                : ['label' => __('catalog.back_all'), 'url' => route('catalog')],
        ];
    }

    public function render()
    {
        $node = $this->category;
        if ($node) {
            view()->share('alternateUrls', collect($node['slugs'])
                ->mapWithKeys(fn (string $slug, string $locale) => [
                    $locale => LaravelLocalization::getLocalizedURL($locale, '/categories/'.$slug),
                ])
                ->filter()
                ->all());
        }

        $landing = $this->isLanding();
        $term = $this->searchTerm();

        return $this->view([
            'landing' => $landing,
            'term' => $term,
            'tiles' => $landing ? $this->landingTiles() : collect(),
            'node' => $node,
            'ancestors' => $node ? CategoryTree::ancestors($node['id']) : collect(),
            'navigation' => $this->navigation(),
            'shown' => $this->results->take($this->limit),
        ])->title($node['title'] ?? ($term !== '' ? __('catalog.search.title', ['term' => $term]) : __('catalog.title')));
    }
};
?>

@php
    $total = $this->results->count();
    $cap = $this->priceCap;
    $low = min($minPrice ?? 0, $cap);
    $high = min($maxPrice ?? $cap, $cap);
    $hasActive = $this->activeCount() > 0;
    $tileBackgrounds = ['#E9F1FF', '#F3F5F9', '#F3F5F9', '#EEF6F2', '#F3F5F9', '#F6F1FA'];
    $chipClose = '<span class="grid size-4 place-items-center rounded-full bg-white/18"><svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span>';
    $section = 'border-t border-line';
    $sectionButton = 'flex w-full items-center justify-between gap-2.5 bg-transparent px-0.5 py-[18px] text-left text-navy';
    $chevron = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="text-subtle transition-transform"><path d="m6 9 6 6 6-6"/></svg>';
@endphp

<main x-data="{ filtersOpen: false }" class="mx-auto max-w-[1480px] px-4 pt-[22px] pb-16 sm:px-6 lg:px-8">
    <nav aria-label="breadcrumb" class="flex flex-wrap items-center gap-2 text-xs tracking-[0.2px] text-subtle">
        <a href="{{ route('home') }}" wire:navigate class="text-muted hover:text-brand">{{ __('account.home') }}</a>
        <span class="size-1 rounded-full bg-[#C9D3E3]"></span>
        @if ($node)
            <a href="{{ route('catalog') }}" wire:navigate class="text-muted hover:text-brand">{{ __('catalog.title') }}</a>
            @foreach ($ancestors as $ancestor)
                <span class="size-1 rounded-full bg-[#C9D3E3]"></span>
                <a href="{{ CategoryTree::url($ancestor) }}" wire:navigate class="text-muted hover:text-brand">{{ $ancestor['title'] }}</a>
            @endforeach
            <span class="size-1 rounded-full bg-[#C9D3E3]"></span>
            <span class="font-semibold text-navy">{{ $node['title'] }}</span>
        @else
            <span class="font-semibold text-navy">{{ __('catalog.title') }}</span>
        @endif
    </nav>

    <h1 class="m-0 mt-[22px] flex items-start gap-3.5 text-[clamp(26px,3.4vw,46px)] leading-[1.1] font-extrabold tracking-[0.2px] text-balance">
        @if ($node)
            {{ $node['title'] }}
        @elseif ($term !== '')
            {{ __('catalog.search.heading') }}
        @elseif ($landing)
            {{ __('catalog.title') }}
        @else
            {{ __('catalog.all_products') }}
        @endif
        <span class="mt-1 shrink-0 rounded-full bg-tint px-[11px] py-[5px] font-display text-[15px] leading-none font-extrabold tracking-normal text-brand">{{ $this->matched->count() }}</span>
    </h1>

    @if ($landing)
        <div class="mt-7 grid auto-rows-[140px] grid-cols-2 gap-2.5 sm:auto-rows-[170px] sm:grid-cols-4 sm:gap-3.5 lg:auto-rows-[190px] lg:grid-cols-5">
            @foreach ($tiles as $index => $tile)
                <a
                    href="{{ $tile['url'] }}"
                    wire:navigate
                    wire:key="catalog-tile-{{ $tile['key'] }}"
                    @class([
                        'relative overflow-hidden rounded-3xl text-navy transition duration-200 hover:-translate-y-[3px] hover:text-navy hover:shadow-[0_22px_44px_-22px_rgba(10,22,51,.35)]',
                        'col-span-2 lg:row-span-2' => $index === 0,
                    ])
                    style="background: {{ $tileBackgrounds[$index % count($tileBackgrounds)] }}"
                >
                    @include('partials.category-icon', ['icon' => $tile['icon'], 'class' => 'absolute right-[6%] bottom-[6%] text-brand/25 '.($index === 0 ? 'w-[46%] lg:w-[58%]' : 'w-[46%]')])
                    <div class="pointer-events-none absolute top-[18px] right-5 left-5 flex flex-col gap-1">
                        <span @class([
                            'font-caps leading-[1.3] font-extrabold tracking-[0.3px] uppercase',
                            'text-base sm:text-xl' => $index === 0,
                            'text-[13px]' => $index !== 0,
                        ])>{{ $tile['short'] }}</span>
                        <span class="font-display text-xs font-bold text-muted">{{ __('shop.products_count', ['count' => $tile['count']]) }}</span>
                    </div>
                </a>
            @endforeach
            <button type="button" x-on:click="$dispatch('open-catalog')" class="relative flex flex-col justify-between rounded-3xl bg-navy px-5 py-[18px] text-left text-white transition-colors hover:bg-brand">
                <span class="font-caps text-sm leading-[1.35] font-extrabold tracking-[0.3px] uppercase">{{ __('shop.all_categories') }}</span>
                <span class="grid size-[42px] place-items-center self-start rounded-full bg-white text-navy">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </span>
            </button>
        </div>

        <div class="mt-6 flex justify-center">
            <a href="{{ route('catalog', ['all' => 1]) }}" wire:navigate class="flex h-12 items-center gap-2 rounded-full border-[1.5px] border-line bg-white px-6 text-sm font-bold text-navy hover:border-brand hover:text-brand">
                {{ __('catalog.landing.all_products', ['count' => $this->matched->count()]) }}
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>
    @else
    <div class="sticky top-(--header-h) z-[15] -mx-4 mt-[22px] flex flex-wrap items-center justify-between gap-3 border-b border-[#EEF2F8] bg-white px-4 py-3 transition-[top] duration-300 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div class="flex min-w-0 flex-wrap items-center gap-2.5">
            <span class="text-[13px] whitespace-nowrap text-muted">
                <span class="font-display text-[15px] font-extrabold text-navy">{{ $total }}</span>
                {{ trans_choice('catalog.results', $total) }}
            </span>
            @if ($term !== '')
                <span class="hidden h-5 w-px bg-[#E3E9F3] sm:block"></span>
                <button type="button" wire:click="clearSearch" class="flex h-[30px] max-w-[260px] items-center gap-1.5 rounded-full bg-brand pr-2 pl-3 text-xs font-semibold text-white hover:bg-navy">
                    <span class="truncate">„{{ $term }}“</span>
                    {!! $chipClose !!}
                </button>
            @endif
            @if ($hasActive)
                <span class="hidden h-5 w-px bg-[#E3E9F3] sm:block"></span>
                @if ($minPrice !== null || $maxPrice !== null)
                    <button type="button" wire:click="clearPrice" class="flex h-[30px] items-center gap-1.5 rounded-full bg-navy pr-2 pl-3 text-xs font-semibold text-white hover:bg-brand">
                        {{ Catalog::money($minPrice ?? 0) }} – {{ Catalog::money($maxPrice ?? $cap) }}
                        <span class="grid size-4 place-items-center rounded-full bg-white/18"><svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span>
                    </button>
                @endif
                @foreach ($attributeFilters as $attributeCode => $valueCodes)
                    @php($facetMeta = App\Support\Catalog::filterAttributes()->get($attributeCode))
                    @foreach ((array) $valueCodes as $valueCode)
                        <button type="button" wire:key="chip-attr-{{ $attributeCode }}-{{ $valueCode }}" wire:click="toggleAttribute(@js($attributeCode), @js($valueCode))" class="flex h-[30px] items-center gap-1.5 rounded-full bg-navy pr-2 pl-3 text-xs font-semibold text-white hover:bg-brand">
                            {{ $facetMeta['values'][$valueCode]['label'] ?? $valueCode }}
                            <span class="grid size-4 place-items-center rounded-full bg-white/18"><svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span>
                        </button>
                    @endforeach
                @endforeach
                @foreach ($brands as $brand)
                    <button type="button" wire:key="chip-brand-{{ $brand }}" wire:click="toggleBrand(@js($brand))" class="flex h-[30px] items-center gap-1.5 rounded-full bg-navy pr-2 pl-3 text-xs font-semibold text-white hover:bg-brand">
                        {{ $brand }}
                        <span class="grid size-4 place-items-center rounded-full bg-white/18"><svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span>
                    </button>
                @endforeach
                @foreach ($flags as $flag)
                    <button type="button" wire:key="chip-flag-{{ $flag }}" wire:click="toggleFlag('{{ $flag }}')" class="flex h-[30px] items-center gap-1.5 rounded-full bg-navy pr-2 pl-3 text-xs font-semibold text-white hover:bg-brand">
                        {{ __("catalog.flags.$flag") }}
                        <span class="grid size-4 place-items-center rounded-full bg-white/18"><svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span>
                    </button>
                @endforeach
                <button type="button" wire:click="clearFilters" class="h-[30px] px-1.5 text-xs font-bold text-muted hover:text-sale">{{ __('catalog.clear') }}</button>
            @endif
        </div>
        <div class="flex items-center gap-2.5">
            <button type="button" x-on:click="filtersOpen = true" class="flex h-10 items-center gap-2 rounded-xl border-[1.5px] border-[#E3E9F3] bg-white px-3.5 text-[13px] font-bold text-navy lg:hidden">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                {{ __('catalog.filters') }}
                @if ($hasActive)
                    <span class="rounded-full bg-brand px-[7px] py-0.5 font-display text-[11px] font-extrabold text-white">{{ $this->activeCount() }}</span>
                @endif
            </button>
            <div x-data="{ open: false }" class="relative">
                <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="flex h-10 items-center gap-2.5 rounded-xl border-[1.5px] bg-white pr-3.5 pl-4 text-[13px] font-bold text-navy hover:border-brand" x-bind:class="open ? 'border-brand' : 'border-[#E3E9F3]'">
                    <svg class="hidden sm:block" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8A97AE" stroke-width="2.2" stroke-linecap="round"><path d="M4 7h16M7 12h10M10 17h4"/></svg>
                    {{ __("catalog.sort.$sort") }}
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="transition-transform" x-bind:class="open && 'rotate-180'"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div x-show="open" x-cloak x-on:click.outside="open = false" x-on:keydown.escape.window="open = false" class="absolute top-12 right-0 z-[17] min-w-[220px] animate-drop rounded-[14px] border border-[#E3E9F3] bg-white p-1.5 shadow-[0_24px_48px_-16px_rgba(10,22,51,.25)]">
                    @foreach (array_keys(__('catalog.sort')) as $option)
                        <button type="button" wire:click="setSort('{{ $option }}')" x-on:click="open = false" @class(['flex h-10 w-full items-center justify-between gap-2.5 rounded-[9px] px-3 text-left text-[13px] font-semibold hover:bg-[#F3F5F9]', 'bg-[#F3F5F9] text-navy' => $sort === $option, 'text-[#3A4A68]' => $sort !== $option])>
                            {{ __("catalog.sort.$option") }}
                            <span @class(['font-extrabold text-brand', 'opacity-0' => $sort !== $option])>✓</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="mt-[26px] grid grid-cols-1 items-start gap-9 lg:grid-cols-[268px_minmax(0,1fr)]">
        <div x-show="filtersOpen" x-cloak x-transition.opacity.duration.300ms x-on:click="filtersOpen = false" class="fixed inset-0 z-[60] bg-navy/40 lg:hidden"></div>
        <aside
            class="fixed inset-y-0 left-0 z-[61] flex w-[min(340px,92vw)] flex-col gap-2 overflow-y-auto overscroll-contain bg-white px-5 pt-5 pb-24 shadow-[20px_0_60px_rgba(10,22,51,.25)] transition-[translate,visibility] duration-300 ease-[cubic-bezier(.22,1,.36,1)] max-lg:not-data-open:invisible max-lg:not-data-open:-translate-x-full lg:static lg:z-auto lg:w-auto lg:overflow-visible lg:bg-transparent lg:p-0 lg:shadow-none lg:transition-none"
            x-bind:data-open="filtersOpen"
        >
            <div class="mb-2 flex items-center justify-between lg:hidden">
                <span class="text-lg font-extrabold">{{ __('catalog.filters') }}</span>
                <button type="button" x-on:click="filtersOpen = false" aria-label="{{ __('shop.close') }}" class="grid size-10 place-items-center rounded-full bg-[#F1F5FC] text-navy">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            @if ($navigation['back'])
                <a href="{{ $navigation['back']['url'] }}" wire:navigate class="flex h-9 max-w-full items-center gap-2 self-start rounded-full bg-[#F3F5F9] pr-3.5 pl-2.5 text-xs font-bold text-navy hover:bg-navy hover:text-white">
                    <svg class="shrink-0" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>
                    <span class="truncate">{{ $navigation['back']['label'] }}</span>
                </a>
            @endif

            <div x-data="{ all: false }" class="mt-1.5 flex flex-col">
                @foreach ($navigation['rows'] as $index => $row)
                    @php($isCurrent = $node && $node['id'] === $row['id'])
                    <a
                        href="{{ $row['url'] }}"
                        wire:navigate
                        wire:key="nav-{{ $row['id'] }}"
                        @if ($index >= 8) x-show="all" x-cloak @endif
                        @class([
                            'group relative flex items-center justify-between gap-2.5 border-b border-[#EEF2F8] py-[13px] pr-1 transition-[padding,color] duration-200 hover:pl-3 hover:text-brand',
                            'pl-3 text-brand' => $isCurrent,
                            'pl-0 text-navy' => ! $isCurrent,
                        ])
                    >
                        <span @class(['absolute top-1/2 left-0 w-1 -translate-y-1/2 rounded-sm bg-brand transition-[height] duration-200 group-hover:h-[18px]', 'h-[18px]' => $isCurrent, 'h-0' => ! $isCurrent])></span>
                        <span class="font-caps text-xs leading-[1.4] font-extrabold tracking-[0.4px] uppercase">{{ $row['name'] }}</span>
                        <span class="flex shrink-0 items-center gap-2">
                            <span class="font-display text-[11px] font-bold text-subtle">{{ $row['count'] }}</span>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9AA6BC" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                        </span>
                    </a>
                @endforeach
                @if (count($navigation['rows']) > 8)
                    <button type="button" x-on:click="all = ! all" class="mt-3 h-[42px] rounded-xl border-[1.5px] border-dashed border-[#D5DEEC] text-xs font-bold text-navy hover:border-brand hover:text-brand">
                        <span x-show="! all">{{ __('catalog.show_more_categories') }}</span>
                        <span x-show="all" x-cloak>{{ __('catalog.show_less_categories') }}</span>
                    </button>
                @endif
            </div>

            <div class="mt-7 flex items-center justify-between">
                <span class="font-caps text-[11px] font-extrabold tracking-[1.2px] text-subtle uppercase">{{ __('catalog.filters') }}</span>
                @if ($hasActive)
                    <span class="rounded-full bg-brand px-2 py-[3px] font-display text-[11px] font-extrabold text-white">{{ $this->activeCount() }}</span>
                @endif
            </div>

            <div class="mt-1 flex flex-col">
                <div x-data="{ open: true }" class="{{ $section }}">
                    <button type="button" x-on:click="open = ! open" class="{{ $sectionButton }}">
                        <span class="text-sm font-extrabold">{{ __('catalog.price') }}</span>
                        <span class="flex items-center gap-2">
                            @if ($minPrice !== null || $maxPrice !== null)
                                <span class="text-xs font-bold text-brand">{{ Catalog::money($low) }} – {{ Catalog::money($high) }}</span>
                            @endif
                            <span x-bind:class="open && '[&>svg]:rotate-180'">{!! $chevron !!}</span>
                        </span>
                    </button>
                    <div x-show="open" class="px-0.5 pb-5">
                        <div class="relative mt-1 h-6">
                            <div class="absolute inset-x-0 top-2.5 h-1 rounded-sm bg-[#E3E9F3]"></div>
                            <div class="absolute top-2.5 h-1 rounded-sm bg-brand" style="left: {{ round($low / $cap * 100, 2) }}%; right: {{ round(100 - $high / $cap * 100, 2) }}%"></div>
                            <input data-range type="range" min="0" max="{{ $cap }}" step="{{ $this::PRICE_STEP }}" value="{{ $low }}" wire:change="$set('minPrice', Math.min($event.target.value, {{ $high }}))" aria-label="{{ __('catalog.min_price') }}">
                            <input data-range type="range" min="0" max="{{ $cap }}" step="{{ $this::PRICE_STEP }}" value="{{ $high }}" wire:change="$set('maxPrice', Math.max($event.target.value, {{ $low }}))" aria-label="{{ __('catalog.max_price') }}">
                        </div>
                        <div class="mt-3.5 grid grid-cols-2 gap-2">
                            <label class="flex flex-col gap-0.5 rounded-xl border-[1.5px] border-[#E3E9F3] px-3 py-2 focus-within:border-brand">
                                <span class="text-[10px] font-bold tracking-[0.4px] text-subtle">{{ __('catalog.from') }}</span>
                                <span class="flex items-center gap-1"><input type="number" min="0" wire:model.live.debounce.600ms="minPrice" placeholder="0" class="w-full border-0 bg-transparent p-0 font-display text-sm font-extrabold text-navy outline-none"><span class="font-bold text-subtle">₾</span></span>
                            </label>
                            <label class="flex flex-col gap-0.5 rounded-xl border-[1.5px] border-[#E3E9F3] px-3 py-2 focus-within:border-brand">
                                <span class="text-[10px] font-bold tracking-[0.4px] text-subtle">{{ __('catalog.to') }}</span>
                                <span class="flex items-center gap-1"><input type="number" min="0" wire:model.live.debounce.600ms="maxPrice" placeholder="{{ $cap }}" class="w-full border-0 bg-transparent p-0 font-display text-sm font-extrabold text-navy outline-none"><span class="font-bold text-subtle">₾</span></span>
                            </label>
                        </div>
                    </div>
                </div>

                @foreach ($this->attributeFacets as $facet)
                    @php($chosen = (array) ($attributeFilters[$facet['code']] ?? []))
                    <div wire:key="facet-{{ $facet['code'] }}" x-data="{ open: true, more: false }" class="{{ $section }}">
                        <button type="button" x-on:click="open = ! open" class="{{ $sectionButton }}">
                            <span class="text-sm font-extrabold">{{ $facet['name'] }}</span>
                            <span class="flex items-center gap-2">
                                @if ($chosen)
                                    <span class="grid h-5 min-w-5 place-items-center rounded-full bg-brand px-1.5 text-[11px] font-bold text-white">{{ count($chosen) }}</span>
                                @endif
                                <span x-bind:class="open && '[&>svg]:rotate-180'">{!! $chevron !!}</span>
                            </span>
                        </button>
                        <div x-show="open" class="flex flex-col gap-0.5 px-0.5 pb-5">
                            @foreach ($facet['values'] as $index => $value)
                                @php($isOn = in_array($value['code'], $chosen, true))
                                <button
                                    type="button"
                                    wire:key="facet-{{ $facet['code'] }}-{{ $value['code'] }}"
                                    wire:click="toggleAttribute(@js($facet['code']), @js($value['code']))"
                                    @if ($index >= 6 && ! $isOn) x-show="more" x-cloak @endif
                                    role="checkbox"
                                    aria-checked="{{ $isOn ? 'true' : 'false' }}"
                                    class="flex min-h-10 items-center gap-3 rounded-[10px] px-2 text-left hover:bg-[#F6F9FF]"
                                >
                                    <span @class(['grid size-[18px] shrink-0 place-items-center rounded-[5px] border-[1.5px] transition-colors', 'border-brand bg-brand' => $isOn, 'border-[#C9D3E3] bg-white' => ! $isOn])>
                                        <svg @class(['transition', 'opacity-100' => $isOn, 'opacity-0' => ! $isOn]) width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
                                    </span>
                                    @if ($facet['type'] === 'color' && $value['hex'])
                                        <span class="size-4 shrink-0 rounded-full border border-black/10" style="background: {{ $value['hex'] }}"></span>
                                    @endif
                                    <span class="min-w-0 flex-1 truncate text-[13px] font-semibold text-navy">{{ $value['label'] }}{{ $facet['unit'] && ! str_contains($value['label'], $facet['unit']) ? ' '.$facet['unit'] : '' }}</span>
                                    <span class="text-[11px] font-semibold text-subtle">{{ $value['count'] }}</span>
                                </button>
                            @endforeach
                            @if ($facet['values']->count() > 6)
                                <button type="button" x-on:click="more = ! more" class="mt-2 flex h-[38px] w-full items-center justify-center gap-1.5 rounded-[10px] bg-[#F3F5F9] text-xs font-bold text-navy hover:bg-tint hover:text-brand">
                                    <span x-show="! more">{{ __('catalog.more_values', ['count' => $facet['values']->count() - 6]) }}</span>
                                    <span x-show="more" x-cloak>{{ __('catalog.less_brands') }}</span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if ($this->brandFacets->isNotEmpty())
                    <div x-data="{ open: true, more: false }" class="{{ $section }}">
                        <button type="button" x-on:click="open = ! open" class="{{ $sectionButton }}">
                            <span class="text-sm font-extrabold">{{ __('catalog.brand') }}</span>
                            <span class="flex items-center gap-2">
                                @if ($brands)
                                    <span class="max-w-[120px] truncate text-xs font-bold text-brand">{{ implode(', ', $brands) }}</span>
                                @endif
                                <span x-bind:class="open && '[&>svg]:rotate-180'">{!! $chevron !!}</span>
                            </span>
                        </button>
                        <div x-show="open" class="px-0.5 pb-5">
                            <div class="grid grid-cols-2 gap-2">
                                @foreach ($this->brandFacets as $index => $facet)
                                    @php($isOn = in_array($facet['name'], $brands, true))
                                    <button
                                        type="button"
                                        wire:key="brand-{{ $facet['name'] }}"
                                        wire:click="toggleBrand(@js($facet['name']))"
                                        @if ($index >= 6 && ! $isOn) x-show="more" x-cloak @endif
                                        aria-pressed="{{ $isOn ? 'true' : 'false' }}"
                                        @class(['relative flex flex-col items-start gap-0.5 rounded-xl border-[1.5px] px-3 py-2.5 text-left transition-colors hover:border-brand', 'border-brand bg-[#F6F9FF]' => $isOn, 'border-line bg-white' => ! $isOn])
                                    >
                                        <span class="font-display text-[13px] font-extrabold tracking-[-0.2px] text-navy">{{ $facet['name'] }}</span>
                                        <span class="text-[11px] font-semibold text-subtle">{{ $facet['count'] }}</span>
                                        <span @class(['absolute top-2 right-2 grid size-4 place-items-center rounded-full bg-brand transition', 'scale-100 opacity-100' => $isOn, 'scale-50 opacity-0' => ! $isOn])>
                                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                            @if ($this->brandFacets->count() > 6)
                                <button type="button" x-on:click="more = ! more" class="mt-2.5 flex h-[38px] w-full items-center justify-center gap-1.5 rounded-[10px] bg-[#F3F5F9] text-xs font-bold text-navy hover:bg-tint hover:text-brand">
                                    <span x-show="! more">{{ __('catalog.more_brands', ['count' => $this->brandFacets->count() - 6]) }}</span>
                                    <span x-show="more" x-cloak>{{ __('catalog.less_brands') }}</span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endif

                <div x-data="{ open: true }" class="{{ $section }}">
                    <button type="button" x-on:click="open = ! open" class="{{ $sectionButton }}">
                        <span class="text-sm font-extrabold">{{ __('catalog.availability') }}</span>
                        <span x-bind:class="open && '[&>svg]:rotate-180'">{!! $chevron !!}</span>
                    </button>
                    <div x-show="open" class="flex flex-col gap-1 px-0.5 pb-5">
                        @foreach ($this::FLAGS as $flag)
                            @php($isOn = in_array($flag, $flags, true))
                            <button type="button" wire:click="toggleFlag('{{ $flag }}')" role="switch" aria-checked="{{ $isOn ? 'true' : 'false' }}" class="flex items-center justify-between gap-3 bg-transparent px-0.5 py-[9px] text-left text-navy">
                                <span class="flex flex-col gap-px">
                                    <span class="text-[13px] font-semibold">{{ __("catalog.flags.$flag") }}</span>
                                    <span class="text-[11px] text-subtle">{{ trans_choice('catalog.products_count', $this->flagCounts[$flag], ['count' => $this->flagCounts[$flag]]) }}</span>
                                </span>
                                <span @class(['relative h-6 w-10 shrink-0 rounded-xl transition-colors', 'bg-brand' => $isOn, 'bg-[#D5DEEC]' => ! $isOn])>
                                    <span @class(['absolute top-[3px] left-[3px] size-[18px] rounded-full bg-white shadow-[0_1px_3px_rgba(10,22,51,.25)] transition-transform', 'translate-x-4' => $isOn])></span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="{{ $section }}"></div>
            </div>

            <button type="button" x-on:click="filtersOpen = false" class="fixed right-0 bottom-0 left-0 z-[62] m-4 h-12 w-[calc(min(340px,92vw)-32px)] rounded-full bg-brand text-sm font-bold text-white lg:hidden">{{ __('catalog.show_results', ['count' => $total]) }}</button>
        </aside>

        <div class="flex min-w-0 flex-col gap-5">
            @if ($total === 0)
                <div class="flex flex-col items-center gap-2.5 rounded-3xl bg-field px-6 py-[72px] text-center">
                    <span class="mb-1 grid size-16 place-items-center rounded-full bg-white text-brand"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5M8 11h6"/></svg></span>
                    @if ($term !== '' && ! $hasActive)
                        <span class="text-lg font-extrabold text-balance">{{ __('catalog.search.empty_title', ['term' => $term]) }}</span>
                        <span class="text-sm text-muted">{{ __('catalog.search.empty_text') }}</span>
                        <button type="button" wire:click="clearSearch" class="mt-2 h-11 rounded-full bg-navy px-[22px] text-sm font-bold text-white hover:bg-brand">{{ __('catalog.search.clear') }}</button>
                    @else
                        <span class="text-lg font-extrabold">{{ __('catalog.empty_title') }}</span>
                        <span class="text-sm text-muted">{{ __('catalog.empty_text') }}</span>
                        @if ($hasActive)
                            <button type="button" wire:click="clearFilters" class="mt-2 h-11 rounded-full bg-navy px-[22px] text-sm font-bold text-white hover:bg-brand">{{ __('catalog.clear_filters') }}</button>
                        @endif
                    @endif
                </div>
            @else
                <div wire:loading.class="opacity-60" wire:target="toggleBrand,toggleFlag,toggleAttribute,setSort,clearFilters,clearPrice,minPrice,maxPrice,search,clearSearch" class="grid grid-cols-2 gap-2.5 transition-opacity sm:grid-cols-3 sm:gap-[18px] xl:grid-cols-4">
                    @foreach ($shown as $index => $product)
                        <div wire:key="category-product-{{ $product['id'] }}" class="min-w-0">
                            @include('partials.product-card', ['product' => $product])
                        </div>
                    @endforeach
                </div>

                @if ($shown->count() < $total)
                    <div class="mt-5 flex flex-col items-stretch gap-4 rounded-[20px] bg-field px-6 py-5 sm:flex-row sm:items-center sm:gap-5">
                        <div class="flex flex-1 flex-col gap-2">
                            <span class="text-[13px] text-muted">{{ __('catalog.shown', ['shown' => $shown->count(), 'total' => $total]) }}</span>
                            <div class="h-1 overflow-hidden rounded-sm bg-[#E3E9F3]"><div class="h-full rounded-sm bg-linear-to-r from-brand to-sky transition-[width] duration-400" style="width: {{ round($shown->count() / $total * 100) }}%"></div></div>
                        </div>
                        <button type="button" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore" class="flex h-12 items-center justify-center gap-2 rounded-full bg-navy px-[26px] text-sm font-bold text-white hover:bg-brand disabled:opacity-60">
                            {{ __('catalog.load_more') }}
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                    </div>
                @endif
            @endif
        </div>
    </div>
    @endif
</main>
