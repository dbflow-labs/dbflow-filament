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

namespace DbflowLabs\Filament\Resources\WorkflowResource\Pages;

use DbflowLabs\Core\Actions\CreateWorkflowDraft;
use DbflowLabs\Filament\Resources\WorkflowResource;
use DbflowLabs\Filament\Support\MinimalWorkflowDefinitionFactory;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateWorkflow extends CreateRecord
{
    protected static string $resource = WorkflowResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (WorkflowResource::isUiBindingMode()) {
            $modelType = $data['model_type'] ?? null;

            if (is_string($modelType) && $modelType !== '') {
                $data['key'] = WorkflowResource::generateAutoKeyFromModelType($modelType)
                    ?? (string) ($data['key'] ?? '');
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $key = (string) ($data['key'] ?? '');
        $name = (string) ($data['name'] ?? '');
        $description = filled($data['description'] ?? null) ? (string) $data['description'] : null;

        $definition = MinimalWorkflowDefinitionFactory::withDefaultApprovalStep(
            MinimalWorkflowDefinitionFactory::forMetadata($key, $name, $description),
        );

        $workflow = app(CreateWorkflowDraft::class)->handle(
            $definition,
            Auth::id(),
        );

        $modelType = $data['model_type'] ?? null;

        if (is_string($modelType) && $modelType !== '') {
            $workflow->forceFill(['model_type' => $modelType])->save();
        }

        return $workflow;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title(__('dbflow-filament::dbflow-filament.notifications.definitions.draft_created'))
            ->body(__('dbflow-filament::dbflow-filament.notifications.definitions.draft_created_body'))
            ->success()
            ->send();
    }
}
