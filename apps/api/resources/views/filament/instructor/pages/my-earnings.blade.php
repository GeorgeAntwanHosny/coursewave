<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-filament::section>
            <x-slot name="heading">Total owed</x-slot>
            <p class="text-2xl font-semibold text-gray-950 dark:text-white">
                ${{ number_format($this->getOwedCents() / 100, 2) }}
            </p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Total paid out</x-slot>
            <p class="text-2xl font-semibold text-gray-950 dark:text-white">
                ${{ number_format(abs($this->getPaidCents()) / 100, 2) }}
            </p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Outstanding</x-slot>
            <p class="text-2xl font-semibold text-primary-600 dark:text-primary-400">
                ${{ number_format($this->getOutstandingCents() / 100, 2) }}
            </p>
        </x-filament::section>
    </div>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Earnings history</x-slot>
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
