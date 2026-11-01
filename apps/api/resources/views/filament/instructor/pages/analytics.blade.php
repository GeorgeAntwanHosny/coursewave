<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Figures cover the last {{ config('coursewave.analytics.default_days', 30) }} days and refresh about every
        {{ max(1, intdiv((int) config('coursewave.analytics.cache_seconds', 300), 60)) }} minutes.
        Earnings are accrued from learner engagement; see Payouts for what has been sent to your account.
    </p>
</x-filament-panels::page>
