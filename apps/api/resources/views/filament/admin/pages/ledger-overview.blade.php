<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-filament::section>
            <x-slot name="heading">Platform fee revenue</x-slot>
            <p class="text-2xl font-semibold text-gray-950 dark:text-white">
                ${{ number_format($this->getTotalPlatformFeeCents() / 100, 2) }}
            </p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Total accrued to instructors</x-slot>
            <p class="text-2xl font-semibold text-gray-950 dark:text-white">
                ${{ number_format($this->getTotalAccruedCents() / 100, 2) }}
            </p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Total paid out</x-slot>
            <p class="text-2xl font-semibold text-gray-950 dark:text-white">
                ${{ number_format(abs($this->getTotalPaidCents()) / 100, 2) }}
            </p>
        </x-filament::section>
    </div>

    <x-filament::section class="mt-6">
        <x-slot name="heading">All ledger entries</x-slot>
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
