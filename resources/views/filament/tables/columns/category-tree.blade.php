@php
    $record = $getRecord();
    $depth = \App\Filament\Resources\Categories\CategoryResource::tree()[$record->getKey()]['depth'] ?? 0;
@endphp

<div class="flex items-center gap-3 px-3 py-1.5" style="padding-inline-start: {{ 0.75 + $depth * 1.75 }}rem">
    @if ($depth > 0)
        <span aria-hidden="true" class="-mt-4 h-5 w-3 shrink-0 rounded-bl-md border-b-2 border-l-2 border-gray-300 dark:border-white/15"></span>
    @endif

    <span @class([
        'grid size-9 shrink-0 place-items-center rounded-lg',
        'bg-primary-50 text-primary-600 dark:bg-primary-500/15 dark:text-primary-400' => $depth === 0,
        'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400' => $depth > 0,
    ])>
        <x-filament::icon :icon="\App\Filament\Resources\Categories\CategoryResource::treeIcon($record)" class="size-5" />
    </span>

    <div class="min-w-0">
        <div @class([
            'truncate text-sm text-gray-950 dark:text-white',
            'font-bold' => $depth === 0,
            'font-semibold' => $depth > 0,
            'opacity-60' => ! $record->is_active,
        ])>
            {{ $record->kaTranslation?->title ?? '#'.$record->getKey() }}
        </div>

        @if ($record->children_count)
            <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('admin.categories.children_count', ['count' => $record->children_count]) }}
            </div>
        @endif
    </div>
</div>
