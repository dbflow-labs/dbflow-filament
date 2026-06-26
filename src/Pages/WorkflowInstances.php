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
use DbflowLabs\Core\Enums\WorkflowInstanceStatus;
use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Core\Models\WorkflowInstance;
use DbflowLabs\Core\Support\WorkflowDefinitionDisplay;
use DbflowLabs\Filament\Contracts\PermissionChecker;
use DbflowLabs\Filament\Contracts\StatusBadgeMapper;
use DbflowLabs\Filament\Contracts\UserDisplayResolver;
use DbflowLabs\Filament\Contracts\WorkflowableLabelResolver;
use DbflowLabs\Filament\Support\Actions\WorkflowInstanceTableActions;
use DbflowLabs\Filament\Support\Queries\WorkflowInstancesQuery;
use DbflowLabs\Filament\Support\WorkflowFilamentPermissions;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
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

class WorkflowInstances extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = null;

    protected static ?int $navigationSort = null;

    protected string $view = 'dbflow-filament::pages.workflow-instances';

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        $prefix = trim((string) config('dbflow-filament.route_prefix', 'dbflow'), '/');

        return $prefix.'/workflow-instances';
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
        return (string) __('dbflow-filament::dbflow-filament.pages.instances.navigation_label');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('dbflow-filament.navigation_sort.workflow_instances');

        return is_numeric($sort) ? (int) $sort : 20;
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
            return WorkflowFilamentPermissions::can('workflow_instances', 'view_any', user: $user);
        }

        return WorkflowFilamentPermissions::can('workflow_instances', 'view_any', user: null);
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (is_callable(config('dbflow-filament.should_register_navigation'))) {
            return (bool) call_user_func(config('dbflow-filament.should_register_navigation'), Auth::user());
        }

        return static::canAccess();
    }

    /**
     * @return string|array<string>
     */
    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            self::getRouteName(),
            ViewWorkflowInstance::getRouteName(),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return (string) __('dbflow-filament::dbflow-filament.pages.instances.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return (string) __('dbflow-filament::dbflow-filament.pages.instances.subheading');
    }

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    public function table(Table $table): Table
    {
        $workflowableLabelResolver = app(WorkflowableLabelResolver::class);
        $statusBadgeMapper = app(StatusBadgeMapper::class);
        $userDisplayResolver = app(UserDisplayResolver::class);
        $workflowDefinitionDisplay = app(WorkflowDefinitionDisplay::class);

        return $table
            ->query(fn (): Builder => app(WorkflowInstancesQuery::class)->baseQuery())
            ->columns([
                TextColumn::make('workflow.name')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.workflow'))
                    ->formatStateUsing(function (?string $state, WorkflowInstance $record) use ($workflowDefinitionDisplay): string {
                        return $workflowDefinitionDisplay->workflowName($record->workflow?->key, $record->workflow?->name ?? $state);
                    })
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('workflow.key')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.instances.columns.workflow_key'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('workflowable_type')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.workflowable_type'))
                    ->formatStateUsing(fn (?string $state): string => $workflowableLabelResolver->morphTypeLabel($state)),
                TextColumn::make('workflowable_id')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.workflowable_id'))
                    ->formatStateUsing(fn (?string $state): string => $workflowableLabelResolver->morphIdLabel($state)),
                TextColumn::make('status')
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
                TextColumn::make('current_node_key')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.current_node'))
                    ->formatStateUsing(function (?string $state, WorkflowInstance $record) use ($workflowDefinitionDisplay): string {
                        return $workflowDefinitionDisplay->nodeLabel($record->workflow?->key, $state);
                    })
                    ->placeholder('—'),
                TextColumn::make('startedBy.name')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.instances.columns.started_by'))
                    ->formatStateUsing(fn ($state, WorkflowInstance $record): string => $userDisplayResolver->displayName($record->startedBy))
                    ->placeholder('—'),
                TextColumn::make('started_at')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.instances.columns.started_at'))
                    ->dateTime(config('dbflow-filament.date_time_format', 'Y-m-d H:i:s'))
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.instances.columns.completed_at'))
                    ->dateTime(config('dbflow-filament.date_time_format', 'Y-m-d H:i:s'))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.created_at'))
                    ->dateTime(config('dbflow-filament.date_time_format', 'Y-m-d H:i:s'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.instances.filters.status'))
                    ->options($this->statusFilterOptions()),
                SelectFilter::make('workflow_id')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.instances.filters.workflow'))
                    ->options($this->workflowFilterOptions())
                    ->searchable(),
                Filter::make('started_at_range')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.instances.filters.started_at_range'))
                    ->schema([
                        DatePicker::make('from')
                            ->label((string) __('dbflow-filament::dbflow-filament.pages.instances.filters.started_from')),
                        DatePicker::make('until')
                            ->label((string) __('dbflow-filament::dbflow-filament.pages.instances.filters.started_until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $builder, string $date): Builder => $builder->whereDate('started_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $builder, string $date): Builder => $builder->whereDate('started_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                WorkflowInstanceTableActions::view(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading((string) __('dbflow-filament::dbflow-filament.pages.instances.empty_state_heading'))
            ->emptyStateDescription((string) __('dbflow-filament::dbflow-filament.pages.instances.empty_state_description'));
    }

    /**
     * @return array<string, string>
     */
    protected function statusFilterOptions(): array
    {
        $statusBadgeMapper = app(StatusBadgeMapper::class);
        $options = [];

        foreach (WorkflowInstanceStatus::cases() as $status) {
            $options[$status->value] = $statusBadgeMapper->labelFor($status->value);
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    protected function workflowFilterOptions(): array
    {
        $workflowDefinitionDisplay = app(WorkflowDefinitionDisplay::class);

        return Workflow::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Workflow $workflow): array => [
                $workflow->getKey() => sprintf(
                    '%s (%s)',
                    $workflowDefinitionDisplay->workflowName($workflow->key, $workflow->name),
                    $workflow->key,
                ),
            ])
            ->all();
    }
}
