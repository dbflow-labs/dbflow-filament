<?php

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
