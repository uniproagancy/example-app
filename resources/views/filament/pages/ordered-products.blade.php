<x-filament-panels::page>
    <x-filament::tabs style="width: 100%">
        @foreach ($this->tabs as $tab)
            <x-filament::tabs.item
                :active="$supplier === $tab['key']"
                wire:click="showSupplier('{{ $tab['key'] }}')"
                style="flex: 1 0 auto"
            >
                {{ $tab['label'] }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
