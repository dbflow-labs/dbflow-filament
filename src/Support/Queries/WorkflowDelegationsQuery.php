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
