<?php

use App\Models\ProductSpec;
use App\Support\Catalog;
use App\Services\Facebook\Pixel;
use App\Support\CategoryTree;
use Livewire\Component;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

new class extends Component
{
    public int $productId = 0;

    public function mount(string $slug): void
    {
        $product = Catalog::findBySlug($slug);

        if (! $product) {
            $product = Catalog::findBySlugInAnyLocale($slug);

            abort_unless($product, 404);

            $this->redirect($product['url'], navigate: true);

            return;
        }

        $this->productId = $product['id'];

        $this->dispatch('pixel', app(Pixel::class)->viewContent([
            'id' => $product['id'],
            'brand' => $product['brand'],
            'name' => $product['name'],
            'price' => (float) $product['price'],
        ]));
    }

    public function render()
    {
        $product = Catalog::find($this->productId);
        $category = $product ? CategoryTree::find($product['categoryId']) : null;

        view()->share('alternateUrls', collect($product['slugs'] ?? [])
            ->mapWithKeys(fn (string $slug, string $locale) => [
                $locale => LaravelLocalization::getLocalizedURL($locale, '/product/'.$slug),
            ])
            ->filter()
            ->all());

        return $this->view([
            'product' => $product,
            'breadcrumbs' => $category ? CategoryTree::ancestors($category['id'])->push($category) : collect(),
            'specs' => $product
                ? ProductSpec::query()->where('product_id', $product['id'])->with(['translations', 'attribute.translations'])->orderBy('position')->get()
                    ->map(fn (ProductSpec $spec) => ['name' => (string) $spec->attribute?->name, 'value' => (string) $spec->value])
                    ->filter(fn (array $spec) => $spec['name'] !== '' && $spec['value'] !== '')
                    ->values()
                : collect(),
            'similar' => $product ? Catalog::similar($product) : collect(),
            'variants' => $product ? Catalog::variantSwitchers($product) : [],
        ])->title($product['name'] ?? '');
    }
};
?>

@php
    $perkIcons = [
        'delivery' => '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
        'regions' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'warranty' => '<path d="M12 3 4 6v6c0 4.5 3.4 8 8 9 4.6-1 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
    ];

    $imageCount = max(count($product['images']), 1);

    $alpineConfig = [
        'id' => $product['id'],
        'basePrice' => $product['price'],
        'baseOld' => $product['old'],
        'colors' => $product['colors'],
        'storages' => $product['storages'],
        'color' => $product['defaultColor'],
        'storage' => $product['defaultStorage'],
        'images' => $imageCount,
        'selectedLabel' => __('product.selected'),
    ];

    $iconSvg = fn (string $paths, int $size = 18, float $stroke = 2) => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'.$stroke.'" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
@endphp

<main class="mx-auto max-w-[1480px] px-4 pt-[22px] pb-16 sm:px-6 lg:px-8" x-data="productPage(@js($alpineConfig))">
    <nav aria-label="breadcrumb" class="flex flex-wrap items-center gap-2 text-xs text-subtle">
        <a href="{{ route('home') }}" wire:navigate class="text-muted hover:text-brand">{{ __('account.home') }}</a>
        @foreach ($breadcrumbs as $crumb)
            <span class="size-1 rounded-full bg-[#C9D3E3]"></span>
            <a href="{{ App\Support\CategoryTree::url($crumb) }}" wire:navigate class="text-muted hover:text-brand">{{ $crumb['title'] }}</a>
        @endforeach
        <span class="size-1 rounded-full bg-[#C9D3E3]"></span>
        <span class="font-semibold text-navy">{{ $product['name'] }}</span>
    </nav>

    <section class="mt-[22px] grid items-start gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] lg:gap-10">
        <div class="flex flex-col gap-3.5 lg:sticky lg:top-24">
            <div class="relative aspect-square overflow-hidden rounded-[24px] bg-[radial-gradient(120%_90%_at_50%_35%,#FFFFFF_0%,#F1F5FB_55%,#E6EDF8_100%)] sm:rounded-[32px]">
                <div class="pointer-events-none absolute inset-x-[18%] bottom-[9%] h-[7%] rounded-[50%] bg-[radial-gradient(closest-side,rgba(10,22,51,.16),transparent)]"></div>

                @forelse ($product['images'] as $index => $image)
                    <img
                        x-show="image === {{ $index }}"
                        @if ($index > 0) x-cloak loading="lazy" @endif
                        src="{{ $image }}"
                        alt="{{ $product['name'] }} — {{ __('product.photo', ['number' => $index + 1]) }}"
                        class="absolute top-[7%] left-[9%] h-[80%] w-[82%] animate-fade object-contain"
                    >
                @empty
                    <div class="absolute inset-[7%_9%_13%] grid place-items-center">
                        <span class="font-display text-[clamp(120px,22vw,260px)] leading-none font-extrabold text-brand/10 select-none">{{ mb_substr($product['brand'], 0, 1) }}</span>
                    </div>
                @endforelse

                <div class="pointer-events-none absolute top-[22px] left-6 flex items-center gap-2.5">
                    <span class="font-display text-[13px] font-extrabold tracking-[0.5px] text-navy" x-text="`${image + 1} / ${images}`">1 / {{ $imageCount }}</span>
                    @if ($product['badge'])
                        <span @class(['h-[26px] rounded-full px-2.5 font-display text-xs leading-[26px] font-extrabold text-white', 'bg-brand' => $product['isNew'], 'bg-sale' => ! $product['isNew']])
                            @if ($product['old']) x-text="unitOld ? '−' + Math.round((unitOld - unitPrice) / unitOld * 100) + '%' : ''" @endif
                        >{{ $product['badge'] }}</span>
                    @endif
                </div>

                <div class="absolute top-4 right-4 flex gap-2">
                    <button
                        type="button"
                        x-on:click="$store.favorites.toggle(id, @js(__('shop.favorites_added')), @js(__('shop.favorites_removed')))"
                        aria-label="{{ __('shop.to_favorites') }}"
                        class="grid size-11 place-items-center rounded-full border border-white/80 bg-white/70 backdrop-blur-md hover:bg-white"
                        :class="$store.favorites.has(id) ? 'text-sale' : 'text-navy'"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" :fill="$store.favorites.has(id) ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8z"/></svg>
                    </button>
                    <button
                        type="button"
                        x-on:click="navigator.clipboard && navigator.clipboard.writeText(window.location.href); $store.toast.show(@js(__('product.link_copied')), false, 2000)"
                        aria-label="{{ __('product.share') }}"
                        class="grid size-11 place-items-center rounded-full border border-white/80 bg-white/70 text-navy backdrop-blur-md hover:bg-white hover:text-brand"
                    >
                        {!! $iconSvg('<circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="m8.2 10.8 7.6-4.4M8.2 13.2l7.6 4.4"/>', 17) !!}
                    </button>
                </div>

                <div class="absolute inset-x-0 bottom-[22px] flex items-center justify-center gap-3.5">
                    <button type="button" x-on:click="stepImage(-1)" aria-label="{{ __('shop.prev') }}" class="grid size-10 place-items-center rounded-full bg-navy text-white hover:bg-brand">
                        {!! $iconSvg('<path d="m15 6-6 6 6 6"/>', 15, 2.4) !!}
                    </button>
                    <div class="flex gap-1.5 px-1">
                        @for ($index = 0; $index < $imageCount; $index++)
                            <button type="button" x-on:click="image = {{ $index }}" aria-label="{{ __('product.photo', ['number' => $index + 1]) }}" class="h-1.5 rounded-[3px] transition-all duration-300" :class="image === {{ $index }} ? 'w-[26px] bg-navy' : 'w-1.5 bg-navy/20'"></button>
                        @endfor
                    </div>
                    <button type="button" x-on:click="stepImage(1)" aria-label="{{ __('shop.next') }}" class="grid size-10 place-items-center rounded-full bg-navy text-white hover:bg-brand">
                        {!! $iconSvg('<path d="m9 6 6 6-6 6"/>', 15, 2.4) !!}
                    </button>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-6 rounded-[28px] border border-line-mid bg-white p-5 shadow-[0_30px_60px_-40px_rgba(10,22,51,.25)] sm:p-7">
                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-center justify-between gap-2.5">
                        <span class="font-display text-[15px] font-extrabold tracking-[-0.2px]">{{ $product['brand'] }}</span>
                        <span class="flex flex-wrap items-center gap-3.5 text-xs text-subtle">
                            @if ($product['inStock'])
                                <span class="flex items-center gap-1.5 font-bold text-ok"><span class="size-2 rounded-full bg-[#1FB866] shadow-[0_0_0_4px_rgba(31,184,102,.15)]"></span>{{ __('product.in_stock') }}</span>
                            @else
                                <span class="flex items-center gap-1.5 font-bold text-sale"><span class="size-2 rounded-full bg-sale"></span>{{ __('product.out_of_stock') }}</span>
                            @endif
                            <span>{{ __('product.id', ['id' => $product['id']]) }}</span>
                            @if (auth()->user()?->is_admin)
                                <a href="{{ App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $product['id']], panel: 'admin') }}" title="{{ __('product.admin_only') }}" class="flex items-center gap-1 rounded-md bg-[#FFF6E5] px-2 py-0.5 font-semibold text-[#B36B00] hover:text-navy">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                                    {{ __('product.sku', ['sku' => strtoupper($product['sku'])]) }}@if ($product['supplierSku']) · {{ __('product.supplier_sku', ['sku' => $product['supplierSku']]) }}@endif
                                </a>
                            @endif
                        </span>
                    </div>
                    <h1 class="m-0 text-[clamp(26px,2.4vw,34px)] leading-[1.18] font-extrabold tracking-[-0.2px] text-balance">{{ $product['name'] }}</h1>
                    @if ($product['colors'] || $product['storages'])
                        <span class="text-sm text-muted" x-text="variantLabel"></span>
                    @elseif ($product['subtitle'])
                        <span class="text-sm text-muted">{{ $product['subtitle'] }}</span>
                    @endif
                </div>

                <div class="flex flex-col gap-1.5 border-y border-line-soft py-[18px]">
                    <div class="flex flex-wrap items-baseline gap-3">
                        <span class="font-display text-[clamp(34px,3.4vw,42px)] leading-none font-extrabold tracking-[-1.4px]" x-text="money(total)">{{ $product['priceLabel'] }}</span>
                        <span x-show="unitOld" @if (! $product['old']) x-cloak @endif class="flex items-baseline gap-3">
                            <span class="text-[15px] text-subtle line-through" x-text="unitOld ? money(unitOld * qty) : ''">{{ $product['oldLabel'] }}</span>
                            <span class="h-6 rounded-full bg-[#FDECEC] px-2.5 text-xs leading-6 font-bold text-sale" x-text="unitOld ? '−' + money((unitOld - unitPrice) * qty) : ''">@if ($product['saveLabel'])−{{ $product['saveLabel'] }}@endif</span>
                        </span>
                    </div>
                    <span class="text-[13px] text-muted" x-html="@js(__('product.installment', ['amount' => '__A__'])).replace('__A__', '<span class=&quot;font-display font-extrabold text-brand&quot;>' + money(Math.ceil(total / 24)) + '</span>')">{{ __('product.installment', ['amount' => $product['monthlyLabel']]) }}</span>
                </div>

                @foreach ($variants as $switcher)
                    @php($chosen = collect($switcher['options'])->firstWhere('current', true))
                    <div wire:key="variant-{{ $switcher['code'] }}" class="flex flex-col gap-3">
                        <span class="text-xs font-bold tracking-[0.4px] text-subtle uppercase">
                            {{ $switcher['name'] }}@if ($chosen): <span class="tracking-normal text-navy normal-case">{{ $chosen['label'] }}</span>@endif
                        </span>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($switcher['options'] as $option)
                                @php($optionClass = 'flex min-h-[46px] items-center gap-2.5 rounded-[14px] border-[1.5px] px-3.5 text-left font-ui text-[13px] font-bold text-navy transition')
                                @if ($option['current'])
                                    <span aria-current="true" class="{{ $optionClass }} border-brand bg-[#F2F7FF]">
                                        @if ($switcher['colour'])
                                            <span class="size-6 shrink-0 rounded-full shadow-[inset_0_0_0_1px_rgba(10,22,51,.14),0_2px_6px_rgba(10,22,51,.12)]" style="background: {{ $option['hex'] ?? '#E3E9F3' }}"></span>
                                        @endif
                                        {{ $option['label'] }}
                                    </span>
                                @else
                                    <a href="{{ $option['url'] }}" wire:navigate @class([$optionClass, 'border-line-mid bg-white hover:border-brand hover:text-navy', 'opacity-55' => ! $option['inStock']]) title="{{ $option['inStock'] ? $option['label'] : __('product.out_of_stock') }}">
                                        @if ($switcher['colour'])
                                            <span class="size-6 shrink-0 rounded-full shadow-[inset_0_0_0_1px_rgba(10,22,51,.14),0_2px_6px_rgba(10,22,51,.12)]" style="background: {{ $option['hex'] ?? '#E3E9F3' }}"></span>
                                        @endif
                                        <span @class(['line-through decoration-1' => ! $option['inStock']])>{{ $option['label'] }}</span>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if ($product['colors'])
                    <div class="flex flex-col gap-3">
                        <span class="text-xs font-bold tracking-[0.4px] text-subtle uppercase">{{ __('product.color') }}</span>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($product['colors'] as $index => $color)
                                <button
                                    type="button"
                                    x-on:click="color = {{ $index }}"
                                    class="flex h-[50px] items-center gap-2.5 rounded-[14px] border-[1.5px] px-3 text-left transition hover:border-brand"
                                    :class="color === {{ $index }} ? 'border-brand bg-[#F2F7FF]' : 'border-line-mid bg-white'"
                                    :aria-pressed="color === {{ $index }}"
                                >
                                    <span class="size-6 shrink-0 rounded-full shadow-[inset_0_0_0_1px_rgba(10,22,51,.14),0_2px_6px_rgba(10,22,51,.12)]" style="background: {{ $color['hex'] ?? '#E3E9F3' }}"></span>
                                    <span class="truncate font-ui text-[13px] font-bold text-navy">{{ str_replace(' Titanium', '', $color['label']) }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($product['storages'])
                    <div class="flex flex-col gap-3">
                        <span class="text-xs font-bold tracking-[0.4px] text-subtle uppercase">{{ __('product.storage') }}</span>
                        <div class="grid gap-1 rounded-2xl bg-soft p-1" style="grid-template-columns: repeat({{ min(count($product['storages']), 4) }}, minmax(0, 1fr))">
                            @foreach ($product['storages'] as $index => $storageOption)
                                <button
                                    type="button"
                                    x-on:click="storage = {{ $index }}"
                                    class="flex flex-col items-center gap-0.5 rounded-xl px-1 py-2.5 transition"
                                    :class="storage === {{ $index }} ? 'bg-white shadow-[0_2px_8px_rgba(10,22,51,.12)]' : 'bg-transparent'"
                                    :aria-pressed="storage === {{ $index }}"
                                >
                                    <span class="font-display text-sm font-extrabold text-navy">{{ $storageOption['label'] }}</span>
                                    <span class="font-ui text-[11px] font-bold" :class="storage === {{ $index }} ? 'text-brand' : 'text-subtle'" x-text="storageDiff(storages[{{ $index }}])"></span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($product['inStock'])
                    <div class="flex gap-2.5" x-ref="mainBuy">
                        <div class="flex h-[58px] items-center rounded-2xl bg-soft">
                            <button type="button" x-on:click="qty = Math.max(1, qty - 1)" aria-label="{{ __('product.less') }}" class="h-full w-11 text-xl text-navy">−</button>
                            <span class="min-w-6 text-center font-display text-base font-extrabold" x-text="qty">1</span>
                            <button type="button" x-on:click="qty = Math.min({{ min($product['stock'], 10) }}, qty + 1)" aria-label="{{ __('product.more') }}" class="h-full w-11 text-xl text-navy">+</button>
                        </div>
                        <button
                            type="button"
                            x-on:click="add()"
                            class="flex h-[58px] flex-1 items-center justify-center gap-2.5 rounded-2xl bg-brand px-3 text-[15px] font-extrabold text-white shadow-[0_18px_36px_-16px_rgba(11,99,246,.8)] hover:bg-brand-dark"
                        >
                            {!! $iconSvg('<path d="M6 7h12l-1 13H7z"/><path d="M9 7a3 3 0 0 1 6 0"/>', 19) !!}
                            <span x-text="$store.cart.qty(id) ? @js(__('product.add_more', ['count' => '__N__'])).replace('__N__', $store.cart.qty(id)) : @js(__('product.add_to_cart'))">{{ __('product.add_to_cart') }}</span>
                        </button>
                    </div>
                @else
                    <button type="button" disabled class="h-[58px] rounded-2xl bg-soft text-[15px] font-extrabold text-subtle">{{ __('product.unavailable') }}</button>
                @endif
            </div>

            @if ($product['inStock'])
                <livewire:quick-order :product-id="$product['id']" :wire:key="'quick-order-'.$product['id']" />
            @endif
        </div>
    </section>

    <section class="mt-9 grid grid-cols-1 overflow-hidden rounded-3xl border border-line-mid sm:grid-cols-3">
        @foreach ($perkIcons as $perk => $icon)
            <div class="flex items-center gap-3.5 border-line-mid px-6 py-5 not-last:border-b sm:not-last:border-r sm:not-last:border-b-0">
                <span class="grid size-11 shrink-0 place-items-center rounded-full border-[1.5px] border-[#D5E2F7] text-brand">{!! $iconSvg($icon, 19, 1.8) !!}</span>
                <span class="flex min-w-0 flex-col gap-[3px]">
                    <span class="text-sm font-bold">{{ __("product.perks.$perk.title") }}</span>
                    <span class="text-xs leading-[1.45] text-muted">{{ __("product.perks.$perk.text") }}</span>
                </span>
            </div>
        @endforeach
    </section>

    @php($hasSpecs = $specs->isNotEmpty() || $product['colors'] || $product['storages'])
    @php($hasDescription = filled($product['description']))
    @php($firstTab = $hasSpecs ? 'specs' : 'desc')
    @php($tabClass = 'flex h-[42px] items-center rounded-full px-[22px] text-sm font-bold transition')

    @if ($hasSpecs || $hasDescription)
        <div x-data="{ tab: '{{ $firstTab }}', show(name) { this.tab = name; if (this.$refs.panels.getBoundingClientRect().top < 0) { this.$refs.panels.scrollIntoView({ behavior: 'smooth', block: 'start' }) } } }" class="mt-14">
            @if ($hasSpecs && $hasDescription)
                <div class="pointer-events-none sticky top-[84px] z-[14] flex justify-center">
                    <div role="tablist" class="pointer-events-auto flex gap-1 rounded-full bg-soft/90 p-[5px] shadow-[0_10px_30px_-18px_rgba(10,22,51,.35)] backdrop-blur-md">
                        <button type="button" role="tab" id="tab-specs" aria-controls="specs" x-on:click="show('specs')" :aria-selected="tab === 'specs'" class="{{ $tabClass }}" :class="tab === 'specs' ? 'bg-navy text-white shadow-[0_4px_12px_-4px_rgba(10,22,51,.4)]' : 'text-muted hover:text-navy'">{{ __('product.tabs.specs') }}</button>
                        <button type="button" role="tab" id="tab-desc" aria-controls="desc" x-on:click="show('desc')" :aria-selected="tab === 'desc'" class="{{ $tabClass }}" :class="tab === 'desc' ? 'bg-navy text-white shadow-[0_4px_12px_-4px_rgba(10,22,51,.4)]' : 'text-muted hover:text-navy'">{{ __('product.tabs.description') }}</button>
                    </div>
                </div>
            @endif

            <div x-ref="panels" class="scroll-mt-[150px]">
                @if ($hasSpecs)
                    <section id="specs" role="tabpanel" aria-labelledby="tab-specs" x-show="tab === 'specs'" @if ($firstTab !== 'specs') x-cloak @endif class="mt-9 grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)] lg:gap-10">
                        <div class="flex flex-col gap-2.5">
                            <h2 class="m-0 text-2xl leading-[1.25] font-extrabold tracking-[0.3px]">{{ __('product.tabs.specs') }}</h2>
                            <span class="text-[13px] leading-[1.55] text-muted">{{ __('product.specs_text') }}</span>
                        </div>
                        <dl class="m-0 grid border-t-2 border-navy md:grid-cols-[repeat(auto-fit,minmax(min(100%,340px),1fr))] md:gap-x-10">
                            @if ($product['storages'])
                                <div class="flex items-center justify-between gap-6 border-b border-line-soft py-[15px] text-[13px]">
                                    <dt class="text-subtle">{{ __('product.storage') }}</dt>
                                    <dd class="m-0 text-right font-bold text-navy" x-text="storageOption?.label"></dd>
                                </div>
                            @endif
                            @foreach ($specs as $spec)
                                <div class="flex items-center justify-between gap-6 border-b border-line-soft py-[15px] text-[13px]">
                                    <dt class="text-subtle">{{ $spec['name'] }}</dt>
                                    <dd class="m-0 text-right font-bold text-navy">{{ $spec['value'] }}</dd>
                                </div>
                            @endforeach
                            @if ($product['colors'])
                                <div class="flex items-center justify-between gap-6 border-b border-line-soft py-[15px] text-[13px]">
                                    <dt class="text-subtle">{{ __('product.color') }}</dt>
                                    <dd class="m-0 text-right font-bold text-navy" x-text="colorOption?.label"></dd>
                                </div>
                            @endif
                        </dl>
                    </section>
                @endif

                @if ($hasDescription)
                    <section id="desc" role="tabpanel" aria-labelledby="tab-desc" x-show="tab === 'desc'" @if ($firstTab !== 'desc') x-cloak @endif class="mt-9 grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)] lg:gap-10">
                        <h2 class="m-0 text-2xl font-extrabold tracking-[0.3px]">{{ __('product.tabs.description') }}</h2>
                        <div class="max-w-[900px] border-t-2 border-navy pt-[18px] text-base leading-[1.7] font-medium text-pretty text-navy sm:text-lg [&_h2]:mt-5 [&_h2]:mb-2 [&_h2]:text-xl [&_h2]:font-extrabold [&_h3]:mt-5 [&_h3]:mb-2 [&_h3]:text-lg [&_h3]:font-extrabold [&_h4]:mt-4 [&_h4]:mb-1.5 [&_h4]:font-extrabold [&_li]:mb-1 [&_ol]:mb-3 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-3 [&_strong]:font-extrabold [&_table]:mb-3 [&_table]:w-full [&_table]:text-sm [&_td]:border-b [&_td]:border-line [&_td]:py-2 [&_td]:pr-3 [&_th]:border-b [&_th]:border-line [&_th]:py-2 [&_th]:pr-3 [&_th]:text-left [&_ul]:mb-3 [&_ul]:list-disc [&_ul]:pl-5">{!! App\Support\Html::clean($product['description']) !!}</div>
                    </section>
                @endif
            </div>
        </div>
    @endif

    @if ($similar->isNotEmpty())
        <section x-data="carousel" class="mt-14 lg:mt-[72px]">
            <div class="mb-5 flex items-center justify-between gap-4">
                <h2 class="m-0 text-2xl font-extrabold tracking-[0.3px]">{{ __('product.similar') }}</h2>
                <div class="flex gap-1.5">
                    <button type="button" x-on:click="scroll(-1)" aria-label="{{ __('shop.prev') }}" class="grid size-10 place-items-center rounded-full bg-soft text-navy hover:bg-brand hover:text-white">{!! $iconSvg('<path d="m15 6-6 6 6 6"/>', 15, 2.4) !!}</button>
                    <button type="button" x-on:click="scroll(1)" aria-label="{{ __('shop.next') }}" class="grid size-10 place-items-center rounded-full bg-soft text-navy hover:bg-brand hover:text-white">{!! $iconSvg('<path d="m9 6 6 6-6 6"/>', 15, 2.4) !!}</button>
                </div>
            </div>
            <div x-ref="track" class="carousel auto-cols-[calc((100%-10px)/2)] gap-2.5 sm:auto-cols-[calc((100%-40px)/3)] sm:gap-5 lg:auto-cols-[calc((100%-60px)/4)] xl:auto-cols-[calc((100%-80px)/5)]">
                @foreach ($similar as $item)
                    <div wire:key="similar-{{ $item['id'] }}">
                        @include('partials.product-card', ['product' => $item])
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($product['inStock'])
        <div
            x-show="showBar"
            x-cloak
            x-transition.opacity
            class="fixed bottom-[calc(84px+env(safe-area-inset-bottom))] left-1/2 z-[44] flex w-[min(880px,calc(100vw-32px))] -translate-x-1/2 items-center gap-3.5 rounded-[20px] bg-navy/96 py-2.5 pr-2.5 pl-3.5 text-white shadow-[0_24px_50px_-16px_rgba(10,22,51,.6)] backdrop-blur-md sm:bottom-5"
        >
            <span class="hidden size-[46px] shrink-0 place-items-center rounded-xl bg-white/8 text-sky sm:grid">
                @include('partials.category-icon', ['icon' => $breadcrumbs->first()['icon'] ?? null, 'class' => 'size-5', 'stroke' => 1.9])
            </span>
            <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                <span class="truncate text-sm font-bold">{{ $product['name'] }}</span>
                <span class="truncate text-xs text-faint" x-text="variantLabel"></span>
            </span>
            <span class="font-display text-lg font-extrabold whitespace-nowrap sm:text-xl" x-text="money(total)">{{ $product['priceLabel'] }}</span>
            <button type="button" x-on:click="add()" class="h-[46px] rounded-[14px] bg-brand px-4 text-sm font-bold whitespace-nowrap text-white hover:bg-brand-bright sm:px-[22px]">{{ __('product.to_cart') }}</button>
        </div>
    @endif
</main>
