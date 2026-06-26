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

use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Filament\Support\WorkflowDefinitionAuthorization;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

final class WorkflowDefinitionDraftActions
{
    public static function validateDraftAction(callable $refreshRecord, ?callable $resolveRecord = null): Action
    {
        return Action::make('validateDraft')
            ->label(__('dbflow-filament::dbflow-filament.actions.definitions.validate_draft'))
            ->visible(function (?Workflow $record = null) use ($resolveRecord): bool {
                $workflow = $record ?? ($resolveRecord !== null ? $resolveRecord() : null);

                return $workflow instanceof Workflow
                    && $workflow->canBePublished()
                    && WorkflowDefinitionAuthorization::can('validate', $workflow);
            })
            ->action(function (?Workflow $record = null) use ($refreshRecord, $resolveRecord): void {
                $workflow = $record ?? ($resolveRecord !== null ? $resolveRecord() : null);

                if (! $workflow instanceof Workflow) {
                    return;
                }

                $result = app(WorkflowDraftActionRunner::class)->validateDraft($workflow->refresh());
                $refreshRecord($workflow->refresh());
                self::notify($result);
            });
    }

    public static function publishDraftAction(callable $refreshRecord, ?callable $resolveRecord = null): Action
    {
        return Action::make('publishDraft')
            ->label(__('dbflow-filament::dbflow-filament.actions.definitions.publish_draft'))
            ->visible(function (?Workflow $record = null) use ($resolveRecord): bool {
                $workflow = $record ?? ($resolveRecord !== null ? $resolveRecord() : null);

                return $workflow instanceof Workflow
                    && $workflow->canBePublished()
                    && WorkflowDefinitionAuthorization::can('publish', $workflow);
            })
            ->requiresConfirmation()
            ->modalHeading(__('dbflow-filament::dbflow-filament.actions.definitions.publish_draft_confirm_heading'))
            ->modalDescription(__('dbflow-filament::dbflow-filament.actions.definitions.publish_draft_confirm_description'))
            ->action(function (?Workflow $record = null) use ($refreshRecord, $resolveRecord): void {
                $workflow = $record ?? ($resolveRecord !== null ? $resolveRecord() : null);

                if (! $workflow instanceof Workflow) {
                    return;
                }

                $result = app(WorkflowDraftActionRunner::class)->publishDraft($workflow->refresh(), Auth::id());
                $refreshRecord($workflow->refresh());
                self::notify($result);
            });
    }

    public static function notify(WorkflowDefinitionActionResult $result): void
    {
        $notification = Notification::make()->title(__($result->titleKey));

        if ($result->bodyKey !== null) {
            $notification->body(__($result->bodyKey, $result->bodyReplacements));
        }

        match ($result->level) {
            'warning' => $notification->warning(),
            'danger' => $notification->danger(),
            default => $notification->success(),
        };

        $notification->send();
    }
}
