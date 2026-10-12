<x-filament-panels::page>
    @php($account = $this->getAccount())

    <x-filament::section>
        <x-slot name="heading">Payout account</x-slot>

        @if (! $account)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Connect a payout account to receive your earnings. Payouts run weekly.
            </p>
        @elseif ($account->payouts_enabled)
            <p class="text-sm text-success-600 dark:text-success-400">
                Connected and ready to receive payouts.
            </p>
        @else
            <p class="text-sm text-warning-600 dark:text-warning-400">
                Onboarding isn't finished yet. Continue onboarding, then refresh the status.
            </p>
        @endif
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Payout history</x-slot>
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
