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

namespace DbflowLabs\Filament\Support\Editors;

/**
 * End node terminal statuses aligned with Pro canvas end_status options.
 *
 * Persisted to Core as {@see \DbflowLabs\Core\Definitions\WorkflowDefinitionSchema::CONFIG_STATUS}.
 */
final class StandardWorkflowEndStatuses
{
    public const COMPLETED = 'completed';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::COMPLETED,
            self::REJECTED,
            self::CANCELLED,
        ];
    }

    public static function normalize(mixed $value): string
    {
        $status = is_string($value) ? trim($value) : '';

        return in_array($status, self::all(), true) ? $status : self::COMPLETED;
    }
}
