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

use DbflowLabs\Core\Models\WorkflowInstance;
use DbflowLabs\Filament\Pages\ViewWorkflowInstance;
use Filament\Actions\Action;

class WorkflowInstanceTableActions
{
    public static function view(): Action
    {
        return Action::make('view')
            ->label((string) __('dbflow-filament::dbflow-filament.actions.view'))
            ->icon('heroicon-o-eye')
            ->url(fn (WorkflowInstance $record): string => ViewWorkflowInstance::getUrl([
                'record' => $record->getKey(),
            ]));
    }
}
