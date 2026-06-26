<div class="fi-ta-ctn overflow-x-auto">
    <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
        <thead>
            <tr class="divide-x divide-gray-200 dark:divide-white/5">
                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('dbflow-filament::dbflow-filament.pages.view_instance.timeline.created_at') }}
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('dbflow-filament::dbflow-filament.pages.view_instance.timeline.event') }}
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('dbflow-filament::dbflow-filament.pages.view_instance.timeline.actor') }}
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('dbflow-filament::dbflow-filament.pages.view_instance.timeline.from_node') }}
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('dbflow-filament::dbflow-filament.pages.view_instance.timeline.to_node') }}
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('dbflow-filament::dbflow-filament.pages.view_instance.timeline.task_node') }}
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('dbflow-filament::dbflow-filament.pages.view_instance.timeline.comment') }}
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
            @forelse ($items as $item)
                <tr class="divide-x divide-gray-200 dark:divide-white/5">
                    <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $item['created_at'] }}</td>
                    <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $item['event_label'] }}</td>
                    <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $item['actor_name'] }}</td>
                    <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $item['from_node'] }}</td>
                    <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $item['to_node'] }}</td>
                    <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $item['task_node'] }}</td>
                    <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $item['comment'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ __('dbflow-filament::dbflow-filament.pages.view_instance.timeline.empty') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
