@php
    $perkIconPaths = [
        'delivery' => '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
        'warranty' => '<path d="M12 3 4 6v6c0 4.5 3.4 8 8 9 4.6-1 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'installments' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
        'returns' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
        'support' => '<path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="3" y="13" width="4" height="6" rx="1.5"/><rect x="17" y="13" width="4" height="6" rx="1.5"/><path d="M19 19a3 3 0 0 1-3 3h-3"/>',
        'store' => '<path d="M4 9 5.5 4h13L20 9"/><path d="M4 9h16v11H4z"/><path d="M9 20v-6h6v6"/>',
    ];
@endphp

        <section class="mt-6 hidden grid-cols-2 overflow-hidden rounded-[18px] border border-line-mid sm:mt-10 sm:grid sm:grid-cols-[repeat(auto-fit,minmax(220px,1fr))] sm:rounded-3xl">
            @foreach ($perks as $perk)
                <div class="flex flex-col items-start gap-2.5 p-3.5 shadow-[1px_0_0_#E9EDF4,0_1px_0_#E9EDF4] sm:flex-row sm:items-center sm:gap-3.5 sm:px-6 sm:py-5 sm:shadow-[1px_0_0_#E9EDF4]">
                    <span class="grid size-11 shrink-0 place-items-center rounded-full border-[1.5px] border-[#D5E2F7] text-brand">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $perkIconPaths[$perk['icon']] ?? $perkIconPaths['delivery'] !!}</svg>
                    </span>
                    <div>
                        <div class="text-sm font-bold">{{ $perk['title'] }}</div>
                        @if ($perk['text'])
                            <div class="text-xs text-muted">{{ $perk['text'] }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
