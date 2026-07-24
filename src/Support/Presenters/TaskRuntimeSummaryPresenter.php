<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Support\Presenters;

use DbflowLabs\Core\Models\WorkflowTaskAssignment;
use DbflowLabs\Core\Support\WorkflowDefinitionDisplay;

final class TaskRuntimeSummaryPresenter
{
    public function __construct(
        private readonly AssignmentActorPresenter $assignmentActorPresenter,
        private readonly RuntimeBadgePresenter $runtimeBadgePresenter,
        private readonly DueDatePresenter $dueDatePresenter,
        private readonly WorkflowDefinitionDisplay $workflowDefinitionDisplay,
    ) {}

    /**
     * @return array<string, string>
     */
    public function summaryForAssignment(WorkflowTaskAssignment $assignment): array
    {
        $task = $assignment->workflowTask;
        $instance = $task?->workflowInstance;
        $workflowKey = $instance?->workflow?->key;
        $actors = $this->assignmentActorPresenter->displayActors($assignment);

        $rows = [
            (string) __('dbflow-filament::dbflow-filament.runtime.summary.original_responsibility') => $actors['original'],
            (string) __('dbflow-filament::dbflow-filament.runtime.summary.effective_actor') => $actors['effective'],
            (string) __('dbflow-filament::dbflow-filament.runtime.summary.assignment_source') => $this->runtimeBadgePresenter->assignmentSourceLabel(
                $assignment->assignmentSourceOrDirect(),
            ),
            (string) __('dbflow-filament::dbflow-filament.runtime.summary.delegation_reference') => $assignment->delegation_id !== null
                ? '#'.(string) $assignment->delegation_id
                : '—',
            (string) __('dbflow-filament::dbflow-filament.runtime.summary.predecessor_assignment') => $assignment->previous_assignment_id !== null
                ? '#'.(string) $assignment->previous_assignment_id
                : '—',
            (string) __('dbflow-filament::dbflow-filament.runtime.summary.due_date') => $this->dueDatePresenter->formatDateTime($task?->due_at),
            (string) __('dbflow-filament::dbflow-filament.runtime.summary.time_remaining') => $task !== null
                ? ($this->dueDatePresenter->remainingLabel($task) ?? '—')
                : '—',
            (string) __('dbflow-filament::dbflow-filament.runtime.summary.overdue_at') => $this->dueDatePresenter->formatDateTime($task?->overdue_at),
            (string) __('dbflow-filament::dbflow-filament.runtime.summary.workflow') => $this->workflowDefinitionDisplay->workflowName(
                $workflowKey,
                $instance?->workflow?->name,
            ),
            (string) __('dbflow-filament::dbflow-filament.runtime.summary.node') => $this->workflowDefinitionDisplay->nodeLabel(
                $workflowKey,
                $task?->node_key,
                $task?->node_name,
            ),
        ];

        if (! $actors['show_both']) {
            unset($rows[(string) __('dbflow-filament::dbflow-filament.runtime.summary.original_responsibility')]);
        }

        return $rows;
    }
}
