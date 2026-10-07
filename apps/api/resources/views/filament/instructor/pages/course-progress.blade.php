<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Student progress
        </x-slot>

        @if (empty($studentRows))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No students have started this course yet.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-left text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-4">Student</th>
                            <th class="py-2 pr-4">Email</th>
                            <th class="py-2 pr-4">Completed</th>
                            <th class="py-2 pr-4">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($studentRows as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 text-gray-950 dark:text-white">{{ $row['student_name'] }}</td>
                                <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ $row['student_email'] }}</td>
                                <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">
                                    {{ $row['completed_lessons'] }} / {{ $row['total_lessons'] }}
                                </td>
                                <td class="py-2 pr-4">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-32 rounded-full bg-gray-200 dark:bg-gray-700">
                                            <div
                                                class="h-2 rounded-full bg-primary-500"
                                                style="width: {{ $row['percent'] }}%"
                                            ></div>
                                        </div>
                                        <span class="text-gray-500 dark:text-gray-400">{{ $row['percent'] }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
