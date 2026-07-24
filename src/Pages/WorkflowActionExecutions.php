<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Pages;

use BackedEnum;
use DbflowLabs\Core\Enums\ActionExecutionMode;
use DbflowLabs\Core\Enums\ActionExecutionStatus;
use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Core\Models\WorkflowActionExecution;
use DbflowLabs\Core\Models\WorkflowInstance;
use DbflowLabs\Core\Support\WorkflowDefinitionDisplay;
use DbflowLabs\Filament\Support\Actions\WorkflowActionExecutionTableActions;
use DbflowLabs\Filament\Support\Presenters\DueDatePresenter;
use DbflowLabs\Filament\Support\Presenters\RuntimeBadgePresenter;
use DbflowLabs\Filament\Support\Presenters\SafeMetadataPresenter;
use DbflowLabs\Filament\Support\Queries\WorkflowActionExecutionsQuery;
use DbflowLabs\Filament\Support\RuntimeCapabilityGate;
use DbflowLabs\Filament\Support\WorkflowFilamentPermissions;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class WorkflowActionExecutions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = null;

    protected string $view = 'dbflow-filament::pages.workflow-action-executions';

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        $prefix = trim((string) config('dbflow-filament.route_prefix', 'dbflow'), '/');

        return $prefix.'/workflow-action-executions';
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
        return (string) __('dbflow-filament::dbflow-filament.pages.action_executions.navigation_label');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('dbflow-filament.navigation_sort.action_executions');

        return is_numeric($sort) ? (int) $sort : 35;
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

        if (! (bool) config('dbflow-filament.enable_action_executions_page', true)) {
            return false;
        }

        if (! app(RuntimeCapabilityGate::class)->reliableActionVisible()) {
            return false;
        }

        $user = Auth::user();

        return WorkflowFilamentPermissions::can('action_executions', 'view_any', user: $user instanceof Authenticatable ? $user : null);
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
            ViewWorkflowActionExecution::getRouteName(),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return (string) __('dbflow-filament::dbflow-filament.pages.action_executions.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return (string) __('dbflow-filament::dbflow-filament.pages.action_executions.subheading');
    }

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    public function table(Table $table): Table
    {
        $badgePresenter = app(RuntimeBadgePresenter::class);
        $dueDatePresenter = app(DueDatePresenter::class);
        $safeMetadataPresenter = app(SafeMetadataPresenter::class);
        $workflowDefinitionDisplay = app(WorkflowDefinitionDisplay::class);

        return $table
            ->query(fn (): Builder => app(WorkflowActionExecutionsQuery::class)->baseQuery())
            ->columns([
                TextColumn::make('id')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.action_executions.columns.id'))
                    ->formatStateUsing(fn ($state) => '#'.(string) $state)
                    ->sortable(),
                TextColumn::make('workflowInstance.workflow.name')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.workflow'))
                    ->formatStateUsing(function (?string $state, WorkflowActionExecution $record) use ($workflowDefinitionDisplay): string {
                        $workflow = $record->workflowInstance?->workflow;

                        return $workflowDefinitionDisplay->workflowName($workflow?->key, $workflow?->name ?? $state);
                    })
                    ->placeholder('—'),
                TextColumn::make('node_key')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.current_node'))
                    ->formatStateUsing(fn (?string $state, WorkflowActionExecution $record) => $workflowDefinitionDisplay->nodeLabel(
                        $record->workflowInstance?->workflow?->key,
                        $state,
                    )),
                TextColumn::make('action_key')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.action_executions.columns.handler'))
                    ->placeholder('—'),
                TextColumn::make('execution_mode')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.action_executions.columns.mode'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $badgePresenter->executionModeLabel($state))
                    ->color('gray'),
                TextColumn::make('status')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $badgePresenter->actionExecutionStatusLabel($state))
                    ->color(fn ($state) => $badgePresenter->actionExecutionStatusColor($state)),
                TextColumn::make('attempts')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.action_executions.columns.attempts'))
                    ->formatStateUsing(fn ($state, WorkflowActionExecution $record): string => (string) $record->attempts.' / '.(string) $record->max_attempts),
                TextColumn::make('next_attempt_at')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.action_executions.columns.next_attempt'))
                    ->formatStateUsing(fn ($state) => $dueDatePresenter->formatDateTime($state))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label((string) __('dbflow-filament::dbflow-filament.tables.columns.created_at'))
                    ->formatStateUsing(fn ($state) => $dueDatePresenter->formatDateTime($state))
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.action_executions.columns.completed_at'))
                    ->state(fn (WorkflowActionExecution $record) => $record->succeeded_at ?? $record->failed_at ?? $record->exhausted_at)
                    ->formatStateUsing(fn ($state) => $dueDatePresenter->formatDateTime($state))
                    ->placeholder('—'),
                TextColumn::make('destination')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.action_executions.columns.destination'))
                    ->state(fn (WorkflowActionExecution $record): string => $safeMetadataPresenter->executionSummary($record)['destination'])
                    ->visible(fn (): bool => app(RuntimeCapabilityGate::class)->outboundWebhookVisible()
                        && WorkflowFilamentPermissions::can('webhook_metadata', 'view')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.action_executions.filters.status'))
                    ->options($this->statusFilterOptions()),
                SelectFilter::make('action_key')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.action_executions.filters.handler'))
                    ->options($this->handlerFilterOptions())
                    ->searchable(),
                SelectFilter::make('execution_mode')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.action_executions.filters.mode'))
                    ->options($this->modeFilterOptions()),
                SelectFilter::make('workflow_id')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.action_executions.filters.workflow'))
                    ->options($this->workflowFilterOptions())
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $workflowId = $data['value'] ?? null;

                        if ($workflowId === null || $workflowId === '') {
                            return $query;
                        }

                        return $query->whereHas(
                            'workflowInstance',
                            static fn (Builder $builder): Builder => $builder->where('workflow_id', $workflowId),
                        );
                    }),
                TernaryFilter::make('retry_due')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.action_executions.filters.retry_due'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query
                            ->where('status', ActionExecutionStatus::Queued->value)
                            ->whereNotNull('next_attempt_at')
                            ->where('next_attempt_at', '<=', now('UTC')),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $builder): void {
                            $builder
                                ->where('status', '!=', ActionExecutionStatus::Queued->value)
                                ->orWhereNull('next_attempt_at')
                                ->orWhere('next_attempt_at', '>', now('UTC'));
                        }),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('exhausted')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.action_executions.filters.exhausted'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('status', ActionExecutionStatus::Exhausted->value),
                        false: fn (Builder $query): Builder => $query->where('status', '!=', ActionExecutionStatus::Exhausted->value),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('blocking')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.action_executions.filters.blocking'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('execution_mode', ActionExecutionMode::ReliableBlocking->value),
                        false: fn (Builder $query): Builder => $query->where('execution_mode', ActionExecutionMode::ReliableNonBlocking->value),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                WorkflowActionExecutionTableActions::view(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading((string) __('dbflow-filament::dbflow-filament.pages.action_executions.empty_state_heading'))
            ->emptyStateDescription((string) __('dbflow-filament::dbflow-filament.pages.action_executions.empty_state_description'));
    }

    /**
     * @return array<string, string>
     */
    protected function statusFilterOptions(): array
    {
        $badgePresenter = app(RuntimeBadgePresenter::class);
        $options = [];

        foreach (ActionExecutionStatus::cases() as $status) {
            $options[$status->value] = $badgePresenter->actionExecutionStatusLabel($status);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    protected function modeFilterOptions(): array
    {
        $badgePresenter = app(RuntimeBadgePresenter::class);
        $options = [];

        foreach (ActionExecutionMode::cases() as $mode) {
            $options[$mode->value] = $badgePresenter->executionModeLabel($mode);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    protected function handlerFilterOptions(): array
    {
        return WorkflowActionExecution::query()
            ->select('action_key')
            ->distinct()
            ->orderBy('action_key')
            ->pluck('action_key', 'action_key')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function workflowFilterOptions(): array
    {
        $workflowDefinitionDisplay = app(WorkflowDefinitionDisplay::class);

        return Workflow::query()
            ->whereIn('id', WorkflowInstance::query()
                ->select('workflow_id')
                ->whereIn('id', WorkflowActionExecution::query()->select('workflow_instance_id')))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Workflow $workflow): array => [
                $workflow->getKey() => $workflowDefinitionDisplay->workflowName($workflow->key, $workflow->name),
            ])
            ->all();
    }
}
