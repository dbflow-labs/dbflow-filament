<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Pages;

use BackedEnum;
use DbflowLabs\Core\Models\WorkflowActionExecution;
use DbflowLabs\Core\Support\WorkflowDefinitionDisplay;
use DbflowLabs\Filament\Support\Presenters\DueDatePresenter;
use DbflowLabs\Filament\Support\Presenters\RuntimeBadgePresenter;
use DbflowLabs\Filament\Support\Presenters\SafeMetadataPresenter;
use DbflowLabs\Filament\Support\Presenters\WorkflowInstanceRuntimePresenter;
use DbflowLabs\Filament\Support\RuntimeCapabilityGate;
use DbflowLabs\Filament\Support\WorkflowFilamentPermissions;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ViewWorkflowActionExecution extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = null;

    protected string $view = 'dbflow-filament::pages.view-workflow-action-execution';

    public ?WorkflowActionExecution $execution = null;

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        $prefix = trim((string) config('dbflow-filament.route_prefix', 'dbflow'), '/');

        return $prefix.'/workflow-action-executions/{record}';
    }

    public static function canAccess(): bool
    {
        if (! (bool) config('dbflow-filament.enabled', true)) {
            return false;
        }

        if (! app(RuntimeCapabilityGate::class)->reliableActionVisible()) {
            return false;
        }

        $user = Auth::user();

        return WorkflowFilamentPermissions::can('action_executions', 'view', user: $user instanceof Authenticatable ? $user : null);
    }

    public function mount(int $record): void
    {
        $this->execution = WorkflowActionExecution::query()
            ->with(['workflowInstance.workflow'])
            ->withCount('attempts')
            ->findOrFail($record);
    }

    /**
     * @return list<Action>
     */
    public function executionRecoveryActions(): array
    {
        return [];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return $this->executionRecoveryActions();
    }

    public function getTitle(): string|Htmlable
    {
        return (string) __('dbflow-filament::dbflow-filament.pages.view_action_execution.title');
    }

    /**
     * @return array<int|string, string|Htmlable>
     */
    public function getBreadcrumbs(): array
    {
        return [
            WorkflowActionExecutions::getUrl() => WorkflowActionExecutions::getNavigationLabel(),
            $this->getTitle(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function executionOverview(): array
    {
        if ($this->execution === null) {
            return [];
        }

        $badgePresenter = app(RuntimeBadgePresenter::class);
        $dueDatePresenter = app(DueDatePresenter::class);
        $safeMetadataPresenter = app(SafeMetadataPresenter::class);
        $workflowDefinitionDisplay = app(WorkflowDefinitionDisplay::class);
        $summary = $safeMetadataPresenter->executionSummary($this->execution);
        $workflowKey = $this->execution->workflowInstance?->workflow?->key;

        $rows = [
            (string) __('dbflow-filament::dbflow-filament.pages.view_action_execution.overview.id') => '#'.(string) $this->execution->getKey(),
            (string) __('dbflow-filament::dbflow-filament.pages.view_action_execution.overview.workflow') => $workflowDefinitionDisplay->workflowName(
                $workflowKey,
                $this->execution->workflowInstance?->workflow?->name,
            ),
            (string) __('dbflow-filament::dbflow-filament.pages.view_action_execution.overview.node') => $workflowDefinitionDisplay->nodeLabel($workflowKey, $this->execution->node_key),
            (string) __('dbflow-filament::dbflow-filament.pages.view_action_execution.overview.handler') => $summary['handler'],
            (string) __('dbflow-filament::dbflow-filament.pages.view_action_execution.overview.mode') => $summary['mode'],
            (string) __('dbflow-filament::dbflow-filament.pages.view_action_execution.overview.status') => $summary['status'],
            (string) __('dbflow-filament::dbflow-filament.pages.view_action_execution.overview.logical_key') => $summary['logical_key'],
            (string) __('dbflow-filament::dbflow-filament.pages.view_action_execution.overview.visit_sequence') => (string) ($this->execution->visit_sequence ?? '—'),
            (string) __('dbflow-filament::dbflow-filament.pages.view_action_execution.overview.attempts') => (string) $this->execution->attempts.' / '.(string) $this->execution->max_attempts,
            (string) __('dbflow-filament::dbflow-filament.pages.view_action_execution.overview.next_attempt_at') => $dueDatePresenter->formatDateTime($this->execution->next_attempt_at),
            (string) __('dbflow-filament::dbflow-filament.pages.view_action_execution.overview.workflow_advanced_at') => $dueDatePresenter->formatDateTime($this->execution->workflow_advanced_at),
            (string) __('dbflow-filament::dbflow-filament.pages.view_action_execution.overview.last_error') => $summary['last_error'],
        ];

        if (app(RuntimeCapabilityGate::class)->outboundWebhookVisible()
            && WorkflowFilamentPermissions::can('webhook_metadata', 'view')) {
            $rows[(string) __('dbflow-filament::dbflow-filament.pages.view_action_execution.overview.destination')] = $summary['destination'];
            $rows[(string) __('dbflow-filament::dbflow-filament.pages.view_action_execution.overview.idempotency_key')] = $summary['idempotency_key'];
            $rows[(string) __('dbflow-filament::dbflow-filament.pages.view_action_execution.overview.response_status')] = $summary['response_status'];
        }

        return $rows;
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    public function attemptsForDisplay(): Collection
    {
        if ($this->execution === null) {
            return collect();
        }

        if (! WorkflowFilamentPermissions::can('action_attempts', 'view')) {
            return collect();
        }

        return app(WorkflowInstanceRuntimePresenter::class)->actionAttempts($this->execution);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return WorkflowActionExecutions::getNavigationGroup();
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return null;
    }
}
