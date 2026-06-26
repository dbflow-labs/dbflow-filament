<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Concerns;

use DbflowLabs\Core\Enums\ApprovalMode;
use DbflowLabs\Core\Enums\WorkflowInstanceStatus;
use DbflowLabs\Core\Enums\WorkflowLogEvent;
use DbflowLabs\Core\Enums\WorkflowTaskAssignmentStatus;
use DbflowLabs\Core\Enums\WorkflowTaskStatus;
use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Core\Models\WorkflowInstance;
use DbflowLabs\Core\Models\WorkflowLog;
use DbflowLabs\Core\Models\WorkflowTask;
use DbflowLabs\Core\Models\WorkflowTaskAssignment;
use DbflowLabs\Core\Models\WorkflowVersion;
use DbflowLabs\Filament\Tests\Models\TestWorkflowSubject;
use Illuminate\Support\Str;

trait BuildsWorkflowInstanceFixtures
{
    protected function createWorkflowInstance(
        string $workflowName = 'Package Instance Workflow',
        ?string $workflowKey = null,
        int|string|null $startedByUserId = null,
        WorkflowInstanceStatus $status = WorkflowInstanceStatus::Running,
        string $currentNodeKey = 'review',
    ): WorkflowInstance {
        $workflowKey ??= 'package_instance_'.Str::lower(Str::random(8));

        $workflow = Workflow::query()->create([
            'key' => $workflowKey,
            'name' => $workflowName,
            'description' => 'Package workflow instance fixture',
            'is_enabled' => true,
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_id' => $workflow->getKey(),
            'version' => 1,
            'definition' => [
                'key' => $workflowKey,
                'name' => $workflowName,
                'nodes' => [
                    ['key' => 'review', 'type' => 'approval', 'name' => 'Review Step'],
                    ['key' => 'approved', 'type' => 'end', 'name' => 'Approved'],
                ],
                'transitions' => [],
            ],
            'is_active' => true,
            'published_at' => now(),
        ]);

        $subject = TestWorkflowSubject::query()->create();

        if ($startedByUserId !== null) {
            $this->ensureUserExists($startedByUserId);
        }

        return WorkflowInstance::query()->create([
            'workflow_id' => $workflow->getKey(),
            'workflow_version_id' => $version->getKey(),
            'workflowable_type' => TestWorkflowSubject::class,
            'workflowable_id' => (int) $subject->getKey(),
            'business_key' => null,
            'status' => $status,
            'current_node_key' => $currentNodeKey,
            'started_by_user_id' => $startedByUserId,
            'started_at' => now()->subHour(),
            'completed_at' => null,
            'cancelled_at' => null,
            'metadata' => null,
        ]);
    }

    protected function createWorkflowTask(
        WorkflowInstance $instance,
        string $nodeKey = 'review',
        string $nodeName = 'Review Step',
        WorkflowTaskStatus $taskStatus = WorkflowTaskStatus::Pending,
    ): WorkflowTask {
        return WorkflowTask::query()->create([
            'workflow_instance_id' => $instance->getKey(),
            'node_key' => $nodeKey,
            'node_name' => $nodeName,
            'status' => $taskStatus,
            'approval_mode' => ApprovalMode::Any,
        ]);
    }

    protected function createWorkflowLog(
        WorkflowInstance $instance,
        WorkflowLogEvent $event,
        ?WorkflowTask $task = null,
        int|string|null $actorUserId = null,
        ?string $comment = null,
        ?array $payload = null,
        ?\DateTimeInterface $createdAt = null,
    ): WorkflowLog {
        if ($actorUserId !== null) {
            $this->ensureUserExists($actorUserId);
        }

        return WorkflowLog::query()->create([
            'workflow_instance_id' => $instance->getKey(),
            'workflow_task_id' => $task?->getKey(),
            'event' => $event->value,
            'actor_user_id' => $actorUserId,
            'comment' => $comment,
            'payload' => $payload,
            'created_at' => $createdAt ?? now(),
        ]);
    }

    protected function createWorkflowTaskAssignment(
        WorkflowTask $task,
        int|string $assigneeUserId,
        WorkflowTaskAssignmentStatus $status = WorkflowTaskAssignmentStatus::Pending,
    ): WorkflowTaskAssignment {
        $this->ensureUserExists($assigneeUserId);

        return WorkflowTaskAssignment::query()->create([
            'workflow_task_id' => $task->getKey(),
            'assignee_user_id' => $assigneeUserId,
            'status' => $status,
        ]);
    }
}
