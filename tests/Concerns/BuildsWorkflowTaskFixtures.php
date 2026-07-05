<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Concerns;

use DbflowLabs\Core\Enums\ApprovalMode;
use DbflowLabs\Core\Enums\WorkflowInstanceStatus;
use DbflowLabs\Core\Enums\WorkflowTaskAssignmentStatus;
use DbflowLabs\Core\Enums\WorkflowTaskStatus;
use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Core\Models\WorkflowInstance;
use DbflowLabs\Core\Models\WorkflowTask;
use DbflowLabs\Core\Models\WorkflowTaskAssignment;
use DbflowLabs\Core\Models\WorkflowVersion;
use DbflowLabs\Filament\Tests\Models\TestWorkflowSubject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait BuildsWorkflowTaskFixtures
{
    protected function createPendingAssignmentForUserId(
        int|string $assigneeUserId,
        int|string $workflowableId = 1,
        string $workflowableType = TestWorkflowSubject::class,
        string $workflowName = 'Package Test Workflow',
        string $nodeKey = 'review',
        string $nodeName = 'Review Step',
        ?string $workflowKey = null,
    ): WorkflowTaskAssignment {
        return $this->createAssignmentForUserId(
            assigneeUserId: $assigneeUserId,
            workflowableId: $workflowableId,
            workflowableType: $workflowableType,
            workflowName: $workflowName,
            nodeKey: $nodeKey,
            nodeName: $nodeName,
            assignmentStatus: WorkflowTaskAssignmentStatus::Pending,
            taskStatus: WorkflowTaskStatus::Pending,
            workflowKey: $workflowKey,
        );
    }

    protected function createAssignmentForUserId(
        int|string $assigneeUserId,
        int|string $workflowableId = 1,
        string $workflowableType = TestWorkflowSubject::class,
        string $workflowName = 'Package Test Workflow',
        string $nodeKey = 'review',
        string $nodeName = 'Review Step',
        WorkflowTaskAssignmentStatus $assignmentStatus = WorkflowTaskAssignmentStatus::Pending,
        WorkflowTaskStatus $taskStatus = WorkflowTaskStatus::Pending,
        ?string $workflowKey = null,
    ): WorkflowTaskAssignment {
        $this->ensureUserExists($assigneeUserId);

        $subject = TestWorkflowSubject::query()->create();
        $workflowableId = $workflowableId === 1 && $workflowableType === TestWorkflowSubject::class
            ? $subject->getKey()
            : $workflowableId;

        $workflowKey ??= 'package_test_'.Str::lower(Str::random(8));

        $workflow = Workflow::query()->create([
            'key' => $workflowKey,
            'name' => $workflowName,
            'description' => 'Package workflow task fixture',
            'is_enabled' => true,
        ]);

        $version = WorkflowVersion::query()->create([
            'workflow_id' => $workflow->getKey(),
            'version' => 1,
            'definition' => $this->singleStepApprovalDefinition($workflowKey, $workflowName, $nodeKey, $nodeName),
            'is_active' => true,
            'published_at' => now(),
        ]);

        $instance = WorkflowInstance::query()->create([
            'workflow_id' => $workflow->getKey(),
            'workflow_version_id' => $version->getKey(),
            'workflowable_type' => $workflowableType,
            'workflowable_id' => (int) $workflowableId,
            'business_key' => null,
            'status' => WorkflowInstanceStatus::Running,
            'current_node_key' => $nodeKey,
            'started_by_user_id' => null,
            'started_at' => now(),
            'completed_at' => null,
            'cancelled_at' => null,
            'metadata' => null,
        ]);

        $task = WorkflowTask::query()->create([
            'workflow_instance_id' => $instance->getKey(),
            'node_key' => $nodeKey,
            'node_name' => $nodeName,
            'status' => $taskStatus,
            'approval_mode' => ApprovalMode::Any,
        ]);

        return WorkflowTaskAssignment::query()->create([
            'workflow_task_id' => $task->getKey(),
            'assignee_user_id' => $assigneeUserId,
            'status' => $assignmentStatus,
        ]);
    }

    protected function ensureUserExists(int|string $userId): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! is_int($userId) && ! is_numeric($userId)) {
            return;
        }

        if (! DB::table('users')->where('id', $userId)->exists()) {
            DB::table('users')->insert([
                'id' => $userId,
                'name' => 'Test User '.$userId,
                'email' => 'user'.$userId.'@example.com',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function singleStepApprovalDefinition(
        string $workflowKey,
        string $workflowName,
        string $nodeKey,
        string $nodeName,
    ): array {
        return [
            'key' => $workflowKey,
            'name' => $workflowName,
            'nodes' => [
                ['key' => 'start', 'type' => 'start', 'name' => 'Start'],
                [
                    'key' => $nodeKey,
                    'type' => 'approval',
                    'name' => $nodeName,
                    'approval_mode' => ApprovalMode::Any->value,
                    'assignee' => [
                        'type' => 'user_ids',
                        'value' => [],
                    ],
                ],
                ['key' => 'approved', 'type' => 'end', 'name' => 'Approved', 'outcome' => 'approved'],
            ],
            'transitions' => [
                ['from' => 'start', 'to' => $nodeKey, 'event' => 'start'],
                ['from' => $nodeKey, 'to' => 'approved', 'event' => 'approve'],
            ],
        ];
    }
}
