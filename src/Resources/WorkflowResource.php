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

namespace DbflowLabs\Filament\Resources;

use BackedEnum;
use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Filament\Support\Actions\WorkflowDefinitionLifecycleActions;
use DbflowLabs\Filament\Support\Editors\StandardWorkflowDefinitionEditor;
use DbflowLabs\Filament\Support\Presenters\WorkflowDefinitionStatusPresenter;
use DbflowLabs\Filament\Support\WorkflowDefinitionAuthorization;
use DbflowLabs\Filament\Support\WorkflowDefinitionEditorResolverManager;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Support\Str;
use UnitEnum;

class WorkflowResource extends Resource
{
    protected static ?string $model = Workflow::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = null;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components(static::metadataFormComponents());
    }

    /**
     * @return list<Section>
     */
    public static function metadataFormComponents(bool $lockKeyOnEdit = true): array
    {
        return [
            Section::make(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.sections.basic'))
                ->schema([
                    Callout::make(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.code_binding_callout_heading'))
                        ->description(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.code_binding_callout_body'))
                        ->info()
                        ->visible(fn (): bool => static::isCodeBindingMode())
                        ->columnSpanFull(),
                    Select::make('model_type')
                        ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.model_type'))
                        ->options(fn (): array => static::workflowableOptions())
                        ->required(fn (): bool => static::isUiBindingMode())
                        ->native(false)
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            $generatedKey = static::generateAutoKeyFromModelType($state);

                            if ($generatedKey !== null) {
                                $set('key', $generatedKey);
                            }
                        })
                        ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.model_type_helper'))
                        ->disabled(fn (?Workflow $record): bool => $lockKeyOnEdit && $record instanceof Workflow)
                        ->dehydrated()
                        ->visible(fn (): bool => static::isUiBindingMode()),
                    TextInput::make('key')
                        ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.key'))
                        ->required(fn (): bool => static::isCodeBindingMode())
                        ->maxLength(64)
                        ->regex('/^[a-z0-9_]+$/')
                        ->unique(ignoreRecord: true)
                        ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.key_helper'))
                        ->disabled(fn (?Workflow $record): bool => $lockKeyOnEdit && $record instanceof Workflow)
                        ->dehydrated()
                        ->visible(fn (): bool => static::isCodeBindingMode()),
                    TextInput::make('name')
                        ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.name'))
                        ->required()
                        ->maxLength(120),
                    Textarea::make('description')
                        ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.description'))
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->compact(),
        ];
    }

    public static function bindingMode(): string
    {
        $mode = config('dbflow.binding_mode', 'code');

        return is_string($mode) && $mode !== '' ? $mode : 'code';
    }

    public static function isCodeBindingMode(): bool
    {
        return static::bindingMode() === 'code';
    }

    public static function isUiBindingMode(): bool
    {
        return static::bindingMode() === 'ui';
    }

    /**
     * @return array<string, string>
     */
    public static function workflowableOptions(): array
    {
        $workflowables = config('dbflow.workflowables', []);

        if (! is_array($workflowables)) {
            return [];
        }

        $options = [];

        foreach ($workflowables as $class => $label) {
            if (! is_string($class) || $class === '') {
                continue;
            }

            $options[$class] = is_string($label) && $label !== ''
                ? $label
                : class_basename($class);
        }

        return $options;
    }

    public static function generateAutoKeyFromModelType(?string $modelType): ?string
    {
        if (! is_string($modelType) || $modelType === '') {
            return null;
        }

        return 'auto_'.Str::snake(class_basename($modelType));
    }

    /**
     * @return list<\Filament\Schemas\Components\Component>
     */
    public static function standardDefinitionEditorComponents(?Workflow $record): array
    {
        return StandardWorkflowDefinitionEditor::formComponents($record);
    }

    /**
     * @return list<Textarea>
     * @deprecated Raw JSON authoring is no longer the Standard default. Use standardDefinitionEditorComponents().
     */
    public static function legacyDefinitionJsonEditorComponents(): array
    {
        return [
            Textarea::make('definition_json')
                ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.definition_json'))
                ->rows(16)
                ->columnSpanFull()
                ->visible(fn (?Workflow $record): bool => $record instanceof Workflow && $record->hasDraft()),
        ];
    }

    /**
     * @return list<Textarea>
     */
    public static function defaultDefinitionEditorComponents(): array
    {
        return static::legacyDefinitionJsonEditorComponents();
    }

    /**
     * @return list<\Filament\Schemas\Components\Component>
     */
    public static function definitionEditorFields(?Workflow $record, string $operation = 'edit'): array
    {
        $context = static::definitionEditorContext($record, $operation);

        $customComponents = app(WorkflowDefinitionEditorResolverManager::class)->resolve($context);

        if ($customComponents !== []) {
            return $customComponents;
        }

        return static::standardDefinitionEditorComponents($record);
    }

    public static function definitionEditorSection(string $operation = 'edit'): Section
    {
        return Section::make(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.sections.definition'))
            ->description(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.definition_form_helper'))
            ->schema(fn (?Workflow $record): array => static::definitionEditorFields($record, $operation))
            ->visible(fn (?Workflow $record): bool => $record instanceof Workflow && $record->hasDraft());
    }

    /**
     * @return array{
     *     record: Workflow|null,
     *     operation: string,
     *     state_path: string,
     *     resource: class-string,
     * }
     */
    public static function definitionEditorContext(?Workflow $record, string $operation = 'edit'): array
    {
        return [
            'record' => $record,
            'operation' => $operation,
            'state_path' => 'definition_json',
            'resource' => static::class,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('key')
                    ->label(__('dbflow-filament::dbflow-filament.tables.workflow_definitions.key'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('name')
                    ->label(__('dbflow-filament::dbflow-filament.tables.workflow_definitions.name'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('dbflow-filament::dbflow-filament.tables.workflow_definitions.status'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state, Workflow $record): string => WorkflowDefinitionStatusPresenter::lifecycleStatusLabel($record))
                    ->color(fn (mixed $state, Workflow $record): string => WorkflowDefinitionStatusPresenter::lifecycleStatusColor($record)),
                TextColumn::make('currentVersion.version')
                    ->label(__('dbflow-filament::dbflow-filament.tables.workflow_definitions.current_version'))
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? (string) $state : '—')
                    ->placeholder('—'),
                TextColumn::make('draft_status')
                    ->label(__('dbflow-filament::dbflow-filament.tables.workflow_definitions.draft_version'))
                    ->badge()
                    ->state(fn (Workflow $record): string => WorkflowDefinitionStatusPresenter::draftStatusKey($record))
                    ->formatStateUsing(fn (string $state): string => WorkflowDefinitionStatusPresenter::draftStatusLabel($state))
                    ->color(fn (string $state): string => WorkflowDefinitionStatusPresenter::draftStatusColor($state)),
                TextColumn::make('draft_validation_errors')
                    ->label(__('dbflow-filament::dbflow-filament.tables.workflow_definitions.validation_errors'))
                    ->state(fn (Workflow $record): int => count($record->draftValidationErrors()))
                    ->alignCenter(),
                TextColumn::make('draft_updated_at')
                    ->label(__('dbflow-filament::dbflow-filament.tables.workflow_definitions.draft_updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label(__('dbflow-filament::dbflow-filament.tables.workflow_definitions.updated_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('dbflow-filament::dbflow-filament.tables.workflow_definitions.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters(static::tableFilters())
            ->recordActions([
                EditAction::make()
                    ->visible(fn (Workflow $record): bool => static::canEdit($record)),
                ...WorkflowDefinitionLifecycleActions::lifecycleActions(),
                WorkflowDefinitionLifecycleActions::deleteAction(),
            ])
            ->toolbarActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('currentVersion');
    }

    public static function canViewAny(): bool
    {
        return WorkflowDefinitionAuthorization::can('view');
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (is_callable(config('dbflow-filament.should_register_navigation'))) {
            return (bool) call_user_func(config('dbflow-filament.should_register_navigation'), auth()->user());
        }

        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return WorkflowDefinitionAuthorization::can('view', $record);
    }

    public static function canCreate(): bool
    {
        return WorkflowDefinitionAuthorization::can('create');
    }

    public static function canEdit(Model $record): bool
    {
        return WorkflowDefinitionAuthorization::can('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        if (! WorkflowDefinitionAuthorization::can('delete', $record)) {
            return false;
        }

        return $record instanceof Workflow && $record->canBeDeleted();
    }

    public static function getPages(): array
    {
        return [
            'index' => WorkflowResource\Pages\ListWorkflows::route('/'),
            'create' => WorkflowResource\Pages\CreateWorkflow::route('/create'),
            'edit' => WorkflowResource\Pages\EditWorkflow::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $configured = config('dbflow-filament.navigation_group');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return (string) __('dbflow-filament::dbflow-filament.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('dbflow-filament::dbflow-filament.resources.workflow_definitions.navigation_label');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('dbflow-filament.navigation_sort.workflow_definitions');

        return is_numeric($sort) ? (int) $sort : 25;
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getModelLabel(): string
    {
        return (string) __('dbflow-filament::dbflow-filament.resources.workflow_definitions.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('dbflow-filament::dbflow-filament.resources.workflow_definitions.plural_model_label');
    }

    /**
     * @return array<SelectFilter>
     */
    private static function tableFilters(): array
    {
        $filters = [];

        if (DatabaseSchema::hasColumn('dbflow_workflows', 'status')) {
            $filters[] = SelectFilter::make('status')
                ->label(__('dbflow-filament::dbflow-filament.tables.workflow_definitions.filters.status'))
                ->options([
                    'draft' => __('dbflow-filament::dbflow-filament.resources.workflow_definitions.statuses.draft'),
                    'published' => __('dbflow-filament::dbflow-filament.resources.workflow_definitions.statuses.published'),
                    'disabled' => __('dbflow-filament::dbflow-filament.resources.workflow_definitions.statuses.disabled'),
                    'archived' => __('dbflow-filament::dbflow-filament.resources.workflow_definitions.statuses.archived'),
                    'empty' => __('dbflow-filament::dbflow-filament.resources.workflow_definitions.statuses.empty'),
                ]);
        }

        $filters[] = SelectFilter::make('draft_state')
            ->label(__('dbflow-filament::dbflow-filament.tables.workflow_definitions.filters.draft_state'))
            ->options([
                'has_draft' => __('dbflow-filament::dbflow-filament.resources.workflow_definitions.draft_statuses.has_draft'),
                'valid_draft' => __('dbflow-filament::dbflow-filament.resources.workflow_definitions.draft_statuses.valid_draft'),
                'invalid_draft' => __('dbflow-filament::dbflow-filament.resources.workflow_definitions.draft_statuses.invalid_draft'),
                'no_draft' => __('dbflow-filament::dbflow-filament.resources.workflow_definitions.draft_statuses.no_draft'),
            ])
            ->query(function (Builder $query, array $data): Builder {
                $value = $data['value'] ?? null;

                if (! is_string($value) || $value === '') {
                    return $query;
                }

                return match ($value) {
                    'has_draft' => $query->whereNotNull('draft_definition')
                        ->where('draft_definition', '!=', '[]')
                        ->where('draft_definition', '!=', 'null'),
                    'valid_draft' => $query->whereNotNull('draft_definition')
                        ->where('draft_definition', '!=', '[]')
                        ->where(function (Builder $builder): void {
                            $builder->whereNull('draft_validation_errors')
                                ->orWhere('draft_validation_errors', '[]');
                        }),
                    'invalid_draft' => $query->whereNotNull('draft_validation_errors')
                        ->where('draft_validation_errors', '!=', '[]'),
                    'no_draft' => $query->where(function (Builder $builder): void {
                        $builder->whereNull('draft_definition')
                            ->orWhere('draft_definition', '[]');
                    }),
                    default => $query,
                };
            });

        return $filters;
    }
}
