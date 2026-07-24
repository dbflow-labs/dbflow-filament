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

namespace DbflowLabs\Filament\Pages;

use BackedEnum;
use DbflowLabs\Core\Models\WorkflowInstance;
use DbflowLabs\Core\Models\WorkflowTask;
use DbflowLabs\Core\Models\WorkflowTaskAssignment;
use DbflowLabs\Core\Support\WorkflowDefinitionDisplay;
use DbflowLabs\Filament\Contracts\StatusBadgeMapper;
use DbflowLabs\Filament\Contracts\UserDisplayResolver;
use DbflowLabs\Filament\Contracts\WorkflowableLabelResolver;
use DbflowLabs\Filament\Support\Actions\WorkflowInstanceHeaderActions;
use DbflowLabs\Filament\Support\Presenters\DueDatePresenter;
use DbflowLabs\Filament\Support\Presenters\WorkflowInstanceDetailPresenter;
use DbflowLabs\Filament\Support\Presenters\WorkflowInstanceRuntimePresenter;
use DbflowLabs\Filament\Support\Presenters\WorkflowInstanceTimelinePresenter;
use DbflowLabs\Filament\Support\RuntimeCapabilityGate;
use DbflowLabs\Filament\Support\WorkflowFilamentPermissions;
use Filament\Pages\Page;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ViewWorkflowInstance extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = null;

    protected string $view = 'dbflow-filament::pages.view-workflow-instance';

    public ?WorkflowInstance $instance = null;

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        $prefix = trim((string) config('dbflow-filament.route_prefix', 'dbflow'), '/');

        return $prefix.'/workflow-instances/{record}';
    }

    public static function canAccess(): bool
    {
        if (! (bool) config('dbflow-filament.enabled', true)) {
            return false;
        }

        $user = Auth::user();

        if ($user instanceof Authenticatable) {
            return WorkflowFilamentPermissions::can('workflow_instances', 'view', user: $user);
        }

        return WorkflowFilamentPermissions::can('workflow_instances', 'view', user: null);
    }

    public function mount(int $record): void
    {
        $this->instance = WorkflowInstance::query()
            ->with([
                'workflow',
                'workflowVersion',
                'startedBy',
                'tasks.assignments.delegation',
            ])
            ->findOrFail($record);
    }

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        if (! (bool) config('dbflow-filament.enable_instance_cancel_action', true)) {
            return [];
        }

        return [
            WorkflowInstanceHeaderActions::cancel(fn (): ?WorkflowInstance => $this->instance),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return (string) __('dbflow-filament::dbflow-filament.pages.view_instance.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->instance === null) {
            return null;
        }

        $workflowName = app(WorkflowDefinitionDisplay::class)->workflowName(
            $this->instance->workflow?->key,
            $this->instance->workflow?->name,
        );

        return (string) __('dbflow-filament::dbflow-filament.pages.view_instance.subheading', [
            'workflow' => $workflowName,
            'id' => (string) $this->instance->getKey(),
        ]);
    }

    /**
     * @return array<int|string, string|Htmlable>
     */
    public function getBreadcrumbs(): array
    {
        return [
            WorkflowInstances::getUrl() => WorkflowInstances::getNavigationLabel(),
            $this->getTitle(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function instanceOverview(): array
    {
        if ($this->instance === null) {
            return [];
        }

        $workflowableLabelResolver = app(WorkflowableLabelResolver::class);
        $userDisplayResolver = app(UserDisplayResolver::class);
        $statusBadgeMapper = app(StatusBadgeMapper::class);
        $workflowDefinitionDisplay = app(WorkflowDefinitionDisplay::class);
        $instance = $this->instance;
        $workflowKey = $instance->workflow?->key;
        $statusValue = $instance->status !== null
            ? (is_object($instance->status) && property_exists($instance->status, 'value')
                ? (string) $instance->status->value
                : (string) $instance->status)
            : null;

        return [
            (string) __('dbflow-filament::dbflow-filament.pages.view_instance.overview.workflow_name') => $workflowDefinitionDisplay->workflowName($workflowKey, $instance->workflow?->name),
            (string) __('dbflow-filament::dbflow-filament.pages.view_instance.overview.workflow_key') => (string) ($instance->workflow?->key ?? '—'),
            (string) __('dbflow-filament::dbflow-filament.pages.view_instance.overview.version') => (string) ($instance->workflowVersion?->version ?? '—'),
            (string) __('dbflow-filament::dbflow-filament.pages.view_instance.overview.status') => $statusValue !== null
                ? $statusBadgeMapper->labelFor($statusValue)
                : '—',
            (string) __('dbflow-filament::dbflow-filament.pages.view_instance.overview.current_node') => $workflowDefinitionDisplay->nodeLabel($workflowKey, $instance->current_node_key),
            (string) __('dbflow-filament::dbflow-filament.pages.view_instance.overview.subject_type') => $workflowableLabelResolver->morphTypeLabel($instance->workflowable_type),
            (string) __('dbflow-filament::dbflow-filament.pages.view_instance.overview.subject') => $this->subjectLabel($instance, $workflowableLabelResolver),
            (string) __('dbflow-filament::dbflow-filament.pages.view_instance.overview.started_by') => $userDisplayResolver->displayName($instance->startedBy),
            (string) __('dbflow-filament::dbflow-filament.pages.view_instance.overview.started_at') => $this->formatDateTime($instance->started_at),
            (string) __('dbflow-filament::dbflow-filament.pages.view_instance.overview.completed_at') => $this->formatDateTime($instance->completed_at),
            (string) __('dbflow-filament::dbflow-filament.pages.view_instance.overview.created_at') => $this->formatDateTime($instance->created_at),
            (string) __('dbflow-filament::dbflow-filament.pages.view_instance.overview.updated_at') => $this->formatDateTime($instance->updated_at),
        ];
    }

    /**
     * @return Collection<int, array{
     *     node_key: string,
     *     status: string,
     *     assigned_at: string,
     *     completed_at: string,
     *     result_comment: string,
     * }>
     */
    public function tasksForDisplay(): Collection
    {
        if ($this->instance === null) {
            return collect();
        }

        $presenter = app(WorkflowInstanceDetailPresenter::class);
        $statusBadgeMapper = app(StatusBadgeMapper::class);
        $workflowDefinitionDisplay = app(WorkflowDefinitionDisplay::class);
        $dueDatePresenter = app(DueDatePresenter::class);
        $workflowKey = $this->instance->workflow?->key;

        return $this->instance->tasks
            ->sortBy('id')
            ->values()
            ->map(function (WorkflowTask $task) use ($presenter, $statusBadgeMapper, $workflowDefinitionDisplay, $dueDatePresenter, $workflowKey): array {
                $comment = $presenter->taskComment($task);
                $statusValue = $task->status !== null
                    ? (is_object($task->status) && property_exists($task->status, 'value')
                        ? (string) $task->status->value
                        : (string) $task->status)
                    : null;

                return [
                    'node_key' => $workflowDefinitionDisplay->nodeLabel($workflowKey, $task->node_key, $task->node_name),
                    'status' => $statusValue !== null ? $statusBadgeMapper->labelFor($statusValue) : '—',
                    'assigned_at' => $this->formatDateTime($presenter->taskAssignedAt($task)),
                    'due_at' => $dueDatePresenter->formatDateTime($task->due_at),
                    'time_remaining' => $dueDatePresenter->remainingLabel($task) ?? '—',
                    'completed_at' => $this->formatDateTime($task->completed_at),
                    'result_comment' => $comment ?? ($statusValue !== null ? $statusBadgeMapper->labelFor($statusValue) : '—'),
                ];
            });
    }

    /**
     * @return Collection<int, array{
     *     assignee_name: string,
     *     status: string,
     *     sequence: string,
     *     acted_at: string,
     *     comment: string,
     * }>
     */
    public function assignmentsForDisplay(): Collection
    {
        if ($this->instance === null) {
            return collect();
        }

        $presenter = app(WorkflowInstanceDetailPresenter::class);
        $userDisplayResolver = app(UserDisplayResolver::class);
        $statusBadgeMapper = app(StatusBadgeMapper::class);

        return $this->instance->tasks
            ->flatMap(fn (WorkflowTask $task) => $task->assignments)
            ->sortBy('id')
            ->values()
            ->map(function (WorkflowTaskAssignment $assignment) use ($presenter, $userDisplayResolver, $statusBadgeMapper): array {
                $comment = $presenter->assignmentComment($assignment);
                $statusValue = $assignment->status !== null
                    ? (is_object($assignment->status) && property_exists($assignment->status, 'value')
                        ? (string) $assignment->status->value
                        : (string) $assignment->status)
                    : null;

                return [
                    'assignee_name' => $userDisplayResolver->displayName($assignment->assignee),
                    'status' => $statusValue !== null ? $statusBadgeMapper->labelFor($statusValue) : '—',
                    'sequence' => $assignment->sequence !== null ? (string) $assignment->sequence : '—',
                    'acted_at' => $this->formatDateTime($assignment->acted_at),
                    'comment' => $comment ?? '—',
                ];
            });
    }

    /**
     * @return list<array{
     *     event: string,
     *     event_label: string,
     *     actor_name: string,
     *     from_node: string,
     *     to_node: string,
     *     task_node: string,
     *     comment: string,
     *     created_at: string,
     * }>
     */
    public function timelineForDisplay(): array
    {
        if ($this->instance === null) {
            return [];
        }

        if (! (bool) config('dbflow-filament.enable_logs_timeline', true)) {
            return [];
        }

        return app(WorkflowInstanceTimelinePresenter::class)->timelineForInstance($this->instance);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, string>>
     */
    public function assignmentHistoryForDisplay(): \Illuminate\Support\Collection
    {
        if ($this->instance === null) {
            return collect();
        }

        return app(WorkflowInstanceRuntimePresenter::class)->assignmentHistory($this->instance);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, string>>
     */
    public function slaEventsForDisplay(): \Illuminate\Support\Collection
    {
        if ($this->instance === null) {
            return collect();
        }

        if (! app(RuntimeCapabilityGate::class)->slaVisible()) {
            return collect();
        }

        if (! WorkflowFilamentPermissions::can('sla_events', 'view')) {
            return collect();
        }

        return app(WorkflowInstanceRuntimePresenter::class)->slaEvents($this->instance);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, string>>
     */
    public function actionExecutionsForDisplay(): \Illuminate\Support\Collection
    {
        if ($this->instance === null) {
            return collect();
        }

        if (! app(RuntimeCapabilityGate::class)->reliableActionVisible()) {
            return collect();
        }

        if (! WorkflowFilamentPermissions::can('action_executions', 'view_any')) {
            return collect();
        }

        return app(WorkflowInstanceRuntimePresenter::class)->actionExecutions($this->instance);
    }

    /**
     * @return array<string, string>
     */
    public function actionExecutionColumnsForDisplay(): array
    {
        $columns = __('dbflow-filament::dbflow-filament.pages.view_instance.action_executions');

        if (! is_array($columns)) {
            return [];
        }

        if (! app(WorkflowInstanceRuntimePresenter::class)->canViewWebhookMetadata()) {
            unset($columns['destination']);
        }

        return $columns;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $configured = config('dbflow-filament.navigation_group');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return (string) __('dbflow-filament::dbflow-filament.navigation.group');
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return null;
    }

    protected function subjectLabel(WorkflowInstance $instance, WorkflowableLabelResolver $workflowableLabelResolver): string
    {
        $workflowable = $this->resolveWorkflowable($instance);

        if ($workflowable instanceof Model) {
            return $workflowableLabelResolver->labelFor($workflowable);
        }

        return sprintf(
            '%s #%s',
            $workflowableLabelResolver->morphTypeLabel($instance->workflowable_type),
            $workflowableLabelResolver->morphIdLabel($instance->workflowable_id),
        );
    }

    protected function resolveWorkflowable(WorkflowInstance $instance): ?Model
    {
        $workflowableType = $instance->workflowable_type;
        $workflowableId = $instance->workflowable_id;

        if ($workflowableType === null || $workflowableType === '' || $workflowableId === null) {
            return null;
        }

        $morphType = Relation::getMorphedModel($workflowableType) ?? $workflowableType;

        if (! is_string($morphType) || ! class_exists($morphType)) {
            return null;
        }

        /** @var class-string<Model> $morphType */
        return $morphType::query()->find($workflowableId);
    }

    protected function formatDateTime(mixed $value): string
    {
        return app(DueDatePresenter::class)->formatDateTime($value);
    }
}
