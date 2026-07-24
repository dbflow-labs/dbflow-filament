<x-filament-panels::page>
    <x-filament::section
        :heading="__('dbflow-filament::dbflow-filament.pages.view_action_execution.sections.overview')"
    >
        <dl class="grid gap-3 sm:grid-cols-2">
            @foreach ($this->executionOverview() as $label => $value)
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>

    @if ($this->attemptsForDisplay()->isNotEmpty())
        <x-filament::section
            class="mt-6"
            :heading="__('dbflow-filament::dbflow-filament.pages.view_action_execution.sections.attempts')"
            :description="__('dbflow-filament::dbflow-filament.pages.view_action_execution.sections.attempts_intro')"
        >
            <div class="fi-ta-ctn overflow-x-auto">
                <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                    <thead>
                        <tr class="divide-x divide-gray-200 dark:divide-white/5">
                            @foreach (__('dbflow-filament::dbflow-filament.pages.view_action_execution.attempts') as $column => $heading)
                                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $heading }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach ($this->attemptsForDisplay() as $attempt)
                            <tr class="divide-x divide-gray-200 dark:divide-white/5">
                                @foreach (array_keys(__('dbflow-filament::dbflow-filament.pages.view_action_execution.attempts')) as $column)
                                    <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $attempt[$column] ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
