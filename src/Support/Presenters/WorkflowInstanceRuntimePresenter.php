<?php

/**
 * This file is part of the dbflowlabs/filament package.
 *
 * Copyright (c) 2026 Baron Wang <hello@dbflow.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT
 * @link    https://dbflow.dev
 * @see     https://github.com/dbflow-labs/dbflow-filament
 */

declare(strict_types=1);

namespace DbflowLabs\Filament\Support\Presenters;

use DbflowLabs\Core\Models\WorkflowActionAttempt;
use DbflowLabs\Core\Models\WorkflowActionExecution;
use DbflowLabs\Core\Models\WorkflowInstance;
use DbflowLabs\Core\Models\WorkflowSlaEvent;
use DbflowLabs\Core\Models\WorkflowTask;
use DbflowLabs\Core\Models\WorkflowTaskAssignment;
use DbflowLabs\Core\Support\WorkflowDefinitionDisplay;
use DbflowLabs\Filament\Support\RuntimeCapabilityGate;
use DbflowLabs\Filament\Support\WorkflowFilamentPermissions;
use Illuminate\Support\Collection;

final class WorkflowInstanceRuntimePresenter
{
    public function __construct(
        private readonly AssignmentActorPresenter $assignmentActorPresenter,
        private readonly RuntimeBadgePresenter $runtimeBadgePresenter,
        private readonly DueDatePresenter $dueDatePresenter,
        private readonly SafeMetadataPresenter $safeMetadataPresenter,
        private readonly WorkflowDefinitionDisplay $workflowDefinitionDisplay,
    ) {}

    /**
     * @return Collection<int, array<string, string>>
     */
    public function assignmentHistory(WorkflowInstance $instance): Collection
    {
        $workflowKey = $instance->workflow?->key;

        return $instance->tasks
            ->flatMap(fn (WorkflowTask $task) => $task->assignments)
            ->sortBy('id')
            ->values()
            ->map(function (WorkflowTaskAssignment $assignment) use ($workflowKey): array {
                $task = $assignment->workflowTask;
                $actors = $this->assignmentActorPresenter->displayActors($assignment);

                return [
                    'node' => $this->workflowDefinitionDisplay->nodeLabel($workflowKey, $task?->node_key, $task?->node_name),
                    'original_actor' => $actors['original'],
                    'effective_actor' => $actors['show_both'] ? $actors['effective'] : '—',
                    'source' => $this->runtimeBadgePresenter->assignmentSourceLabel($assignment->assignmentSourceOrDirect()),
                    'delegation' => $assignment->delegation_id !== null
                        ? '#'.(string) $assignment->delegation_id
                        : '—',
                    'predecessor' => $assignment->previous_assignment_id !== null
                        ? '#'.(string) $assignment->previous_assignment_id
                        : '—',
                    'status' => (string) ($assignment->status?->value ?? $assignment->status ?? '—'),
                    'assigned_at' => $this->dueDatePresenter->formatDateTime($assignment->created_at),
                    'acted_at' => $this->dueDatePresenter->formatDateTime($assignment->acted_at),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    public function slaEvents(WorkflowInstance $instance): Collection
    {
        return WorkflowSlaEvent::query()
            ->where('workflow_instance_id', $instance->getKey())
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->limit((int) config('dbflow-filament.runtime_detail_limit', 100))
            ->get()
            ->map(fn (WorkflowSlaEvent $event): array => [
                'event_type' => $this->runtimeBadgePresenter->slaEventTypeLabel($event->event_type),
                'status' => $this->runtimeBadgePresenter->slaEventStatusLabel($event->status),
                'scheduled_at' => $this->dueDatePresenter->formatDateTime($event->scheduled_at),
                'next_attempt_at' => $this->dueDatePresenter->formatDateTime($event->next_attempt_at),
                'attempts' => (string) $event->attempts.' / '.(string) $event->max_attempts,
                'processed_at' => $this->dueDatePresenter->formatDateTime($event->processed_at),
                'failed_at' => $this->dueDatePresenter->formatDateTime($event->failed_at),
                'cancelled_at' => $this->dueDatePresenter->formatDateTime($event->cancelled_at),
                'last_error' => $this->safeMetadataPresenter->safeErrorSummary($event->last_error),
            ]);
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    public function actionExecutions(WorkflowInstance $instance): Collection
    {
        return WorkflowActionExecution::query()
            ->where('workflow_instance_id', $instance->getKey())
            ->withCount('attempts')
            ->orderByDesc('id')
            ->limit((int) config('dbflow-filament.runtime_detail_limit', 100))
            ->get()
            ->map(function (WorkflowActionExecution $execution) use ($instance): array {
                $workflowKey = $instance->workflow?->key;
                $summary = $this->safeMetadataPresenter->executionSummary($execution);

                $row = [
                    'node' => $this->workflowDefinitionDisplay->nodeLabel($workflowKey, $execution->node_key),
                    'handler' => $summary['handler'],
                    'mode' => $summary['mode'],
                    'status' => $summary['status'],
                    'attempts' => (string) $execution->attempts.' / '.(string) $execution->max_attempts,
                    'next_attempt_at' => $this->dueDatePresenter->formatDateTime($execution->next_attempt_at),
                    'completed_at' => $this->dueDatePresenter->formatDateTime(
                        $execution->succeeded_at ?? $execution->failed_at ?? $execution->exhausted_at ?? $execution->cancelled_at,
                    ),
                    'workflow_advanced_at' => $this->dueDatePresenter->formatDateTime($execution->workflow_advanced_at),
                ];

                if ($this->canViewWebhookMetadata()) {
                    $row['destination'] = $summary['destination'];
                }

                return $row;
            });
    }

    public function canViewWebhookMetadata(): bool
    {
        return app(RuntimeCapabilityGate::class)->outboundWebhookVisible()
            && WorkflowFilamentPermissions::can('webhook_metadata', 'view');
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    public function actionAttempts(WorkflowActionExecution $execution): Collection
    {
        return WorkflowActionAttempt::query()
            ->where('workflow_action_execution_id', $execution->getKey())
            ->orderBy('attempt_number')
            ->limit((int) config('dbflow-filament.runtime_detail_limit', 100))
            ->get()
            ->map(fn (WorkflowActionAttempt $attempt): array => $this->safeMetadataPresenter->attemptSummary($attempt));
    }
}
