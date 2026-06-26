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

namespace DbflowLabs\Filament\Support\Queries;

use DbflowLabs\Core\Enums\WorkflowTaskAssignmentStatus;
use DbflowLabs\Core\Enums\WorkflowTaskStatus;
use DbflowLabs\Core\Models\WorkflowTaskAssignment;
use Illuminate\Database\Eloquent\Builder;

final class MyWorkflowTasksQuery
{
    /**
     * Pending workflow task assignments for the given user (read-only list source).
     *
     * @return Builder<WorkflowTaskAssignment>
     */
    public function pendingForUser(int|string $userId): Builder
    {
        return WorkflowTaskAssignment::query()
            ->where('assignee_user_id', $userId)
            ->where('status', WorkflowTaskAssignmentStatus::Pending)
            ->whereHas('workflowTask', function (Builder $query): void {
                $query->where('status', WorkflowTaskStatus::Pending);
            })
            ->with([
                'workflowTask.workflowInstance.workflow',
                'workflowTask.workflowInstance',
            ])
            ->orderByDesc('created_at');
    }
}
