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
use DbflowLabs\Core\Enums\DelegationLifecycle;
use DbflowLabs\Core\Models\WorkflowDelegation;
use DbflowLabs\Filament\Support\Presenters\AssignmentActorPresenter;
use DbflowLabs\Filament\Support\Presenters\DueDatePresenter;
use DbflowLabs\Filament\Support\Presenters\RuntimeBadgePresenter;
use DbflowLabs\Filament\Support\Queries\WorkflowDelegationsQuery;
use DbflowLabs\Filament\Support\RuntimeCapabilityGate;
use DbflowLabs\Filament\Support\WorkflowFilamentPermissions;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class WorkflowDelegations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = null;

    protected string $view = 'dbflow-filament::pages.workflow-delegations';

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        $prefix = trim((string) config('dbflow-filament.route_prefix', 'dbflow'), '/');

        return $prefix.'/workflow-delegations';
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
        return (string) __('dbflow-filament::dbflow-filament.pages.delegations.navigation_label');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('dbflow-filament.navigation_sort.delegations');

        return is_numeric($sort) ? (int) $sort : 30;
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

        if (! (bool) config('dbflow-filament.enable_delegations_page', true)) {
            return false;
        }

        if (! app(RuntimeCapabilityGate::class)->delegationVisible()) {
            return false;
        }

        $user = Auth::user();

        return WorkflowFilamentPermissions::can('delegations', 'view_any', user: $user instanceof Authenticatable ? $user : null);
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
        return (string) __('dbflow-filament::dbflow-filament.pages.delegations.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return (string) __('dbflow-filament::dbflow-filament.pages.delegations.subheading');
    }

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    public function table(Table $table): Table
    {
        $actorPresenter = app(AssignmentActorPresenter::class);
        $badgePresenter = app(RuntimeBadgePresenter::class);
        $dueDatePresenter = app(DueDatePresenter::class);

        return $table
            ->query(fn (): Builder => app(WorkflowDelegationsQuery::class)->baseQuery())
            ->columns([
                TextColumn::make('id')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.delegations.columns.id'))
                    ->sortable(),
                TextColumn::make('delegator_user_id')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.delegations.columns.delegator'))
                    ->formatStateUsing(fn ($state) => $actorPresenter->labelForUserId($state)),
                TextColumn::make('delegate_user_id')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.delegations.columns.delegate'))
                    ->formatStateUsing(fn ($state) => $actorPresenter->labelForUserId($state)),
                TextColumn::make('lifecycle')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.delegations.columns.lifecycle'))
                    ->badge()
                    ->state(fn (WorkflowDelegation $record): string => $badgePresenter->delegationLifecycleLabel($record->lifecycle()))
                    ->color(fn (WorkflowDelegation $record): string => $badgePresenter->delegationLifecycleColor($record->lifecycle())),
                TextColumn::make('workflow_key')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.delegations.columns.workflow_key'))
                    ->placeholder('—'),
                TextColumn::make('node_key')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.delegations.columns.node_key'))
                    ->placeholder('—'),
                TextColumn::make('starts_at')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.delegations.columns.starts_at'))
                    ->formatStateUsing(fn ($state) => $dueDatePresenter->formatDateTime($state))
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.delegations.columns.ends_at'))
                    ->formatStateUsing(fn ($state) => $dueDatePresenter->formatDateTime($state))
                    ->sortable(),
                TextColumn::make('revoked_at')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.delegations.columns.revoked_at'))
                    ->formatStateUsing(fn ($state) => $dueDatePresenter->formatDateTime($state))
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('lifecycle')
                    ->label((string) __('dbflow-filament::dbflow-filament.pages.delegations.filters.lifecycle'))
                    ->options($this->lifecycleFilterOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! is_string($value) || $value === '') {
                            return $query;
                        }

                        $now = now('UTC');

                        return match ($value) {
                            DelegationLifecycle::Revoked->value => $query->whereNotNull('revoked_at'),
                            DelegationLifecycle::Scheduled->value => $query
                                ->whereNull('revoked_at')
                                ->where('starts_at', '>', $now),
                            DelegationLifecycle::Expired->value => $query
                                ->whereNull('revoked_at')
                                ->where('ends_at', '<=', $now),
                            DelegationLifecycle::Active->value => $query
                                ->whereNull('revoked_at')
                                ->where('starts_at', '<=', $now)
                                ->where('ends_at', '>', $now),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions($this->delegationRecordActions())
            ->headerActions($this->delegationHeaderActions())
            ->toolbarActions([])
            ->emptyStateHeading((string) __('dbflow-filament::dbflow-filament.pages.delegations.empty_state_heading'))
            ->emptyStateDescription((string) __('dbflow-filament::dbflow-filament.pages.delegations.empty_state_description'));
    }

    /**
     * @return list<Action>
     */
    protected function delegationHeaderActions(): array
    {
        return [];
    }

    /**
     * @return list<Action>
     */
    protected function delegationRecordActions(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    protected function lifecycleFilterOptions(): array
    {
        $badgePresenter = app(RuntimeBadgePresenter::class);
        $options = [];

        foreach (DelegationLifecycle::cases() as $lifecycle) {
            $options[$lifecycle->value] = $badgePresenter->delegationLifecycleLabel($lifecycle);
        }

        return $options;
    }
}
