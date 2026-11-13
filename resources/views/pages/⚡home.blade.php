<?php

use App\Models\Slide;
use App\Support\Catalog;
use App\Support\HomeContent;
use App\Support\Seo;
use App\Support\Site;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        Seo::set([
            'title' => __('seo.home_title', ['site' => Site::name()]),
            'schema' => [Seo::website()],
        ]);

        return $this->view([
            'slides' => Slide::forHero(),
            'blocks' => HomeContent::blocks(),
            'perks' => HomeContent::perks(),
            'promos' => HomeContent::promos(),
            'deal' => Catalog::deal(),
            'brands' => Catalog::brands(),
            'saleProducts' => Catalog::onSale(),
            'sections' => Catalog::sections(),
        ])->title(__('shop.nav.home'));
    }
};
?>

<div>
    <h1 class="sr-only" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0">{{ __('seo.home_title', ['site' => App\Support\Site::name()]) }}</h1>

    @include('partials.home.hero')

    <main class="mx-auto max-w-[1480px] px-4 sm:px-6 lg:px-8">
        @foreach ($blocks as $block)
            @switch($block['key'])
                @case('perks')
                    @if ($perks->isNotEmpty())
                        @include('partials.home.perks')
                    @endif
                    @break
                @case('sale')
                    @if ($saleProducts->isNotEmpty())
                        @include('partials.home.sale')
                    @endif
                    @break
                @case('deal')
                    @if ($deal)
                        @include('partials.home.deal')
                    @endif
                    @break
                @case('categories')
                    @foreach ($sections as $section)
                        @include('partials.home.category-section', ['section' => $section, 'loop' => $loop])
                    @endforeach
                    @break
                @case('promos')
                    @if ($promos->isNotEmpty())
                        @include('partials.home.promo-banners')
                    @endif
                    @break
                @case('brands')
                    @if ($brands->isNotEmpty())
                        @include('partials.home.brands')
                    @endif
                    @break
            @endswitch
        @endforeach
    </main>
</div>
