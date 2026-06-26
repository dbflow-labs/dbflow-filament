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

use DbflowLabs\Core\Actions\ArchiveWorkflow;
use DbflowLabs\Core\Actions\CopyWorkflow;
use DbflowLabs\Core\Actions\DeleteWorkflow;
use DbflowLabs\Core\Actions\DisableWorkflow;
use DbflowLabs\Core\Actions\EnableWorkflow;
use DbflowLabs\Core\Exceptions\WorkflowInvalidStateException;
use DbflowLabs\Core\Exceptions\WorkflowKeyAlreadyExistsException;
use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Filament\Resources\WorkflowResource;
use DbflowLabs\Filament\Support\WorkflowDefinitionAuthorization;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

final class WorkflowDefinitionLifecycleActions
{
    public static function copyAction(bool $redirectToEdit = false): Action
    {
        return Action::make('copyWorkflow')
            ->label(__('dbflow-filament::dbflow-filament.actions.definitions.copy'))
            ->icon('heroicon-o-document-duplicate')
            ->visible(fn (): bool => WorkflowDefinitionAuthorization::can('copy'))
            ->form([
                TextInput::make('new_key')
                    ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.key'))
                    ->required()
                    ->maxLength(64)
                    ->regex('/^[a-z0-9_]+$/')
                    ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.key_helper')),
                TextInput::make('new_name')
                    ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.name'))
                    ->required()
                    ->maxLength(120),
            ])
            ->fillForm(fn (Workflow $record): array => [
                'new_key' => $record->key.'_copy',
                'new_name' => $record->name.' Copy',
            ])
            ->action(function (Workflow $record, array $data, Action $action) use ($redirectToEdit): void {
                try {
                    $copied = app(CopyWorkflow::class)->handle(
                        $record,
                        (string) ($data['new_key'] ?? ''),
                        (string) ($data['new_name'] ?? ''),
                        Auth::id(),
                    );

                    Notification::make()
                        ->title(__('dbflow-filament::dbflow-filament.notifications.definitions.workflow_copied'))
                        ->body(__('dbflow-filament::dbflow-filament.notifications.definitions.workflow_copied_to', [
                            'key' => $copied->key,
                        ]))
                        ->success()
                        ->send();

                    if ($redirectToEdit) {
                        $action->redirect(WorkflowResource::getUrl('edit', ['record' => $copied]));
                    }
                } catch (WorkflowKeyAlreadyExistsException|WorkflowInvalidStateException $exception) {
                    Notification::make()
                        ->title(__('dbflow-filament::dbflow-filament.notifications.definitions.workflow_copy_failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function disableAction(): Action
    {
        return Action::make('disableWorkflow')
            ->label(__('dbflow-filament::dbflow-filament.actions.definitions.disable'))
            ->icon('heroicon-o-no-symbol')
            ->color('warning')
            ->visible(fn (Workflow $record): bool => $record->canBeDisabled() && WorkflowDefinitionAuthorization::can('disable', $record))
            ->requiresConfirmation()
            ->modalHeading(__('dbflow-filament::dbflow-filament.actions.definitions.disable_confirm_heading'))
            ->modalDescription(__('dbflow-filament::dbflow-filament.actions.definitions.disable_confirm_description'))
            ->action(function (Workflow $record): void {
                try {
                    app(DisableWorkflow::class)->handle($record, Auth::id());

                    Notification::make()
                        ->title(__('dbflow-filament::dbflow-filament.notifications.definitions.workflow_disabled'))
                        ->success()
                        ->send();
                } catch (WorkflowInvalidStateException $exception) {
                    Notification::make()
                        ->title(__('dbflow-filament::dbflow-filament.notifications.definitions.workflow_disable_failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function enableAction(): Action
    {
        return Action::make('enableWorkflow')
            ->label(__('dbflow-filament::dbflow-filament.actions.definitions.enable'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Workflow $record): bool => $record->canBeEnabled() && WorkflowDefinitionAuthorization::can('enable', $record))
            ->action(function (Workflow $record): void {
                try {
                    app(EnableWorkflow::class)->handle($record, Auth::id());

                    Notification::make()
                        ->title(__('dbflow-filament::dbflow-filament.notifications.definitions.workflow_enabled'))
                        ->success()
                        ->send();
                } catch (WorkflowInvalidStateException $exception) {
                    Notification::make()
                        ->title(__('dbflow-filament::dbflow-filament.notifications.definitions.workflow_enable_failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function archiveAction(): Action
    {
        return Action::make('archiveWorkflow')
            ->label(__('dbflow-filament::dbflow-filament.actions.definitions.archive'))
            ->icon('heroicon-o-archive-box')
            ->color('gray')
            ->visible(fn (Workflow $record): bool => $record->canBeArchived() && WorkflowDefinitionAuthorization::can('archive', $record))
            ->requiresConfirmation()
            ->modalHeading(__('dbflow-filament::dbflow-filament.actions.definitions.archive_confirm_heading'))
            ->modalDescription(__('dbflow-filament::dbflow-filament.actions.definitions.archive_confirm_description'))
            ->action(function (Workflow $record): void {
                app(ArchiveWorkflow::class)->handle($record, Auth::id());

                Notification::make()
                    ->title(__('dbflow-filament::dbflow-filament.notifications.definitions.workflow_archived'))
                    ->success()
                    ->send();
            });
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->visible(fn (Workflow $record): bool => $record->canBeDeleted() && WorkflowDefinitionAuthorization::can('delete', $record))
            ->modalHeading(__('dbflow-filament::dbflow-filament.actions.definitions.delete_confirm_heading'))
            ->modalDescription(__('dbflow-filament::dbflow-filament.actions.definitions.delete_confirm_description'))
            ->successNotificationTitle(__('dbflow-filament::dbflow-filament.notifications.definitions.workflow_deleted'))
            ->using(function (Workflow $record): bool {
                try {
                    app(DeleteWorkflow::class)->handle($record);

                    return true;
                } catch (WorkflowInvalidStateException $exception) {
                    Notification::make()
                        ->title(__('dbflow-filament::dbflow-filament.notifications.definitions.workflow_delete_failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return false;
                }
            });
    }

    /**
     * @return list<Action|DeleteAction>
     */
    public static function lifecycleActions(bool $redirectCopyToEdit = false): array
    {
        return [
            self::copyAction($redirectCopyToEdit),
            self::disableAction(),
            self::enableAction(),
            self::archiveAction(),
        ];
    }

    public static function lifecycleActionGroup(bool $redirectCopyToEdit = false): ActionGroup
    {
        return ActionGroup::make(self::lifecycleActions($redirectCopyToEdit))
            ->label(__('dbflow-filament::dbflow-filament.actions.definitions.more'))
            ->icon('heroicon-m-ellipsis-horizontal')
            ->color('gray')
            ->button();
    }
}
