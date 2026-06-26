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

use DbflowLabs\Core\Models\WorkflowTaskAssignment;
use DbflowLabs\Filament\Support\Actions\MyWorkflowTaskActionRunner;
use DbflowLabs\Filament\Support\WorkflowFilamentPermissions;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

final class MyWorkflowTaskTableActions
{
    public static function approve(): Action
    {
        return Action::make('approveTask')
            ->label((string) __('dbflow-filament::dbflow-filament.actions.approve'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (WorkflowTaskAssignment $record): bool => self::canShowApprove($record, Auth::user()))
            ->form([
                Textarea::make('comment')
                    ->label((string) __('dbflow-filament::dbflow-filament.fields.comment'))
                    ->placeholder((string) __('dbflow-filament::dbflow-filament.fields.comment_placeholder'))
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->modalHeading((string) __('dbflow-filament::dbflow-filament.modals.approve.heading'))
            ->modalDescription((string) __('dbflow-filament::dbflow-filament.modals.approve.description'))
            ->action(function (WorkflowTaskAssignment $record, array $data): void {
                $user = Auth::user();

                if (! $user instanceof Authenticatable) {
                    return;
                }

                $comment = isset($data['comment']) && is_string($data['comment']) && $data['comment'] !== ''
                    ? $data['comment']
                    : null;

                $result = app(MyWorkflowTaskActionRunner::class)->approve($record, $user, $comment);

                self::notifyActionResult(
                    $result,
                    successTitle: (string) __('dbflow-filament::dbflow-filament.notifications.task_approved'),
                    failureTitle: (string) __('dbflow-filament::dbflow-filament.notifications.approve_failed'),
                );
            });
    }

    public static function reject(): Action
    {
        return Action::make('rejectTask')
            ->label((string) __('dbflow-filament::dbflow-filament.actions.reject'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (WorkflowTaskAssignment $record): bool => self::canShowReject($record, Auth::user()))
            ->form([
                Textarea::make('comment')
                    ->label((string) __('dbflow-filament::dbflow-filament.fields.rejection_reason'))
                    ->placeholder((string) __('dbflow-filament::dbflow-filament.fields.rejection_reason_placeholder'))
                    ->rows(3)
                    ->required((bool) config('dbflow-filament.require_reject_note', true))
                    ->validationMessages([
                        'required' => (string) __('dbflow-filament::dbflow-filament.validation.rejection_reason_required'),
                    ]),
            ])
            ->requiresConfirmation()
            ->modalHeading((string) __('dbflow-filament::dbflow-filament.modals.reject.heading'))
            ->modalDescription((string) __('dbflow-filament::dbflow-filament.modals.reject.description'))
            ->action(function (WorkflowTaskAssignment $record, array $data): void {
                $user = Auth::user();

                if (! $user instanceof Authenticatable) {
                    return;
                }

                $comment = isset($data['comment']) && is_string($data['comment']) ? $data['comment'] : null;

                $result = app(MyWorkflowTaskActionRunner::class)->reject($record, $user, $comment);

                self::notifyActionResult(
                    $result,
                    successTitle: (string) __('dbflow-filament::dbflow-filament.notifications.task_rejected'),
                    failureTitle: (string) __('dbflow-filament::dbflow-filament.notifications.reject_failed'),
                );
            });
    }

    public static function canShowApprove(WorkflowTaskAssignment $assignment, mixed $user): bool
    {
        if (! (bool) config('dbflow-filament.enable_my_task_actions', true)) {
            return false;
        }

        if (! MyWorkflowTaskActionRunner::canActOnAssignment($assignment, $user)) {
            return false;
        }

        return WorkflowFilamentPermissions::can('tasks', 'approve', $assignment, $user);
    }

    public static function canShowReject(WorkflowTaskAssignment $assignment, mixed $user): bool
    {
        if (! (bool) config('dbflow-filament.enable_my_task_actions', true)) {
            return false;
        }

        if (! MyWorkflowTaskActionRunner::canActOnAssignment($assignment, $user)) {
            return false;
        }

        return WorkflowFilamentPermissions::can('tasks', 'reject', $assignment, $user);
    }

    private static function notifyActionResult(
        WorkflowTaskActionResult $result,
        string $successTitle,
        string $failureTitle,
    ): void {
        if ($result->successful) {
            Notification::make()
                ->title($successTitle)
                ->success()
                ->send();

            return;
        }

        $body = match ($result->outcome) {
            WorkflowTaskActionResult::OUTCOME_TASK_NOT_AVAILABLE => (string) __('dbflow-filament::dbflow-filament.notifications.task_not_available_body'),
            default => $result->message ?? (string) __('dbflow-filament::dbflow-filament.notifications.action_failed_body'),
        };

        Notification::make()
            ->title(match ($result->outcome) {
                WorkflowTaskActionResult::OUTCOME_TASK_NOT_AVAILABLE => (string) __('dbflow-filament::dbflow-filament.notifications.task_not_available'),
                default => $failureTitle,
            })
            ->body($body)
            ->danger()
            ->send();
    }
}
