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

namespace DbflowLabs\Filament\Support;

use DbflowLabs\Core\Contracts\WorkflowRouteResolvable;
use Illuminate\Database\Eloquent\Model;

final class WorkflowableShowUrlResolver
{
    public function resolve(?Model $workflowable): ?string
    {
        if (! $workflowable instanceof WorkflowRouteResolvable) {
            return null;
        }

        $url = $workflowable->getWorkflowShowUrl();

        if ($url === null || $url === '') {
            return null;
        }

        return $url;
    }
}
