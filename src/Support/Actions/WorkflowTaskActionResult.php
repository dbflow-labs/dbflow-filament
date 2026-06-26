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

final class WorkflowTaskActionResult
{
    public const OUTCOME_SUCCESS = 'success';

    public const OUTCOME_TASK_NOT_AVAILABLE = 'task_not_available';

    public const OUTCOME_WORKFLOW_ERROR = 'workflow_error';

    public function __construct(
        public readonly bool $successful,
        public readonly string $outcome = self::OUTCOME_SUCCESS,
        public readonly ?string $message = null,
    ) {}

    public static function success(): self
    {
        return new self(true);
    }

    public static function taskNotAvailable(?string $message = null): self
    {
        return new self(false, self::OUTCOME_TASK_NOT_AVAILABLE, $message);
    }

    public static function workflowError(?string $message = null): self
    {
        return new self(false, self::OUTCOME_WORKFLOW_ERROR, $message);
    }
}
