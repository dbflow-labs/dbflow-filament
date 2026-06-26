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

namespace DbflowLabs\Filament\Support\Editors;

use DbflowLabs\Core\Definitions\WorkflowDefinitionSchema;
use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Filament\Contracts\PermissionAssigneeOptionsResolver;
use DbflowLabs\Filament\Contracts\UserAssigneeOptionsResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

final class StandardWorkflowDefinitionEditor
{
    /**
     * @return list<Component>
     */
    public static function formComponents(?Workflow $record): array
    {
        $isUnsupported = $record instanceof Workflow
            && $record->hasDraft()
            && ! StandardWorkflowDefinitionMapper::isStandardFormDefinition($record->draftDefinition());

        $isFormEditable = $record instanceof Workflow
            && $record->hasDraft()
            && ! $isUnsupported;

        return [
            Textarea::make('definition_unsupported_notice')
                ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.unsupported_notice'))
                ->default(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.unsupported_notice_body'))
                ->disabled()
                ->dehydrated(false)
                ->rows(3)
                ->visible(fn (): bool => $isUnsupported)
                ->columnSpanFull(),
            Placeholder::make('workflow_flow_preview')
                ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.flow_preview'))
                ->content(fn (Get $get): string => StandardWorkflowFlowPreviewFormatter::format(
                    $get('workflow_steps'),
                    $get('end_outcomes'),
                ))
                ->visible(fn (): bool => $isFormEditable)
                ->columnSpanFull(),
            Builder::make('workflow_steps')
                ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.workflow_steps'))
                ->blocks(self::workflowStepBlocks())
                ->blockNumbers()
                ->collapsible()
                ->cloneable()
                ->minItems(1)
                ->maxItems(20)
                ->reorderableWithButtons()
                ->addActionLabel(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.add_workflow_step'))
                ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.workflow_steps_helper'))
                ->live()
                ->visible(fn (): bool => $isFormEditable)
                ->columnSpanFull(),
            Repeater::make('end_outcomes')
                ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.end_outcomes'))
                ->schema([
                    TextInput::make('step_label')
                        ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.end_outcome_label'))
                        ->required()
                        ->maxLength(120)
                        ->placeholder(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.end_outcome_label_placeholder'))
                        ->columnSpanFull(),
                    Select::make('end_status')
                        ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.end_status'))
                        ->required()
                        ->options(fn (): array => self::endStatusOptions())
                        ->default(StandardWorkflowEndStatuses::COMPLETED)
                        ->native(false)
                        ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.end_status_helper'))
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->minItems(1)
                ->maxItems(10)
                ->reorderableWithButtons()
                ->itemLabel(fn (array $state): ?string => filled($state['step_label'] ?? null)
                    ? (string) $state['step_label']
                    : __('dbflow-filament::dbflow-filament.forms.workflow_definitions.end_outcome_untitled'))
                ->addActionLabel(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.add_end_outcome'))
                ->deleteAction(fn (Action $action): Action => self::guardRepeaterMinItemsDelete(
                    $action,
                    'dbflow-filament::dbflow-filament.forms.workflow_definitions.cannot_delete_last_end_outcome',
                    'dbflow-filament::dbflow-filament.forms.workflow_definitions.cannot_delete_last_end_outcome_body',
                ))
                ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.end_outcomes_helper'))
                ->live()
                ->visible(fn (): bool => $isFormEditable)
                ->columnSpanFull(),
            self::definitionJsonFallbackField($isUnsupported),
        ];
    }

    private static function guardRepeaterMinItemsDelete(
        Action $action,
        string $titleKey,
        string $bodyKey,
    ): Action {
        return $action->action(function (array $arguments, Repeater $component) use ($titleKey, $bodyKey): void {
            $items = $component->getRawState();
            $minItems = $component->getMinItems();

            if ($minItems !== null && is_array($items) && count($items) <= $minItems) {
                Notification::make()
                    ->title(__($titleKey))
                    ->body(__($bodyKey))
                    ->warning()
                    ->send();

                return;
            }

            unset($items[$arguments['item']]);

            $component->rawState($items);
            $component->callAfterStateUpdated();

            $component->shouldPartiallyRenderAfterActionsCalled() ? $component->partiallyRender() : null;
        });
    }

    private static function definitionJsonFallbackField(bool $isUnsupported): Textarea
    {
        return Textarea::make('definition_json')
            ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.definition_json'))
            ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.definition_json_helper'))
            ->rows(16)
            ->columnSpanFull()
            ->visible(fn (): bool => $isUnsupported);
    }

    /**
     * @return array<Builder\Block>
     */
    private static function workflowStepBlocks(): array
    {
        return [
            self::approvalBlock(),
            self::conditionBlock(),
            self::actionBlock(),
        ];
    }

    private static function approvalBlock(): Block
    {
        return Block::make(StandardWorkflowStepTypes::APPROVAL)
            ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.blocks.approval'))
            ->schema([
                self::stepLabelField(),
                Select::make('assignee_type')
                    ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_type'))
                    ->required()
                    ->options(fn (): array => self::assigneeTypeOptions())
                    ->default(WorkflowDefinitionSchema::ASSIGNEE_TYPE_USER)
                    ->live()
                    ->native(false),
                ...self::assigneeValueFields(),
                Placeholder::make('permission_assignee_preview')
                    ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_permission_preview'))
                    ->content(fn (Get $get): string => self::permissionAssigneePreview((string) $get('assignee_value')))
                    ->visible(fn (Get $get): bool => $get('assignee_type') === WorkflowDefinitionSchema::ASSIGNEE_TYPE_PERMISSION
                        && self::permissionAssigneeOptions() !== [])
                    ->columnSpanFull(),
                Select::make('approval_mode')
                    ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.approval_mode'))
                    ->required()
                    ->options(fn (): array => self::approvalModeOptions())
                    ->default(WorkflowDefinitionSchema::APPROVAL_MODE_ANY)
                    ->native(false)
                    ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.approval_mode_helper')),
            ])
            ->columns(2);
    }

    private static function conditionBlock(): Block
    {
        return Block::make(StandardWorkflowStepTypes::CONDITION)
            ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.blocks.condition'))
            ->schema([
                self::stepLabelField(),
                Textarea::make('expression')
                    ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.condition_expression'))
                    ->required()
                    ->rows(3)
                    ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.condition_expression_helper'))
                    ->columnSpanFull(),
                Select::make('true_branch')
                    ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.true_branch'))
                    ->required()
                    ->options(fn (): array => self::branchTargetOptions())
                    ->default(StandardWorkflowBranchTargets::NEXT)
                    ->native(false)
                    ->live(),
                Select::make('false_branch')
                    ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.false_branch'))
                    ->required()
                    ->options(fn (): array => self::branchTargetOptions())
                    ->default(StandardWorkflowBranchTargets::END_OUTCOME)
                    ->native(false)
                    ->live(),
                Select::make('true_branch_step_key')
                    ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.branch_step_key'))
                    ->options(fn (Get $get): array => self::workflowStepKeyOptions($get('workflow_steps')))
                    ->visible(fn (Get $get): bool => $get('true_branch') === StandardWorkflowBranchTargets::STEP)
                    ->required(fn (Get $get): bool => $get('true_branch') === StandardWorkflowBranchTargets::STEP)
                    ->native(false)
                    ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.branch_step_key_helper')),
                Select::make('false_branch_step_key')
                    ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.branch_step_key'))
                    ->options(fn (Get $get): array => self::workflowStepKeyOptions($get('workflow_steps')))
                    ->visible(fn (Get $get): bool => $get('false_branch') === StandardWorkflowBranchTargets::STEP)
                    ->required(fn (Get $get): bool => $get('false_branch') === StandardWorkflowBranchTargets::STEP)
                    ->native(false)
                    ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.branch_step_key_helper')),
                Select::make('true_branch_end_key')
                    ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.branch_end_key'))
                    ->options(fn (Get $get): array => self::endOutcomeKeyOptions(
                        $get('end_outcomes'),
                        $get('workflow_steps'),
                    ))
                    ->visible(fn (Get $get): bool => in_array(
                        $get('true_branch'),
                        [StandardWorkflowBranchTargets::END_OUTCOME, StandardWorkflowBranchTargets::END],
                        true,
                    ))
                    ->native(false)
                    ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.branch_end_key_helper')),
                Select::make('false_branch_end_key')
                    ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.branch_end_key'))
                    ->options(fn (Get $get): array => self::endOutcomeKeyOptions(
                        $get('end_outcomes'),
                        $get('workflow_steps'),
                    ))
                    ->visible(fn (Get $get): bool => in_array(
                        $get('false_branch'),
                        [StandardWorkflowBranchTargets::END_OUTCOME, StandardWorkflowBranchTargets::END],
                        true,
                    ))
                    ->native(false)
                    ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.branch_end_key_helper')),
            ])
            ->columns(2);
    }

    private static function actionBlock(): Block
    {
        return Block::make(StandardWorkflowStepTypes::ACTION)
            ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.blocks.action'))
            ->schema([
                self::stepLabelField(),
                TextInput::make('action_key')
                    ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.action_key'))
                    ->required()
                    ->maxLength(120)
                    ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.action_key_helper')),
                KeyValue::make('payload')
                    ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.action_payload'))
                    ->keyLabel(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.action_payload_key'))
                    ->valueLabel(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.action_payload_value'))
                    ->addActionLabel(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.action_payload_add'))
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    private static function stepLabelField(): TextInput
    {
        return TextInput::make('step_label')
            ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.step_label'))
            ->required()
            ->maxLength(120)
            ->placeholder(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.step_label_placeholder'))
            ->columnSpanFull();
    }

    /**
     * @return list<Select|TextInput>
     */
    private static function assigneeValueFields(): array
    {
        $userOptions = self::userAssigneeOptions();
        $permissionOptions = self::permissionAssigneeOptions();

        if ($userOptions === [] && $permissionOptions === []) {
            return [
                TextInput::make('assignee_value')
                    ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_value'))
                    ->required()
                    ->maxLength(255)
                    ->numeric(fn (Get $get): bool => $get('assignee_type') === WorkflowDefinitionSchema::ASSIGNEE_TYPE_USER)
                    ->integer(fn (Get $get): bool => $get('assignee_type') === WorkflowDefinitionSchema::ASSIGNEE_TYPE_USER)
                    ->minValue(fn (Get $get): ?int => $get('assignee_type') === WorkflowDefinitionSchema::ASSIGNEE_TYPE_USER ? 1 : null)
                    ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_value_helper')),
            ];
        }

        $fields = [];

        if ($userOptions !== []) {
            $fields[] = Select::make('assignee_value')
                ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_user'))
                ->required()
                ->options(fn (): array => $userOptions)
                ->getOptionLabelUsing(fn ($value): string => self::userAssigneeLabel((string) $value))
                ->searchable()
                ->native(false)
                ->visible(fn (Get $get): bool => $get('assignee_type') === WorkflowDefinitionSchema::ASSIGNEE_TYPE_USER)
                ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_user_helper'));
        }

        if ($permissionOptions !== []) {
            $fields[] = Select::make('assignee_value')
                ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_permission'))
                ->required()
                ->options(fn (): array => $permissionOptions)
                ->getOptionLabelUsing(fn ($value): string => self::permissionAssigneeLabel((string) $value))
                ->searchable()
                ->native(false)
                ->live()
                ->visible(fn (Get $get): bool => $get('assignee_type') === WorkflowDefinitionSchema::ASSIGNEE_TYPE_PERMISSION)
                ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_permission_helper'));
        }

        $fields[] = TextInput::make('assignee_value')
            ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_value'))
            ->required()
            ->maxLength(255)
            ->visible(fn (Get $get): bool => match ($get('assignee_type')) {
                WorkflowDefinitionSchema::ASSIGNEE_TYPE_USER => $userOptions === [],
                WorkflowDefinitionSchema::ASSIGNEE_TYPE_PERMISSION => $permissionOptions === [],
                default => true,
            })
            ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_value_helper'));

        return $fields;
    }

    private static function userAssigneeLabel(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return self::userAssigneeOptions()[$value]
            ?? (string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_unknown_user', ['id' => $value]);
    }

    /**
     * @return array<string, string>
     */
    private static function userAssigneeOptions(): array
    {
        if (! interface_exists(UserAssigneeOptionsResolver::class)) {
            return [];
        }

        try {
            return app(UserAssigneeOptionsResolver::class)->options();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    private static function permissionAssigneeOptions(): array
    {
        if (! interface_exists(PermissionAssigneeOptionsResolver::class)) {
            return [];
        }

        try {
            return app(PermissionAssigneeOptionsResolver::class)->options();
        } catch (\Throwable) {
            return [];
        }
    }

    private static function permissionAssigneeLabel(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return self::permissionAssigneeOptions()[$value]
            ?? (string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_unknown_permission', ['key' => $value]);
    }

    private static function permissionAssigneePreview(string $permissionKey): string
    {
        $permissionKey = trim($permissionKey);

        if ($permissionKey === '') {
            return (string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_permission_preview_empty');
        }

        if (! interface_exists(PermissionAssigneeOptionsResolver::class)) {
            return '';
        }

        try {
            $resolver = app(PermissionAssigneeOptionsResolver::class);

            if (! $resolver->exists($permissionKey)) {
                return (string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_permission_invalid', [
                    'key' => $permissionKey,
                ]);
            }

            $labels = $resolver->resolvedUserLabels($permissionKey);

            if ($labels === []) {
                return (string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_permission_no_users', [
                    'key' => $permissionKey,
                ]);
            }

            return (string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_permission_preview_users', [
                'users' => implode(', ', $labels),
            ]);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @return array<string, string>
     */
    private static function assigneeTypeOptions(): array
    {
        $options = [];

        foreach (StandardWorkflowDefinitionMapper::supportedAssigneeTypes() as $type) {
            $options[$type] = __("dbflow-filament::dbflow-filament.forms.workflow_definitions.assignee_types.{$type}");
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function approvalModeOptions(): array
    {
        return [
            WorkflowDefinitionSchema::APPROVAL_MODE_ANY => __('dbflow-filament::dbflow-filament.forms.workflow_definitions.approval_modes.any'),
            WorkflowDefinitionSchema::APPROVAL_MODE_ALL => __('dbflow-filament::dbflow-filament.forms.workflow_definitions.approval_modes.all'),
            WorkflowDefinitionSchema::APPROVAL_MODE_SEQUENTIAL => __('dbflow-filament::dbflow-filament.forms.workflow_definitions.approval_modes.sequential'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function branchTargetOptions(): array
    {
        return [
            StandardWorkflowBranchTargets::NEXT => __('dbflow-filament::dbflow-filament.forms.workflow_definitions.branch_targets.next'),
            StandardWorkflowBranchTargets::END_OUTCOME => __('dbflow-filament::dbflow-filament.forms.workflow_definitions.branch_targets.end_outcome'),
            StandardWorkflowBranchTargets::STEP => __('dbflow-filament::dbflow-filament.forms.workflow_definitions.branch_targets.step'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function endStatusOptions(): array
    {
        return [
            StandardWorkflowEndStatuses::COMPLETED => __('dbflow-filament::dbflow-filament.forms.workflow_definitions.end_statuses.completed'),
            StandardWorkflowEndStatuses::REJECTED => __('dbflow-filament::dbflow-filament.forms.workflow_definitions.end_statuses.rejected'),
            StandardWorkflowEndStatuses::CANCELLED => __('dbflow-filament::dbflow-filament.forms.workflow_definitions.end_statuses.cancelled'),
        ];
    }

    /**
     * @param  mixed  $workflowSteps
     * @return array<string, string>
     */
    private static function workflowStepKeyOptions(mixed $workflowSteps): array
    {
        if (! is_array($workflowSteps) || $workflowSteps === []) {
            return [];
        }

        return StandardWorkflowDefinitionMapper::previewStepKeyOptions($workflowSteps);
    }

    /**
     * @param  mixed  $endOutcomes
     * @param  mixed  $workflowSteps
     * @return array<string, string>
     */
    private static function endOutcomeKeyOptions(mixed $endOutcomes, mixed $workflowSteps = []): array
    {
        if (! is_array($endOutcomes) || $endOutcomes === []) {
            return [];
        }

        $blocks = is_array($workflowSteps) ? $workflowSteps : [];

        return StandardWorkflowDefinitionMapper::previewEndOutcomeKeyOptions($endOutcomes, $blocks);
    }
}
