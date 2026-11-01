<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Period figures cover the last {{ config('coursewave.analytics.default_days', 30) }} days; "Owed to instructors" and
        the health row are current. Everything is cached for about
        {{ max(1, intdiv((int) config('coursewave.analytics.cache_seconds', 300), 60)) }} minutes.
        Comments are deliberately not part of admin analytics.
    </p>
</x-filament-panels::page>
