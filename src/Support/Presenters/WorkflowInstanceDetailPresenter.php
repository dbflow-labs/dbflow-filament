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
use DbflowLabs\Core\Models\WorkflowLog;
use DbflowLabs\Core\Models\WorkflowTask;
use DbflowLabs\Core\Models\WorkflowTaskAssignment;
use Illuminate\Support\Carbon;

class WorkflowInstanceDetailPresenter
{
    public function taskAssignedAt(WorkflowTask $task): ?Carbon
    {
        $task->loadMissing('assignments');

        /** @var Carbon|null $assignedAt */
        $assignedAt = $task->assignments->min('created_at');

        return $assignedAt;
    }

    public function taskComment(WorkflowTask $task): ?string
    {
        $comment = WorkflowLog::query()
            ->where('workflow_task_id', $task->getKey())
            ->whereIn('event', [
                WorkflowLogEvent::TaskApproved->value,
                WorkflowLogEvent::TaskRejected->value,
            ])
            ->whereNotNull('comment')
            ->orderByDesc('created_at')
            ->value('comment');

        return is_string($comment) && $comment !== '' ? $comment : null;
    }

    public function assignmentComment(WorkflowTaskAssignment $assignment): ?string
    {
        if ($assignment->assignee_user_id === null) {
            return null;
        }

        $comment = WorkflowLog::query()
            ->where('workflow_task_id', $assignment->workflow_task_id)
            ->where('actor_user_id', $assignment->assignee_user_id)
            ->whereIn('event', [
                WorkflowLogEvent::TaskApproved->value,
                WorkflowLogEvent::TaskRejected->value,
            ])
            ->whereNotNull('comment')
            ->orderByDesc('created_at')
            ->value('comment');

        return is_string($comment) && $comment !== '' ? $comment : null;
    }
}
