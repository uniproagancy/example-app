@php
    $items = $this->items;
    $picked = $items->whereNotNull('picked_up_at')->count();
@endphp

<x-filament-panels::page>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <label class="flex flex-col gap-1">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('courier.pickups.day') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model.live="day" />
                </x-filament::input.wrapper>
            </label>
            @if ($items->isNotEmpty())
                <x-filament::badge :color="$picked === $items->count() ? 'success' : 'warning'" size="lg">
                    {{ __('courier.pickups.progress', ['picked' => $picked, 'total' => $items->count()]) }}
                </x-filament::badge>
            @endif
        </div>

        @forelse ($this->groups as $place => $group)
            @php($first = $group->first())
            <x-filament::section wire:key="place-{{ md5($place) }}" compact :heading="$place" :description="$first->pickup_address">
                <div class="flex flex-col divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($group as $item)
                        @php($done = $item->picked_up_at !== null)
                        <div wire:key="pickup-{{ $item->id }}" @class(['flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between', 'opacity-70' => $done])>
                            <div class="flex min-w-0 flex-col gap-0.5 text-sm">
                                <span class="font-semibold text-gray-950 dark:text-white">{{ $item->name }}</span>
                                @if ($item->options)
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ implode(', ', (array) $item->options) }}</span>
                                @endif
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ collect([
                                        $this->supplierOf($item),
                                        $this->supplierCodeOf($item) ? __('courier.pickups.code', ['code' => $this->supplierCodeOf($item)]) : null,
                                        $item->order?->number,
                                    ])->filter()->implode(' · ') }}
                                </span>
                                @if ($item->pickup_address && $item->pickup_address !== $first->pickup_address)
                                    <span class="text-xs text-gray-700 dark:text-gray-300">{{ $item->pickup_address }}</span>
                                @endif
                                @if ($item->pickup_note)
                                    <span class="text-xs font-medium text-warning-700 dark:text-warning-400">{{ __('courier.pickups.note') }}: {{ $item->pickup_note }}</span>
                                @endif
                                @if ($done)
                                    <span class="text-xs text-success-700 dark:text-success-400">{{ __('courier.pickups.picked_by', ['courier' => (string) $item->pickedUpBy?->name, 'time' => $item->picked_up_at->format('H:i')]) }}</span>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <x-filament::badge>{{ __('courier.deliveries.quantity', ['count' => $item->quantity]) }}</x-filament::badge>
                                <x-filament::button
                                    :color="$done ? 'gray' : 'success'"
                                    :outlined="$done"
                                    :icon="$done ? 'heroicon-m-arrow-uturn-left' : 'heroicon-m-check'"
                                    wire:click="togglePickup({{ $item->id }})"
                                    wire:target="togglePickup({{ $item->id }})"
                                >
                                    {{ $done ? __('courier.pickups.undo') : __('courier.pickups.pick') }}
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @empty
            <x-filament::section>
                <div class="flex flex-col items-center gap-1 py-6 text-center">
                    <span class="font-semibold text-gray-950 dark:text-white">{{ __('courier.pickups.empty') }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('courier.pickups.empty_help') }}</span>
                </div>
            </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>
