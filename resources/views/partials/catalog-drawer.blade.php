@php
    $drawerCategories = App\Support\Catalog::categories()->map(fn ($category, $index) => [
        ...$category,
        'number' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
        'countLabel' => $category['count'] ? __('shop.products_count', ['count' => $category['count']]) : __('shop.categories_count', ['count' => count($category['groups'])]),
        'iconSvg' => view('partials.category-icon', ['icon' => $category['icon'], 'class' => 'size-[19px]', 'stroke' => 1.7])->render(),
    ])->values();
@endphp

<div
    x-data="catalogDrawer(@js($drawerCategories))"
    x-on:open-catalog.window="openAt($event.detail?.index)"
>
    <template x-teleport="body">
        <div x-show="$store.ui.catalog" x-cloak>
            <div x-on:click="$store.ui.catalog = false" class="fixed inset-0 z-[70] animate-fade bg-navy/45"></div>
            <aside
                class="fixed inset-y-0 left-0 z-[71] grid w-screen animate-slide-in grid-cols-1 overflow-hidden bg-white shadow-[20px_0_60px_rgba(10,22,51,.3)] sm:w-[min(980px,100vw)] sm:grid-cols-[260px_minmax(0,1fr)] sm:rounded-r-[28px] lg:grid-cols-[300px_minmax(0,1fr)]"
            >
                <div class="min-h-0 flex-col bg-navy font-caps text-white sm:flex" :class="pane === 'list' ? 'flex' : 'hidden'">
                    <div class="flex items-center justify-between pt-[26px] pr-5 pb-[18px] pl-7">
                        <span class="text-xl font-extrabold tracking-[0.4px] uppercase">{{ __('shop.catalog') }}</span>
                        <button type="button" x-on:click="$store.ui.catalog = false" aria-label="{{ __('shop.close') }}" class="grid size-10 place-items-center rounded-full bg-white/8 text-white hover:bg-white/16">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>
                    <div class="scroll-thin-dark flex flex-1 flex-col gap-0.5 overflow-y-auto pt-1 pr-1.5 pb-5 pl-3.5">
                        <template x-for="(category, i) in categories" :key="category.id">
                            <button
                                type="button"
                                x-on:mouseenter="index = i"
                                x-on:click="index = i; pane = 'sub'"
                                class="grid grid-cols-[36px_minmax(0,1fr)_16px] items-center gap-3 rounded-[14px] px-3 py-[11px] text-left transition-colors"
                                :class="i === index ? 'bg-white text-navy' : 'bg-transparent text-white hover:bg-white/6'"
                            >
                                <span class="grid size-9 place-items-center rounded-[11px] transition-colors" :class="i === index ? 'bg-brand text-white' : 'bg-white/8 text-[#8FA2C4]'" x-html="category.iconSvg"></span>
                                <span class="min-w-0 text-[13px] leading-[1.35] font-bold tracking-[0.3px] uppercase" x-text="category.short"></span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </button>
                        </template>
                    </div>
                    <div class="flex flex-col gap-1 border-t border-white/10 px-7 pt-[18px] pb-[26px]">
                        <a href="{{ route('catalog') }}" wire:navigate x-on:click="$store.ui.catalog = false" class="mb-3 flex h-11 items-center justify-between rounded-[14px] bg-white/8 px-4 text-[13px] font-bold tracking-[0.3px] text-white uppercase hover:bg-brand hover:text-white">
                            {{ __('shop.all_categories') }}
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                        <span class="text-xs text-[#8FA2C4]">{{ __('shop.help_needed') }}</span>
                        <a href="{{ App\Support\Site::phoneHref() }}" class="font-display text-base font-extrabold text-white hover:text-white">{{ App\Support\Site::hotline() ?? App\Support\Site::phone() }}</a>
                    </div>
                </div>

                <div class="min-h-0 flex-col sm:flex" :class="pane === 'sub' ? 'flex' : 'hidden'">
                    <div class="px-5 pt-[26px] sm:px-8">
                        <button type="button" x-on:click="pane = 'list'" class="mb-3 flex h-8 items-center gap-1.5 rounded-full bg-[#F1F5FC] pr-3 pl-2 text-[13px] font-bold text-navy sm:hidden">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>
                            {{ __('shop.back') }}
                        </button>
                        <div class="relative h-[150px] overflow-hidden rounded-[20px] bg-[radial-gradient(circle_at_85%_30%,rgba(20,180,255,.45),transparent_55%),linear-gradient(120deg,#0A1633,#0B63F6)]">
                            <div class="pointer-events-none absolute inset-x-7 bottom-[22px] flex items-end justify-between gap-4">
                                <div class="flex flex-col gap-1 text-white">
                                    <span class="text-xs text-[#C9D6EE]" x-text="active.countLabel"></span>
                                    <span class="font-caps text-[22px] leading-tight font-extrabold tracking-[0.3px] text-pretty uppercase" x-text="active.name"></span>
                                </div>
                                <a :href="active.url" class="pointer-events-auto flex h-10 shrink-0 items-center gap-1.5 rounded-full bg-white px-[18px] text-[13px] font-bold text-navy hover:bg-brand hover:text-white">{{ __('shop.all_arrow') }}</a>
                            </div>
                        </div>
                    </div>
                    <div class="scroll-thin flex-1 overflow-y-auto px-5 pt-2 pb-7 sm:px-8">
                        <div class="gap-x-9 lg:columns-2">
                            <template x-for="group in active.groups" :key="group.name">
                                <div class="flex break-inside-avoid flex-col border-b border-line-mid pt-[18px] pb-3.5">
                                    <a :href="group.url" class="flex items-center justify-between gap-2.5 text-navy hover:text-brand">
                                        <span class="font-caps text-sm leading-[1.4] font-extrabold tracking-[0.3px] uppercase" x-text="group.name"></span>
                                        <span class="grid h-6 min-w-[34px] shrink-0 place-items-center rounded-full bg-[#F1F5FC] px-2 text-[11px] font-bold text-muted" x-show="group.count" x-text="group.count"></span>
                                    </a>
                                    <template x-if="group.subs.length">
                                        <div class="mt-3 flex flex-wrap gap-1.5">
                                            <template x-for="sub in group.subs" :key="sub.name">
                                                <a :href="sub.url" class="flex h-8 items-center gap-1.5 rounded-full border border-line px-3 text-xs font-semibold text-navy hover:border-brand hover:text-brand">
                                                    <span x-text="sub.name"></span><span x-show="sub.count" class="font-medium text-subtle" x-text="sub.count"></span>
                                                </a>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </template>
</div>
