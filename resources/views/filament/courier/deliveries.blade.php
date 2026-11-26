@php
    $orders = $this->orders;
    $label = 'text-xs font-medium text-gray-500 dark:text-gray-400';
@endphp

<x-filament-panels::page>
    <div class="flex flex-col gap-4">
        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
            <x-filament::input type="search" wire:model.live.debounce.400ms="search" :placeholder="__('courier.deliveries.search')" />
        </x-filament::input.wrapper>

        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('courier.deliveries.count', ['count' => $orders->count()]) }}</p>

        @forelse ($orders as $order)
            <x-filament::section wire:key="order-{{ $order->id }}" compact>
                <x-slot name="heading">
                    <span class="flex flex-wrap items-center gap-2">
                        <span>{{ $order->number }}</span>
                        @if ($order->is_region)
                            <x-filament::badge color="warning">{{ __('courier.deliveries.region') }}</x-filament::badge>
                        @endif
                    </span>
                </x-slot>
                <x-slot name="afterHeader">
                    @if ($order->isPaid())
                        <x-filament::badge color="success">{{ __('courier.deliveries.paid') }}</x-filament::badge>
                    @else
                        <x-filament::badge color="danger">{{ __('courier.deliveries.collect') }}: {{ App\Support\Catalog::money($order->total) }}</x-filament::badge>
                    @endif
                </x-slot>

                <div class="flex flex-col gap-3 text-sm">
                    <div class="flex flex-col gap-1">
                        <span class="font-semibold text-gray-950 dark:text-white">{{ trim($order->first_name.' '.$order->last_name) }}</span>
                        @if ($order->phone)
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $order->phone) }}" class="flex w-fit items-center gap-1.5 font-semibold text-primary-600 dark:text-primary-400">
                                <x-filament::icon icon="heroicon-m-phone" class="size-4" />{{ $order->phone }}
                            </a>
                        @endif
                        @if ($order->address || $order->apartment)
                            <span class="text-gray-700 dark:text-gray-300">{{ collect([$order->address, $order->apartment])->filter()->implode(', ') }}</span>
                        @endif
                        @if ($order->note)
                            <span class="text-gray-500 dark:text-gray-400">{{ __('courier.deliveries.note') }}: {{ $order->note }}</span>
                        @endif
                    </div>

                    <div class="flex flex-col divide-y divide-gray-100 rounded-xl ring-1 ring-gray-950/5 dark:divide-white/5 dark:ring-white/10">
                        @foreach ($order->items as $item)
                            @php
                                $key = 'i'.$item->id;
                                $photo = $photos[$key] ?? null;
                                $filled = filled($item->serial_number) && filled($item->serial_photo);
                            @endphp
                            <div wire:key="item-{{ $item->id }}" class="flex flex-col gap-3 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex flex-col">
                                        <span class="font-medium text-gray-950 dark:text-white">{{ $item->name }}</span>
                                        @if ($item->options)
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ implode(', ', (array) $item->options) }}</span>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <x-filament::badge>{{ __('courier.deliveries.quantity', ['count' => $item->quantity]) }}</x-filament::badge>
                                        @if ($filled)
                                            <x-filament::badge color="success" icon="heroicon-m-check">{{ __('courier.deliveries.done') }}</x-filament::badge>
                                        @endif
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_140px]">
                                    <label class="flex flex-col gap-1">
                                        <span class="{{ $label }}">{{ __('courier.deliveries.serial') }}</span>
                                        <x-filament::input.wrapper :valid="! $errors->has('items.'.$key.'.serial')">
                                            <x-filament::input type="text" wire:model="items.{{ $key }}.serial" autocomplete="off" autocapitalize="characters" :placeholder="__('courier.deliveries.serial_placeholder')" />
                                        </x-filament::input.wrapper>
                                        @error('items.'.$key.'.serial') <span class="text-xs text-danger-600">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="flex flex-col gap-1">
                                        <span class="{{ $label }}">{{ __('courier.deliveries.months') }}</span>
                                        <x-filament::input.wrapper :valid="! $errors->has('items.'.$key.'.months')">
                                            <x-filament::input type="number" min="1" max="120" inputmode="numeric" wire:model="items.{{ $key }}.months" />
                                        </x-filament::input.wrapper>
                                        @error('items.'.$key.'.months') <span class="text-xs text-danger-600">{{ $message }}</span> @enderror
                                    </label>
                                </div>

                                <div class="flex flex-wrap items-center gap-3">
                                    @if ($photo)
                                        <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-20 rounded-lg object-cover ring-1 ring-gray-950/10">
                                    @elseif ($item->serial_photo)
                                        <a href="{{ route('serial-photo', $item) }}" target="_blank">
                                            <img src="{{ route('serial-photo', $item) }}" alt="" class="size-20 rounded-lg object-cover ring-1 ring-gray-950/10">
                                        </a>
                                    @endif

                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-primary-600 ring-1 ring-primary-600/30 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-white/5">
                                        <x-filament::icon icon="heroicon-m-camera" class="size-5" />
                                        <span wire:loading.remove wire:target="photos.{{ $key }}">{{ $photo || $item->serial_photo ? __('courier.deliveries.retake_photo') : __('courier.deliveries.take_photo') }}</span>
                                        <span wire:loading wire:target="photos.{{ $key }}">{{ __('courier.deliveries.uploading') }}</span>
                                        <input type="file" accept="image/*" capture="environment" wire:model="photos.{{ $key }}" class="sr-only">
                                    </label>

                                    <x-filament::button wire:click="saveItem({{ $item->id }})" wire:target="saveItem({{ $item->id }}), photos.{{ $key }}" class="ms-auto">
                                        {{ __('courier.deliveries.save') }}
                                    </x-filament::button>
                                </div>
                                @error('photos.'.$key) <span class="text-xs text-danger-600">{{ $message }}</span> @enderror
                            </div>
                        @endforeach
                    </div>

                    @php
                        $missing = $order->items->filter(fn ($item) => blank($item->serial_number) || blank($item->serial_photo))->count();
                        $question = __('courier.deliveries.confirm_delivered', ['number' => $order->number]).($missing ? ' '.__('courier.deliveries.confirm_missing', ['count' => $missing]) : '');
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-3 dark:border-white/5">
                        @unless ($order->isPaid())
                            <label class="flex items-center gap-2 font-semibold text-gray-950 dark:text-white">
                                <x-filament::input.checkbox wire:model="collected.o{{ $order->id }}" />
                                {{ __('courier.deliveries.collected', ['amount' => App\Support\Catalog::money($order->total)]) }}
                            </label>
                        @endunless
                        <x-filament::button color="success" icon="heroicon-m-check-circle" wire:click="markDelivered({{ $order->id }})" wire:confirm="{{ $question }}" wire:target="markDelivered({{ $order->id }})" class="ms-auto">
                            {{ __('courier.deliveries.mark_delivered') }}
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @empty
            <x-filament::section>
                <div class="flex flex-col items-center gap-1 py-6 text-center">
                    <span class="font-semibold text-gray-950 dark:text-white">{{ __('courier.deliveries.empty') }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('courier.deliveries.empty_help') }}</span>
                </div>
            </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>
