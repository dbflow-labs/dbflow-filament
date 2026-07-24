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

use DbflowLabs\Core\Models\WorkflowActionExecution;
use DbflowLabs\Filament\Pages\ViewWorkflowActionExecution;
use Filament\Actions\Action;

final class WorkflowActionExecutionTableActions
{
    public static function view(): Action
    {
        return Action::make('viewActionExecution')
            ->label((string) __('dbflow-filament::dbflow-filament.actions.view'))
            ->icon('heroicon-o-eye')
            ->url(fn (WorkflowActionExecution $record): string => ViewWorkflowActionExecution::getUrl(['record' => $record->getKey()]));
    }
}
