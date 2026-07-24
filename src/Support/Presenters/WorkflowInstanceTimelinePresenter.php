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

use DbflowLabs\Core\Enums\WorkflowLogEvent;
use DbflowLabs\Core\Models\WorkflowInstance;
use DbflowLabs\Core\Models\WorkflowLog;
use DbflowLabs\Core\Models\WorkflowTask;
use DbflowLabs\Core\Support\DbflowAuth;
use DbflowLabs\Core\Support\WorkflowDefinitionDisplay;
use DbflowLabs\Filament\Contracts\UserDisplayResolver;
use Illuminate\Support\Carbon;

class WorkflowInstanceTimelinePresenter
{
    /**
     * @var list<string>
     */
    private const HIDDEN_EVENTS = [
        WorkflowLogEvent::TaskSkipped->value,
    ];

    public function __construct(
        private readonly UserDisplayResolver $userDisplayResolver,
    ) {}

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
    public function timelineForInstance(WorkflowInstance $instance): array
    {
        $workflowKey = $instance->workflow?->key;

        return WorkflowLog::query()
            ->where('workflow_instance_id', $instance->getKey())
            ->with(['workflowTask', 'actor'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->reject(fn (WorkflowLog $log): bool => in_array($this->resolveEventValue($log), self::HIDDEN_EVENTS, true))
            ->map(fn (WorkflowLog $log): array => $this->mapLogToTimelineItem($log, $workflowKey))
            ->values()
            ->all();
    }

    public function eventLabel(string $event): string
    {
        $translationKey = 'dbflow-filament::dbflow-filament.timeline.'.$event;
        $translated = __($translationKey);

        if ($translated !== $translationKey) {
            return (string) $translated;
        }

        foreach (WorkflowLogEvent::cases() as $case) {
            if ($case->value === $event) {
                return $case->label();
            }
        }

        return (string) __('dbflow-filament::dbflow-filament.timeline.unknown');
    }

    public function actorDisplayName(int|string|null $actorUserId): string
    {
        if ($actorUserId === null) {
            return (string) __('dbflow-filament::dbflow-filament.labels.system');
        }

        $userModelClass = DbflowAuth::userModelClass();
        $user = $userModelClass::query()->find($actorUserId);

        return $this->userDisplayResolver->displayName($user);
    }

    /**
     * @return array{
     *     event: string,
     *     event_label: string,
     *     actor_name: string,
     *     from_node: string,
     *     to_node: string,
     *     task_node: string,
     *     comment: string,
     *     created_at: string,
     * }
     */
    private function mapLogToTimelineItem(WorkflowLog $log, ?string $workflowKey): array
    {
        $event = $this->resolveEventValue($log);
        $payload = is_array($log->payload) ? $log->payload : [];
        $workflowDefinitionDisplay = app(WorkflowDefinitionDisplay::class);

        return [
            'event' => $event,
            'event_label' => $this->eventLabel($event),
            'actor_name' => $this->actorDisplayName($log->actor_user_id),
            'from_node' => $this->displayNode($workflowDefinitionDisplay, $workflowKey, $this->resolvePayloadNode($payload, 'from_node', 'from')),
            'to_node' => $this->displayNode($workflowDefinitionDisplay, $workflowKey, $this->resolvePayloadNode($payload, 'to_node', 'to', 'node_key')),
            'task_node' => $this->displayTaskNode($workflowDefinitionDisplay, $workflowKey, $log),
            'comment' => $this->displayComment($this->resolveComment($log, $payload)),
            'created_at' => $log->created_at instanceof Carbon
                ? $log->created_at->format((string) config('dbflow-filament.date_time_format', 'Y-m-d H:i:s'))
                : '—',
        ];
    }

    private function resolveEventValue(WorkflowLog $log): string
    {
        $event = $log->event;

        if ($event instanceof WorkflowLogEvent) {
            return $event->value;
        }

        return is_string($event) && $event !== '' ? $event : 'unknown';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveComment(WorkflowLog $log, array $payload): ?string
    {
        if (is_string($log->comment) && $log->comment !== '') {
            return $log->comment;
        }

        foreach (['comment', 'reason', 'rejection_reason'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        if ($this->resolveEventValue($log) !== WorkflowLogEvent::TaskCreated->value) {
            return null;
        }

        $assigneeUserIds = $payload['assignee_user_ids'] ?? null;

        if (! is_array($assigneeUserIds) || $assigneeUserIds === []) {
            return null;
        }

        $names = [];

        foreach ($assigneeUserIds as $assigneeUserId) {
            $names[] = $this->actorDisplayName(is_int($assigneeUserId) || is_string($assigneeUserId) ? $assigneeUserId : null);
        }

        return implode(', ', $names);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function resolvePayloadNode(array $payload, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function displayTaskNode(WorkflowDefinitionDisplay $workflowDefinitionDisplay, ?string $workflowKey, WorkflowLog $log): string
    {
        $task = $log->relationLoaded('workflowTask')
            ? $log->workflowTask
            : $log->workflowTask()->first();

        if (! $task instanceof WorkflowTask) {
            return '—';
        }

        return $workflowDefinitionDisplay->nodeLabel(
            $workflowKey,
            is_string($task->node_key) && $task->node_key !== '' ? $task->node_key : null,
            is_string($task->node_name) && $task->node_name !== '' ? $task->node_name : null,
        );
    }

    private function displayNode(WorkflowDefinitionDisplay $workflowDefinitionDisplay, ?string $workflowKey, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return $workflowDefinitionDisplay->nodeLabel($workflowKey, $value, $value);
    }

    private function displayComment(?string $value): string
    {
        return $value !== null && $value !== '' ? $value : '—';
    }
}
