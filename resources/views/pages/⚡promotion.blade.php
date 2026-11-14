<?php

use App\Models\Campaign;
use App\Support\Seo;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    public const SORTS = ['popular', 'discount', 'price_asc', 'price_desc'];

    #[Locked]
    public string $slug = '';

    #[Url(except: 'popular')]
    public string $sort = 'popular';

    public function mount(string $slug): void
    {
        abort_unless(Campaign::findLive($slug), 404);

        $this->slug = $slug;
    }

    public function setSort(string $sort): void
    {
        $this->sort = in_array($sort, self::SORTS, true) ? $sort : 'popular';
    }

    public function render()
    {
        $campaign = Campaign::findLive($this->slug);

        abort_unless($campaign, 404);

        $products = $campaign['products'];
        $discount = fn (array $product) => $product['old'] ? ($product['old'] - $product['price']) / $product['old'] : 0;

        $products = match ($this->sort) {
            'discount' => $products->sortBy([['inStock', 'desc'], fn (array $a, array $b) => $discount($b) <=> $discount($a)]),
            'price_asc' => $products->sortBy([['inStock', 'desc'], ['price', 'asc']]),
            'price_desc' => $products->sortBy([['inStock', 'desc'], ['price', 'desc']]),
            default => $products->sortByDesc('inStock'),
        };

        Seo::set([
            'description' => Seo::text($campaign['text'], __('pages.campaign.seo', ['title' => $campaign['title'], 'count' => $campaign['count']])),
            'canonical' => $campaign['url'],
            'image' => $campaign['image'],
            'schema' => [Seo::breadcrumbs([
                ['name' => __('pages.promotions.title'), 'url' => route('promotions')],
                ['name' => $campaign['title'], 'url' => $campaign['url']],
            ])],
        ]);

        return $this->view([
            'campaign' => $campaign,
            'products' => $products->values(),
            'others' => Campaign::listing()->where('slug', '!=', $this->slug)->take(3)->values(),
        ])->title($campaign['title']);
    }

    public function validity(?string $endsAt): string
    {
        if (! $endsAt) {
            return __('pages.promotions.no_end');
        }

        $date = Carbon::parse($endsAt)->timezone(config('app.timezone'));

        return $date->isToday()
            ? __('pages.promotions.ends_today')
            : __('pages.promotions.until', ['date' => $date->format('d.m.Y')]);
    }
};
?>

@php
    $arrowIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>';
@endphp

<main class="mx-auto flex max-w-[1480px] flex-col gap-6 px-4 pt-6 pb-16 sm:px-6 lg:px-8">
    <nav aria-label="breadcrumb" class="flex flex-wrap items-center gap-2 text-[13px] text-muted">
        <a href="{{ route('home') }}" wire:navigate class="text-muted hover:text-brand">{{ __('account.home') }}</a>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
        <a href="{{ route('promotions') }}" wire:navigate class="text-muted hover:text-brand">{{ __('pages.promotions.title') }}</a>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
        <span class="font-semibold text-navy">{{ $campaign['title'] }}</span>
    </nav>

    @include('partials.campaign-hero', ['campaign' => $campaign, 'heading' => 'h1'])

    <section class="flex flex-col gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="m-0 text-xl font-extrabold tracking-[0.3px] sm:text-2xl">
                {{ trans_choice('pages.campaign.products', $campaign['count'], ['count' => $campaign['count']]) }}
            </h2>
            @if ($campaign['count'] > 1)
                <div class="flex gap-1 overflow-x-auto rounded-full bg-soft p-1">
                    @foreach ($this::SORTS as $option)
                        <button
                            type="button"
                            wire:click="setSort('{{ $option }}')"
                            @class([
                                'h-9 shrink-0 rounded-full px-4 text-[13px] font-bold whitespace-nowrap transition',
                                'bg-white text-navy shadow-[0_2px_8px_rgba(10,22,51,.12)]' => $sort === $option,
                                'text-muted hover:text-navy' => $sort !== $option,
                            ])
                        >{{ __("pages.campaign.sort.$option") }}</button>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($products->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-[28px] border border-line-soft bg-white px-6 py-16 text-center">
                <span class="grid size-16 place-items-center rounded-2xl bg-soft font-display text-3xl font-extrabold text-brand">%</span>
                <h3 class="m-0 text-lg font-extrabold">{{ __('pages.campaign.empty') }}</h3>
                <a href="{{ route('catalog') }}" wire:navigate class="mt-2 flex h-12 items-center rounded-full bg-brand px-6 text-sm font-bold text-white hover:bg-brand-dark hover:text-white">{{ __('pages.promotions.catalog') }}</a>
            </div>
        @else
            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5" wire:loading.class="opacity-60" wire:target="setSort">
                @foreach ($products as $item)
                    <div wire:key="campaign-product-{{ $item['id'] }}">
                        @include('partials.product-card', ['product' => $item])
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    @if ($others->isNotEmpty())
        <section class="mt-8 flex flex-col gap-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 class="m-0 text-xl font-extrabold tracking-[0.3px] sm:text-2xl">{{ __('pages.campaign.others') }}</h2>
                <a href="{{ route('promotions') }}" wire:navigate class="flex h-10 items-center gap-1.5 rounded-full bg-navy px-[18px] text-[13px] font-bold text-white hover:bg-brand hover:text-white">
                    {{ __('pages.campaign.all') }} {!! $arrowIcon !!}
                </a>
            </div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($others as $other)
                    <div wire:key="other-campaign-{{ $other['id'] }}">
                        @include('partials.campaign-card', ['campaign' => $other])
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</main>
