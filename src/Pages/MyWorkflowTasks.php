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

namespace DbflowLabs\Filament\Pages;

use BackedEnum;
use DbflowLabs\Core\Models\WorkflowTaskAssignment;
use DbflowLabs\Core\Support\WorkflowDefinitionDisplay;
use DbflowLabs\Filament\Contracts\StatusBadgeMapper;
use DbflowLabs\Filament\Contracts\WorkflowableLabelResolver;
use DbflowLabs\Filament\Support\Actions\MyWorkflowTaskTableActions;
use DbflowLabs\Filament\Support\Queries\MyWorkflowTasksQuery;
use DbflowLabs\Filament\Support\WorkflowFilamentPermissions;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class MyWorkflowTasks extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = null;

    protected static ?int $navigationSort = null;

    protected string $view = 'dbflow-filament::pages.my-workflow-tasks';

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        $prefix = trim((string) config('dbflow-filament.route_prefix', 'dbflow'), '/');

        return $prefix.'/my-workflow-tasks';
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
        return (string) __('dbflow-filament::dbflow-filament.pages.my_tasks.navigation_label');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('dbflow-filament.navigation_sort.my_tasks');

        return is_numeric($sort) ? (int) $sort : 10;
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function canAccess(): bool
    {
        if (! (bool) config('dbflow-filament.enabled', true)) {
            return false;
        }

        $user = Auth::user();

        if ($user instanceof Authenticatable) {
            return WorkflowFilamentPermissions::can('tasks', 'view', user: $user);
        }

        return WorkflowFilamentPermissions::can('tasks', 'view', user: null);
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (is_callable(config('dbflow-filament.should_register_navigation'))) {
            return (bool) call_user_func(config('dbflow-filament.should_register_navigation'), Auth::user());
        }

        return static::canAccess();
    }

    public function getTitle(): string|Htmlable
    {
        return (string) __('dbflow-filament::dbflow-filament.pages.my_tasks.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return (string) __('dbflow-filament::dbflow-filament.pages.my_tasks.subheading');
    }

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    public function table(Table $table): Table
    {
        $workflowableLabelResolver = app(WorkflowableLabelResolver::class);
        $statusBadgeMapper = app(StatusBadgeMapper::class);
        $workflowDefinitionDisplay = app(WorkflowDefinitionDisplay::class);

        return $table
            ->query(fn (): Builder => $this->pendingTasksQuery())
            ->columns([
                TextColumn::make('workflowTask.workflowInstance.workflow.name')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.workflow'))
                    ->formatStateUsing(function (?string $state, WorkflowTaskAssignment $record) use ($workflowDefinitionDisplay): string {
                        $workflow = $record->workflowTask?->workflowInstance?->workflow;

                        return $workflowDefinitionDisplay->workflowName($workflow?->key, $workflow?->name ?? $state);
                    })
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('workflowTask.node_name')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.current_node'))
                    ->formatStateUsing(function (?string $state, WorkflowTaskAssignment $record) use ($workflowDefinitionDisplay): string {
                        $workflowKey = $record->workflowTask?->workflowInstance?->workflow?->key;
                        $nodeKey = $record->workflowTask?->node_key;

                        if ($state !== null && $state !== '') {
                            return $workflowDefinitionDisplay->nodeLabel($workflowKey, $nodeKey, $state);
                        }

                        if ($nodeKey !== null && $nodeKey !== '') {
                            return $workflowDefinitionDisplay->nodeLabel($workflowKey, $nodeKey);
                        }

                        $currentNodeKey = $record->workflowTask?->workflowInstance?->current_node_key;

                        if ($currentNodeKey !== null && $currentNodeKey !== '') {
                            return $workflowDefinitionDisplay->nodeLabel($workflowKey, $currentNodeKey);
                        }

                        return '—';
                    }),
                TextColumn::make('workflowTask.workflowInstance.workflowable_type')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.workflowable_type'))
                    ->formatStateUsing(fn (?string $state): string => $workflowableLabelResolver->morphTypeLabel($state)),
                TextColumn::make('workflowTask.workflowInstance.workflowable_id')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.workflowable_id'))
                    ->formatStateUsing(fn (?string $state): string => $workflowableLabelResolver->morphIdLabel($state)),
                TextColumn::make('workflowTask.status')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.status'))
                    ->badge()
                    ->formatStateUsing(function ($state) use ($statusBadgeMapper): string {
                        if ($state === null) {
                            return '—';
                        }

                        $statusValue = is_object($state) && property_exists($state, 'value')
                            ? (string) $state->value
                            : (string) $state;

                        return $statusBadgeMapper->labelFor($statusValue);
                    })
                    ->color(function ($state) use ($statusBadgeMapper): string {
                        if ($state === null) {
                            return 'gray';
                        }

                        $statusValue = is_object($state) && property_exists($state, 'value')
                            ? (string) $state->value
                            : (string) $state;

                        return $statusBadgeMapper->colorFor($statusValue);
                    }),
                TextColumn::make('created_at')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.assigned_at'))
                    ->dateTime(config('dbflow-filament.date_time_format', 'Y-m-d H:i:s'))
                    ->sortable(),
                TextColumn::make('workflowTask.created_at')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.created_at'))
                    ->dateTime(config('dbflow-filament.date_time_format', 'Y-m-d H:i:s'))
                    ->sortable(),
            ])
            ->recordActions($this->myWorkflowTaskRecordActions())
            ->toolbarActions([])
            ->emptyStateHeading((string) __('dbflow-filament::dbflow-filament.pages.my_tasks.empty_state_heading'))
            ->emptyStateDescription((string) __('dbflow-filament::dbflow-filament.pages.my_tasks.empty_state_description'));
    }

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function myWorkflowTaskRecordActions(): array
    {
        if (! (bool) config('dbflow-filament.enable_my_task_actions', true)) {
            return [];
        }

        return [
            MyWorkflowTaskTableActions::approve(),
            MyWorkflowTaskTableActions::reject(),
        ];
    }

    protected function pendingTasksQuery(): Builder
    {
        $user = Auth::user();

        if (! $user instanceof Authenticatable) {
            return WorkflowTaskAssignment::query()->whereRaw('1 = 0');
        }

        return app(MyWorkflowTasksQuery::class)->pendingForUser($user->getKey());
    }
}
