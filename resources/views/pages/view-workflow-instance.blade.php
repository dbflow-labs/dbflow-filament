<x-filament-panels::page>
    <x-filament::section
        :heading="__('dbflow-filament::dbflow-filament.pages.view_instance.sections.overview')"
    >
        <dl class="grid gap-3 sm:grid-cols-2">
            @foreach ($this->instanceOverview() as $label => $value)
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>

    <x-filament::section
        class="mt-6"
        :heading="__('dbflow-filament::dbflow-filament.pages.view_instance.sections.tasks')"
        :description="__('dbflow-filament::dbflow-filament.pages.view_instance.sections.tasks_intro')"
    >
        <div class="fi-ta-ctn overflow-x-auto">
            <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                <thead>
                    <tr class="divide-x divide-gray-200 dark:divide-white/5">
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('dbflow-filament::dbflow-filament.pages.view_instance.tasks.node_key') }}
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('dbflow-filament::dbflow-filament.pages.view_instance.tasks.status') }}
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('dbflow-filament::dbflow-filament.pages.view_instance.tasks.assigned_at') }}
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('dbflow-filament::dbflow-filament.pages.view_instance.tasks.due_at') }}
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('dbflow-filament::dbflow-filament.pages.view_instance.tasks.time_remaining') }}
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('dbflow-filament::dbflow-filament.pages.view_instance.tasks.completed_at') }}
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('dbflow-filament::dbflow-filament.pages.view_instance.tasks.result_comment') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @forelse ($this->tasksForDisplay() as $task)
                        <tr class="divide-x divide-gray-200 dark:divide-white/5">
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $task['node_key'] }}</td>
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $task['status'] }}</td>
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $task['assigned_at'] }}</td>
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $task['due_at'] }}</td>
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $task['time_remaining'] }}</td>
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $task['completed_at'] }}</td>
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $task['result_comment'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ __('dbflow-filament::dbflow-filament.pages.view_instance.tasks.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section
        class="mt-6"
        :heading="__('dbflow-filament::dbflow-filament.pages.view_instance.sections.assignments')"
        :description="__('dbflow-filament::dbflow-filament.pages.view_instance.sections.assignments_intro')"
    >
        <div class="fi-ta-ctn overflow-x-auto">
            <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                <thead>
                    <tr class="divide-x divide-gray-200 dark:divide-white/5">
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('dbflow-filament::dbflow-filament.pages.view_instance.assignments.assignee_name') }}
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('dbflow-filament::dbflow-filament.pages.view_instance.assignments.status') }}
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('dbflow-filament::dbflow-filament.pages.view_instance.assignments.sequence') }}
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('dbflow-filament::dbflow-filament.pages.view_instance.assignments.acted_at') }}
                        </th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('dbflow-filament::dbflow-filament.pages.view_instance.assignments.comment') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @forelse ($this->assignmentsForDisplay() as $assignment)
                        <tr class="divide-x divide-gray-200 dark:divide-white/5">
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $assignment['assignee_name'] }}</td>
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $assignment['status'] }}</td>
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $assignment['sequence'] }}</td>
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $assignment['acted_at'] }}</td>
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $assignment['comment'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ __('dbflow-filament::dbflow-filament.pages.view_instance.assignments.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    @if (config('dbflow-filament.enable_logs_timeline', true))
        <x-filament::section
            class="mt-6"
            :heading="__('dbflow-filament::dbflow-filament.pages.view_instance.sections.timeline')"
            :description="__('dbflow-filament::dbflow-filament.pages.view_instance.sections.timeline_intro')"
        >
            @include('dbflow-filament::components.timeline', ['items' => $this->timelineForDisplay()])
        </x-filament::section>
    @endif

    @if ($this->assignmentHistoryForDisplay()->isNotEmpty())
        <x-filament::section
            class="mt-6"
            :heading="__('dbflow-filament::dbflow-filament.pages.view_instance.sections.assignment_history')"
            :description="__('dbflow-filament::dbflow-filament.pages.view_instance.sections.assignment_history_intro')"
        >
            @include('dbflow-filament::components.runtime-table', [
                'columns' => __('dbflow-filament::dbflow-filament.pages.view_instance.assignment_history'),
                'rows' => $this->assignmentHistoryForDisplay(),
            ])
        </x-filament::section>
    @endif

    @if ($this->slaEventsForDisplay()->isNotEmpty())
        <x-filament::section
            class="mt-6"
            :heading="__('dbflow-filament::dbflow-filament.pages.view_instance.sections.sla_events')"
            :description="__('dbflow-filament::dbflow-filament.pages.view_instance.sections.sla_events_intro')"
        >
            @include('dbflow-filament::components.runtime-table', [
                'columns' => __('dbflow-filament::dbflow-filament.pages.view_instance.sla_events'),
                'rows' => $this->slaEventsForDisplay(),
            ])
        </x-filament::section>
    @endif

    @if ($this->actionExecutionsForDisplay()->isNotEmpty())
        <x-filament::section
            class="mt-6"
            :heading="__('dbflow-filament::dbflow-filament.pages.view_instance.sections.action_executions')"
            :description="__('dbflow-filament::dbflow-filament.pages.view_instance.sections.action_executions_intro')"
        >
            @include('dbflow-filament::components.runtime-table', [
                'columns' => $this->actionExecutionColumnsForDisplay(),
                'rows' => $this->actionExecutionsForDisplay(),
            ])
        </x-filament::section>
    @endif
</x-filament-panels::page>
