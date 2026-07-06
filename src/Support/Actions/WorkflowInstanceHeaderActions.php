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

use Closure;
use DbflowLabs\Core\Enums\WorkflowInstanceStatus;
use DbflowLabs\Core\Models\WorkflowInstance;
use DbflowLabs\Core\Support\DbflowRuntime;
use DbflowLabs\Filament\Support\WorkflowFilamentPermissions;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

final class WorkflowInstanceHeaderActions
{
    /**
     * @param  Closure(): (?WorkflowInstance)  $resolveInstance
     */
    public static function cancel(Closure $resolveInstance): Action
    {
        return Action::make('cancelWorkflowInstance')
            ->label((string) __('dbflow-filament::dbflow-filament.actions.cancel'))
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(function () use ($resolveInstance): bool {
                $instance = $resolveInstance();

                return self::canShowCancel($instance, Auth::user());
            })
            ->form([
                Textarea::make('comment')
                    ->label((string) __('dbflow-filament::dbflow-filament.fields.cancel_reason'))
                    ->placeholder((string) __('dbflow-filament::dbflow-filament.fields.cancel_reason_placeholder'))
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->modalHeading((string) __('dbflow-filament::dbflow-filament.modals.cancel_instance.heading'))
            ->modalDescription((string) __('dbflow-filament::dbflow-filament.modals.cancel_instance.description'))
            ->action(function (array $data) use ($resolveInstance): void {
                $user = Auth::user();
                $instance = $resolveInstance();

                if (! $user instanceof Authenticatable || ! $instance instanceof WorkflowInstance) {
                    return;
                }

                $comment = isset($data['comment']) && is_string($data['comment']) && $data['comment'] !== ''
                    ? $data['comment']
                    : null;

                $result = app(WorkflowInstanceActionRunner::class)->cancel($instance, $user, $comment);

                if ($result->successful) {
                    Notification::make()
                        ->title((string) __('dbflow-filament::dbflow-filament.notifications.workflow_cancelled'))
                        ->success()
                        ->send();

                    return;
                }

                $body = match ($result->outcome) {
                    WorkflowTaskActionResult::OUTCOME_TASK_NOT_AVAILABLE => (string) __('dbflow-filament::dbflow-filament.notifications.instance_not_cancellable_body'),
                    default => $result->message ?? (string) __('dbflow-filament::dbflow-filament.notifications.action_failed_body'),
                };

                Notification::make()
                    ->title((string) __('dbflow-filament::dbflow-filament.notifications.cancel_failed'))
                    ->body($body)
                    ->danger()
                    ->send();
            });
    }

    public static function canShowCancel(?WorkflowInstance $instance, mixed $user): bool
    {
        if (! DbflowRuntime::isEnabled()) {
            return false;
        }

        if (! (bool) config('dbflow-filament.enable_instance_cancel_action', true)) {
            return false;
        }

        if (! $instance instanceof WorkflowInstance || $instance->status !== WorkflowInstanceStatus::Running) {
            return false;
        }

        return WorkflowFilamentPermissions::can('workflow_instances', 'cancel', $instance, $user);
    }
}
