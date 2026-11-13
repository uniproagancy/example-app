<?php

use App\Models\Page;
use App\Support\Seo;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public string $slug = '';

    public function mount(string $slug): void
    {
        abort_unless(Page::query()->active()->where('slug', $slug)->exists(), 404);

        $this->slug = $slug;
    }

    public function render()
    {
        $page = Page::query()->active()->with('translations')->where('slug', $this->slug)->firstOrFail();

        Seo::set([
            'description' => Seo::text($page->meta_description, $page->intro, $page->body),
            'schema' => [Seo::breadcrumbs([['name' => (string) $page->title]])],
        ]);

        return $this->view([
            'page' => $page,
            'pages' => Page::menu(),
        ])->title((string) $page->title);
    }
};
?>

<main class="mx-auto flex max-w-[1480px] flex-col gap-6 px-4 pt-6 pb-16 sm:px-6 lg:px-8">
    <nav aria-label="breadcrumb" class="flex items-center gap-2 text-[13px] text-muted">
        <a href="{{ route('home') }}" wire:navigate class="text-muted hover:text-brand">{{ __('account.home') }}</a>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
        <span class="font-semibold text-navy">{{ $page->title }}</span>
    </nav>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
        <aside class="no-scrollbar flex gap-1.5 overflow-x-auto rounded-3xl border border-line bg-white p-2 lg:sticky lg:top-[120px] lg:flex-col lg:overflow-visible lg:p-3">
            @foreach ($pages as $item)
                <a
                    href="{{ $item['url'] }}"
                    wire:navigate
                    wire:key="info-{{ $item['slug'] }}"
                    @class([
                        'shrink-0 rounded-2xl px-4 py-3 text-sm font-bold whitespace-nowrap transition-colors lg:whitespace-normal',
                        'bg-navy text-white hover:text-white' => $item['slug'] === $page->slug,
                        'text-navy hover:bg-field hover:text-brand' => $item['slug'] !== $page->slug,
                    ])
                >{{ $item['title'] }}</a>
            @endforeach
        </aside>

        <article class="flex flex-col gap-5 rounded-3xl border border-line bg-white p-6 sm:p-10">
            <div class="flex flex-col gap-2">
                <h1 class="m-0 text-[26px] leading-tight font-extrabold tracking-[0.3px] sm:text-[32px]">{{ $page->title }}</h1>
                <span class="text-xs text-subtle">{{ __('pages.info.updated', ['date' => $page->updated_at?->format('d.m.Y')]) }}</span>
            </div>

            @if ($page->intro)
                <p class="m-0 rounded-2xl bg-tint px-5 py-4 text-base leading-relaxed font-semibold text-navy">{{ $page->intro }}</p>
            @endif

            @if ($page->body)
                <div class="max-w-[820px] text-base leading-[1.75] text-navy [&_a]:font-bold [&_a]:text-brand [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-line [&_blockquote]:pl-4 [&_blockquote]:text-muted [&_h2]:mt-6 [&_h2]:mb-2 [&_h2]:text-xl [&_h2]:font-extrabold [&_h3]:mt-5 [&_h3]:mb-2 [&_h3]:text-lg [&_h3]:font-extrabold [&_li]:mb-1.5 [&_ol]:mb-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:mb-4 [&_strong]:font-extrabold [&_table]:mb-4 [&_table]:w-full [&_table]:text-sm [&_td]:border-b [&_td]:border-line [&_td]:py-2 [&_td]:pr-3 [&_th]:border-b [&_th]:border-line [&_th]:py-2 [&_th]:pr-3 [&_th]:text-left [&_ul]:mb-4 [&_ul]:list-disc [&_ul]:pl-6">
                    {!! App\Support\Html::clean($page->body) !!}
                </div>
            @endif
        </article>
    </div>
</main>
