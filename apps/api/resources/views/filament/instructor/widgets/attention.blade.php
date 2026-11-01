<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Needs your attention</x-slot>

        <ul class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($items as $item)
                <li class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                    <div class="flex min-w-0 items-start gap-3">
                        <x-filament::badge :color="$item['color']" :icon="$item['icon']">{{ $item['label'] }}</x-filament::badge>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $item['title'] }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $item['detail'] }}</p>
                        </div>
                    </div>

                    @if ($item['url'])
                        <x-filament::link :href="$item['url']">{{ $item['action'] }}</x-filament::link>
                    @endif
                </li>
            @endforeach
        </ul>

        <p class="mt-4 text-sm">
            <x-filament::link :href="$analyticsUrl">Open full analytics</x-filament::link>
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
