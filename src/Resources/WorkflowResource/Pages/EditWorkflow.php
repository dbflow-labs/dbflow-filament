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

use DbflowLabs\Core\Actions\SaveWorkflowDraft;
use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Filament\Resources\WorkflowResource;
use DbflowLabs\Filament\Support\Actions\WorkflowDefinitionDraftActions;
use DbflowLabs\Filament\Support\Editors\LinearApprovalDefinitionMapper;
use DbflowLabs\Filament\Support\Editors\StandardWorkflowDefinitionMapper;
use DbflowLabs\Filament\Support\Editors\StandardWorkflowStepTypes;
use DbflowLabs\Filament\Support\Actions\WorkflowDefinitionLifecycleActions;
use DbflowLabs\Filament\Support\WorkflowDefinitionFormValidationMapper;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use JsonException;

class EditWorkflow extends EditRecord
{
    protected static string $resource = WorkflowResource::class;

    private bool $savedViaDefinitionJson = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                ...WorkflowResource::metadataFormComponents(),
                WorkflowResource::definitionEditorSection('edit'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            WorkflowDefinitionDraftActions::validateDraftAction(
                fn (Workflow $workflow): mixed => $this->refreshWorkflowRecord($workflow),
                fn (): Workflow => $this->getRecord(),
            ),
            WorkflowDefinitionDraftActions::publishDraftAction(
                fn (Workflow $workflow): mixed => $this->refreshWorkflowRecord($workflow),
                fn (): Workflow => $this->getRecord(),
            ),
            WorkflowDefinitionLifecycleActions::lifecycleActionGroup(redirectCopyToEdit: true),
            WorkflowDefinitionLifecycleActions::deleteAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Workflow $record */
        $record = $this->getRecord();

        if ($record->hasDraft()) {
            $draftDefinition = $record->draftDefinition();

            $formState = StandardWorkflowDefinitionMapper::parseFormState($draftDefinition);

            if ($formState !== null) {
                $data['workflow_steps'] = $formState['workflow_steps'];
                $data['end_outcomes'] = $formState['end_outcomes'];
            } elseif (($legacySteps = LinearApprovalDefinitionMapper::parseApprovalSteps($draftDefinition)) !== null) {
                $data['workflow_steps'] = array_map(
                    static fn (array $step): array => [
                        'type' => StandardWorkflowStepTypes::APPROVAL,
                        'data' => $step,
                    ],
                    $legacySteps,
                );
                $data['end_outcomes'] = StandardWorkflowDefinitionMapper::parseEndOutcomes($draftDefinition) ?? [];
            }

            try {
                $data['definition_json'] = json_encode(
                    $draftDefinition,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                );
            } catch (JsonException) {
                $data['definition_json'] = '';
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Workflow $workflow */
        $workflow = $record;
        $workflow->name = (string) ($data['name'] ?? $workflow->name);
        $workflow->description = filled($data['description'] ?? null) ? (string) $data['description'] : null;
        $workflow->save();

        if (! $workflow->hasDraft()) {
            return $workflow->refresh();
        }

        $this->savedViaDefinitionJson = $this->willSaveViaDefinitionJson($data);

        $definition = $this->resolveDefinitionForSave($workflow, $data);
        $definition['name'] = $workflow->name;

        if ($workflow->description !== null) {
            $definition['description'] = $workflow->description;
        } else {
            unset($definition['description']);
        }

        return app(SaveWorkflowDraft::class)->handle(
            $workflow,
            $definition,
            Auth::id(),
        );
    }

    protected function getSavedNotification(): ?Notification
    {
        return null;
    }

    protected function afterSave(): void
    {
        /** @var Workflow $workflow */
        $workflow = $this->getRecord()->refresh();

        if ($this->savedViaDefinitionJson && ! $workflow->draftIsValid()) {
            foreach (WorkflowDefinitionFormValidationMapper::messagesForDefinitionJson($workflow->draftValidationErrors()) as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }

        $this->fillForm();

        $this->sendDraftSavedNotification($workflow);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function resolveDefinitionForSave(Workflow $workflow, array $data): array
    {
        $workflowSteps = $data['workflow_steps'] ?? null;

        $endOutcomes = is_array($data['end_outcomes'] ?? null) ? $data['end_outcomes'] : [];

        if (is_array($workflowSteps) && $workflowSteps !== []) {
            return StandardWorkflowDefinitionMapper::buildDefinition(
                workflow: $workflow,
                blocks: $workflowSteps,
                name: (string) ($data['name'] ?? $workflow->name),
                description: filled($data['description'] ?? null) ? (string) $data['description'] : null,
                endOutcomes: $endOutcomes,
            );
        }

        $approvalSteps = $data['approval_steps'] ?? null;

        if (is_array($approvalSteps) && $approvalSteps !== []) {
            return StandardWorkflowDefinitionMapper::buildDefinition(
                workflow: $workflow,
                blocks: array_map(
                    static fn (array $step): array => [
                        'type' => StandardWorkflowStepTypes::APPROVAL,
                        'data' => $step,
                    ],
                    $approvalSteps,
                ),
                name: (string) ($data['name'] ?? $workflow->name),
                description: filled($data['description'] ?? null) ? (string) $data['description'] : null,
                endOutcomes: $endOutcomes,
            );
        }

        $definitionJson = $data['definition_json'] ?? null;

        if (! is_string($definitionJson) || trim($definitionJson) === '') {
            return $workflow->draftDefinition();
        }

        try {
            $decoded = json_decode($definitionJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ValidationException::withMessages([
                'definition_json' => [
                    __('dbflow-filament::dbflow-filament.forms.workflow_definitions.definition_json_invalid_syntax', [
                        'detail' => $exception->getMessage(),
                    ]),
                ],
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'definition_json' => [
                    __('dbflow-filament::dbflow-filament.forms.workflow_definitions.definition_json_must_be_object'),
                ],
            ]);
        }

        $decoded['key'] = $workflow->key;

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function willSaveViaDefinitionJson(array $data): bool
    {
        $workflowSteps = $data['workflow_steps'] ?? null;

        if (is_array($workflowSteps) && $workflowSteps !== []) {
            return false;
        }

        $approvalSteps = $data['approval_steps'] ?? null;

        if (is_array($approvalSteps) && $approvalSteps !== []) {
            return false;
        }

        $definitionJson = $data['definition_json'] ?? null;

        return is_string($definitionJson) && trim($definitionJson) !== '';
    }

    protected function refreshWorkflowRecord(Workflow $workflow): void
    {
        $this->record = $workflow->loadMissing('currentVersion');
        $this->fillForm();
    }

    private function sendDraftSavedNotification(Workflow $workflow): void
    {
        if ($workflow->draftIsValid()) {
            Notification::make()
                ->title(__('dbflow-filament::dbflow-filament.notifications.definitions.draft_saved_valid'))
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('dbflow-filament::dbflow-filament.notifications.definitions.draft_saved_invalid'))
            ->body(__('dbflow-filament::dbflow-filament.notifications.definitions.validation_error_count', [
                'count' => count($workflow->draftValidationErrors()),
            ]))
            ->warning()
            ->send();
    }
}
