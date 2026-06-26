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

namespace DbflowLabs\Filament\Support\Actions;

use DbflowLabs\Core\Actions\ApproveTask;
use DbflowLabs\Core\Actions\RejectTask;
use DbflowLabs\Core\Enums\RejectStrategy;
use DbflowLabs\Core\Enums\WorkflowTaskAssignmentStatus;
use DbflowLabs\Core\Enums\WorkflowTaskStatus;
use DbflowLabs\Core\Exceptions\TaskNotPendingException;
use DbflowLabs\Core\Exceptions\UserCannotApproveTaskException;
use DbflowLabs\Core\Exceptions\WorkflowException;
use DbflowLabs\Core\Models\WorkflowTask;
use DbflowLabs\Core\Models\WorkflowTaskAssignment;
use Illuminate\Contracts\Auth\Authenticatable;

final class MyWorkflowTaskActionRunner
{
    public function __construct(
        private readonly ApproveTask $approveTask,
        private readonly RejectTask $rejectTask,
    ) {}

    public function approve(WorkflowTaskAssignment $assignment, mixed $actor, ?string $comment = null): WorkflowTaskActionResult
    {
        $task = $this->resolvePendingTaskForUser($assignment, $actor);

        if ($task === null) {
            return WorkflowTaskActionResult::taskNotAvailable();
        }

        try {
            $this->approveTask->handle($task, $actor, $comment);

            return WorkflowTaskActionResult::success();
        } catch (TaskNotPendingException|UserCannotApproveTaskException) {
            return WorkflowTaskActionResult::taskNotAvailable();
        } catch (WorkflowException $exception) {
            return WorkflowTaskActionResult::workflowError($exception->getMessage());
        }
    }

    public function reject(WorkflowTaskAssignment $assignment, mixed $actor, ?string $comment): WorkflowTaskActionResult
    {
        $task = $this->resolvePendingTaskForUser($assignment, $actor);

        if ($task === null) {
            return WorkflowTaskActionResult::taskNotAvailable();
        }

        try {
            $this->rejectTask->handle(
                $task,
                $actor,
                $comment,
                $this->resolveRejectStrategy(),
            );

            return WorkflowTaskActionResult::success();
        } catch (TaskNotPendingException|UserCannotApproveTaskException) {
            return WorkflowTaskActionResult::taskNotAvailable();
        } catch (WorkflowException $exception) {
            return WorkflowTaskActionResult::workflowError($exception->getMessage());
        }
    }

    public static function canActOnAssignment(WorkflowTaskAssignment $assignment, mixed $user): bool
    {
        if (! $user instanceof Authenticatable) {
            return false;
        }

        if ($assignment->status !== WorkflowTaskAssignmentStatus::Pending) {
            return false;
        }

        if ((int) $assignment->assignee_user_id !== (int) $user->getKey()) {
            return false;
        }

        $task = $assignment->relationLoaded('workflowTask')
            ? $assignment->workflowTask
            : $assignment->workflowTask()->first();

        return $task instanceof WorkflowTask
            && $task->status === WorkflowTaskStatus::Pending;
    }

    private function resolvePendingTaskForUser(WorkflowTaskAssignment $assignment, mixed $actor): ?WorkflowTask
    {
        $assignment = $assignment->fresh(['workflowTask']);

        if ($assignment === null || ! self::canActOnAssignment($assignment, $actor)) {
            return null;
        }

        return $assignment->workflowTask;
    }

    private function resolveRejectStrategy(): RejectStrategy
    {
        $configured = config('dbflow-filament.reject_strategy', RejectStrategy::End->value);

        if ($configured instanceof RejectStrategy) {
            return $configured;
        }

        return RejectStrategy::tryFrom((string) $configured) ?? RejectStrategy::End;
    }
}
