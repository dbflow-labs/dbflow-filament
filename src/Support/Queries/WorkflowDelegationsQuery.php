<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Support\Queries;

use DbflowLabs\Core\Models\WorkflowDelegation;
use Illuminate\Database\Eloquent\Builder;

final class WorkflowDelegationsQuery
{
    /**
     * @return Builder<WorkflowDelegation>
     */
    public function baseQuery(): Builder
    {
        return WorkflowDelegation::query()
            ->orderByDesc('id');
    }
}
