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

use DbflowLabs\Core\Enums\WorkflowStatus;
use DbflowLabs\Core\Models\Workflow;

final class WorkflowDefinitionStatusPresenter
{
    public static function lifecycleStatusKey(Workflow $record): string
    {
        if (! $record->hasDraft() && ! $record->hasPublishedVersion() && $record->lifecycleStatus() === WorkflowStatus::Draft) {
            return 'empty';
        }

        return $record->lifecycleStatus()->value;
    }

    public static function lifecycleStatusLabel(Workflow $record): string
    {
        $status = self::lifecycleStatusKey($record);

        return (string) __("dbflow-filament::dbflow-filament.resources.workflow_definitions.statuses.{$status}");
    }

    public static function lifecycleStatusColor(Workflow $record): string
    {
        return match (self::lifecycleStatusKey($record)) {
            'published' => 'success',
            'draft' => 'warning',
            'disabled' => 'danger',
            'archived' => 'gray',
            'empty' => 'gray',
            default => 'gray',
        };
    }

    public static function draftStatusKey(Workflow $record): string
    {
        if (! $record->hasDraft()) {
            return 'no_draft';
        }

        if ($record->draftIsValid()) {
            return 'valid_draft';
        }

        return 'invalid_draft';
    }

    public static function draftStatusLabel(string $status): string
    {
        return (string) __("dbflow-filament::dbflow-filament.resources.workflow_definitions.draft_statuses.{$status}");
    }

    public static function draftStatusColor(string $status): string
    {
        return match ($status) {
            'valid_draft' => 'success',
            'invalid_draft' => 'danger',
            'no_draft' => 'gray',
            default => 'gray',
        };
    }
}
