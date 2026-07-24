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
use DbflowLabs\Core\Enums\AssignmentSource;
use DbflowLabs\Core\Models\WorkflowTaskAssignment;
use DbflowLabs\Core\Support\WorkflowDefinitionDisplay;
use DbflowLabs\Filament\Contracts\StatusBadgeMapper;
use DbflowLabs\Filament\Contracts\WorkflowableLabelResolver;
use DbflowLabs\Filament\Support\Actions\MyWorkflowTaskTableActions;
use DbflowLabs\Filament\Support\Presenters\AssignmentActorPresenter;
use DbflowLabs\Filament\Support\Presenters\DueDatePresenter;
use DbflowLabs\Filament\Support\Presenters\RuntimeBadgePresenter;
use DbflowLabs\Filament\Support\Queries\MyWorkflowTasksQuery;
use DbflowLabs\Filament\Support\WorkflowableShowUrlResolver;
use DbflowLabs\Filament\Support\WorkflowFilamentPermissions;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
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
        $workflowableShowUrlResolver = app(WorkflowableShowUrlResolver::class);
        $statusBadgeMapper = app(StatusBadgeMapper::class);
        $workflowDefinitionDisplay = app(WorkflowDefinitionDisplay::class);
        $assignmentActorPresenter = app(AssignmentActorPresenter::class);
        $runtimeBadgePresenter = app(RuntimeBadgePresenter::class);
        $dueDatePresenter = app(DueDatePresenter::class);

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
                TextColumn::make('subject')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.subject'))
                    ->state(function (WorkflowTaskAssignment $record) use ($workflowableLabelResolver): string {
                        return $workflowableLabelResolver->labelFor(
                            $record->workflowTask?->workflowInstance?->workflowable,
                        );
                    })
                    ->url(function (WorkflowTaskAssignment $record) use ($workflowableShowUrlResolver): ?string {
                        return $workflowableShowUrlResolver->resolve(
                            $record->workflowTask?->workflowInstance?->workflowable,
                        );
                    })
                    ->openUrlInNewTab((bool) config('dbflow-filament.open_workflowable_links_in_new_tab', false)),
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
                TextColumn::make('original_actor')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.original_actor'))
                    ->state(function (WorkflowTaskAssignment $record) use ($assignmentActorPresenter): string {
                        $actors = $this->actorsForRecord($record, $assignmentActorPresenter);

                        return $actors['show_both'] ? $actors['original'] : '—';
                    }),
                TextColumn::make('effective_actor')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.effective_actor'))
                    ->state(function (WorkflowTaskAssignment $record) use ($assignmentActorPresenter): string {
                        return $this->actorsForRecord($record, $assignmentActorPresenter)['combined'];
                    }),
                TextColumn::make('assignment_source')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.assignment_source'))
                    ->badge()
                    ->state(fn (WorkflowTaskAssignment $record): string => $runtimeBadgePresenter->assignmentSourceLabel($record->assignmentSourceOrDirect()))
                    ->color(fn (WorkflowTaskAssignment $record): string => $runtimeBadgePresenter->assignmentSourceColor($record->assignmentSourceOrDirect())),
                IconColumn::make('delegation_id')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.delegated'))
                    ->boolean()
                    ->state(fn (WorkflowTaskAssignment $record): bool => $record->assignmentSourceOrDirect() === AssignmentSource::Delegation),
                TextColumn::make('workflowTask.due_at')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.due_date'))
                    ->formatStateUsing(function ($state, WorkflowTaskAssignment $record) use ($dueDatePresenter): string {
                        $task = $record->workflowTask;

                        return $task !== null
                            ? $dueDatePresenter->dueDateColumnLabel($task)
                            : '—';
                    })
                    ->color(function ($state, WorkflowTaskAssignment $record) use ($dueDatePresenter): ?string {
                        $task = $record->workflowTask;

                        if ($task === null || $task->due_at === null) {
                            return null;
                        }

                        if ($dueDatePresenter->isOverdue($task)) {
                            return 'danger';
                        }

                        if ($dueDatePresenter->isDueSoon($task)) {
                            return 'warning';
                        }

                        return null;
                    })
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('workflowTask.overdue_at')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.overdue'))
                    ->badge()
                    ->formatStateUsing(function ($state, WorkflowTaskAssignment $record) use ($dueDatePresenter): string {
                        $task = $record->workflowTask;

                        return $task !== null && $dueDatePresenter->isOverdue($task)
                            ? (string) ($dueDatePresenter->overdueLabel($task) ?? '—')
                            : '—';
                    })
                    ->color(fn ($state, WorkflowTaskAssignment $record): string => $record->workflowTask !== null && $dueDatePresenter->isOverdue($record->workflowTask) ? 'danger' : 'gray')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.assigned_at'))
                    ->dateTime(config('dbflow-filament.date_time_format', 'Y-m-d H:i:s'))
                    ->sortable(),
                TextColumn::make('workflowTask.created_at')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.created_at'))
                    ->dateTime(config('dbflow-filament.date_time_format', 'Y-m-d H:i:s'))
                    ->sortable(),
            ])
            ->filters([
                Filter::make('overdue')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.filters.overdue'))
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'workflowTask',
                        static fn (Builder $taskQuery): Builder => $taskQuery->whereNotNull('overdue_at'),
                    )),
                Filter::make('due_soon')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.filters.due_soon'))
                    ->query(function (Builder $query) use ($dueDatePresenter): Builder {
                        $now = now('UTC');
                        $threshold = $now->copy()->addHours($dueDatePresenter->dueSoonThresholdHours());

                        return $query->whereHas(
                            'workflowTask',
                            static fn (Builder $taskQuery): Builder => $taskQuery
                                ->whereNotNull('due_at')
                                ->whereNull('overdue_at')
                                ->where('due_at', '>=', $now)
                                ->where('due_at', '<=', $threshold),
                        );
                    }),
                Filter::make('no_due_date')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.filters.no_due_date'))
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'workflowTask',
                        static fn (Builder $taskQuery): Builder => $taskQuery->whereNull('due_at'),
                    )),
                SelectFilter::make('assignment_source')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.filters.assignment_source'))
                    ->options($this->assignmentSourceFilterOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! is_string($value) || $value === '') {
                            return $query;
                        }

                        if ($value === AssignmentSource::Direct->value) {
                            return $query->where(function (Builder $builder): void {
                                $builder
                                    ->whereNull('assignment_source')
                                    ->orWhere('assignment_source', AssignmentSource::Direct->value);
                            });
                        }

                        return $query->where('assignment_source', $value);
                    }),
                Filter::make('delegated')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.filters.delegated'))
                    ->query(fn (Builder $query): Builder => $query->where('assignment_source', AssignmentSource::Delegation->value)),
                Filter::make('reassigned')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.filters.reassigned'))
                    ->query(fn (Builder $query): Builder => $query->where('assignment_source', AssignmentSource::Reassignment->value)),
                Filter::make('direct')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.filters.direct'))
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $builder): void {
                        $builder
                            ->whereNull('assignment_source')
                            ->orWhere('assignment_source', AssignmentSource::Direct->value);
                    })),
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
            MyWorkflowTaskTableActions::viewRuntime(),
            MyWorkflowTaskTableActions::approve(),
            MyWorkflowTaskTableActions::reject(),
            MyWorkflowTaskTableActions::reassign(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function assignmentSourceFilterOptions(): array
    {
        $runtimeBadgePresenter = app(RuntimeBadgePresenter::class);
        $options = [];

        foreach (AssignmentSource::cases() as $source) {
            $options[$source->value] = $runtimeBadgePresenter->assignmentSourceLabel($source);
        }

        return $options;
    }

    /**
     * @return array{original: string, effective: string, show_both: bool, combined: string}
     */
    protected function actorsForRecord(WorkflowTaskAssignment $record, AssignmentActorPresenter $presenter): array
    {
        $cacheKey = (string) $record->getKey();

        if (! isset($this->actorDisplayCache[$cacheKey])) {
            $this->actorDisplayCache[$cacheKey] = $presenter->displayActors($record);
        }

        return $this->actorDisplayCache[$cacheKey];
    }

    /**
     * @var array<string, array{original: string, effective: string, show_both: bool, combined: string}>
     */
    private array $actorDisplayCache = [];

    public function isCoreRuntimeDisabled(): bool
    {
        return ! \DbflowLabs\Core\Support\DbflowRuntime::isEnabled();
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
