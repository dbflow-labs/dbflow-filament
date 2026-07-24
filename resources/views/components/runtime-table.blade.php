<div class="fi-ta-ctn overflow-x-auto">
    <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
        <thead>
            <tr class="divide-x divide-gray-200 dark:divide-white/5">
                @foreach ($columns as $column => $heading)
                    <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $heading }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
            @forelse ($rows as $row)
                <tr class="divide-x divide-gray-200 dark:divide-white/5">
                    @foreach (array_keys($columns) as $column)
                        <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $row[$column] ?? '—' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ __('dbflow-filament::dbflow-filament.labels.no_records') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
