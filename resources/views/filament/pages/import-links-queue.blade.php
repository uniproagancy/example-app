<div class="flex flex-col divide-y divide-gray-100 dark:divide-white/5">
    @forelse ($rows as $row)
        <div wire:key="link-row-{{ $row['id'] }}" class="flex items-center gap-3 py-2.5">
            <span @class([
                'grid size-8 shrink-0 place-items-center rounded-lg',
                'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400' => $row['product'],
                'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400' => ! $row['product'],
            ])>
                <x-filament::icon :icon="$row['product'] ? \Filament\Support\Icons\Heroicon::OutlinedCheck : \Filament\Support\Icons\Heroicon::OutlinedClock" class="size-4" />
            </span>

            <div class="min-w-0 flex-1">
                <div class="truncate font-mono text-xs text-gray-950 dark:text-white" title="{{ $row['address'] }}">{{ $row['address'] }}</div>
                <div class="flex flex-wrap gap-x-3 text-xs text-gray-500 dark:text-gray-400">
                    @if ($row['product'])
                        <a href="{{ $row['edit'] }}" class="truncate font-semibold text-success-600 hover:underline dark:text-success-400">{{ $row['product'] }}</a>
                    @else
                        <span>{{ __('admin.links.waiting') }}</span>
                    @endif
                    @if ($row['category'])
                        <span>{{ $row['category'] }}</span>
                    @endif
                </div>
            </div>

            <x-filament::icon-button
                :icon="\Filament\Support\Icons\Heroicon::OutlinedTrash"
                color="danger"
                size="sm"
                :label="__('admin.links.forget')"
                :disabled="$locked"
                wire:click="forget({{ $row['id'] }})"
                wire:confirm="{{ __('admin.links.forget_confirm') }}"
            />
        </div>
    @empty
        <p class="py-2 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.links.empty') }}</p>
    @endforelse

    @if ($more > 0)
        <p class="pt-2.5 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.links.more', ['count' => $more]) }}</p>
    @endif
</div>
