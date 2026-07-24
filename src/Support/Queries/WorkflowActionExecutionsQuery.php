<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Support\Queries;

use DbflowLabs\Core\Models\WorkflowActionExecution;
use Illuminate\Database\Eloquent\Builder;

final class WorkflowActionExecutionsQuery
{
    /**
     * @return Builder<WorkflowActionExecution>
     */
    public function baseQuery(): Builder
    {
        return WorkflowActionExecution::query()
            ->with([
                'workflowInstance.workflow',
            ])
            ->withCount('attempts')
            ->orderByDesc('id');
    }
}
