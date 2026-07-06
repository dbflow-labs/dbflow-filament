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

use DbflowLabs\Core\DBFlow;
use DbflowLabs\Core\Enums\WorkflowInstanceStatus;
use DbflowLabs\Core\Exceptions\WorkflowException;
use DbflowLabs\Core\Models\WorkflowInstance;

final class WorkflowInstanceActionRunner
{
    public function cancel(WorkflowInstance $instance, mixed $actor, ?string $comment = null): WorkflowTaskActionResult
    {
        if ($instance->status !== WorkflowInstanceStatus::Running) {
            return WorkflowTaskActionResult::taskNotAvailable();
        }

        try {
            DBFlow::cancel($instance, $actor, $comment);

            return WorkflowTaskActionResult::success();
        } catch (WorkflowException $exception) {
            return WorkflowTaskActionResult::workflowError($exception->getMessage());
        }
    }
}
