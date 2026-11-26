<x-filament-panels::page>
    <x-filament::tabs>
        @foreach ($this->tabs as $tab)
            <x-filament::tabs.item
                :active="$supplier === $tab['key']"
                :badge="$tab['count']"
                wire:click="showSupplier('{{ $tab['key'] }}')"
            >
                {{ $tab['label'] }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
