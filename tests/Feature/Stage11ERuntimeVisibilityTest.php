<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Core\Capabilities\RuntimeCapabilityRegistry;
use DbflowLabs\Core\Enums\ActionExecutionMode;
use DbflowLabs\Core\Enums\ActionExecutionStatus;
use DbflowLabs\Core\Enums\AssignmentSource;
use DbflowLabs\Core\Enums\RuntimeCapability;
use DbflowLabs\Core\Enums\SlaEventStatus;
use DbflowLabs\Core\Enums\SlaEventType;
use DbflowLabs\Core\Enums\WorkflowLogEvent;
use DbflowLabs\Core\Models\WorkflowActionAttempt;
use DbflowLabs\Core\Models\WorkflowActionExecution;
use DbflowLabs\Core\Models\WorkflowDelegation;
use DbflowLabs\Core\Models\WorkflowSlaEvent;
use DbflowLabs\Filament\Contracts\PermissionChecker;
use DbflowLabs\Filament\Pages\MyWorkflowTasks;
use DbflowLabs\Filament\Pages\ViewWorkflowActionExecution;
use DbflowLabs\Filament\Pages\WorkflowActionExecutions;
use DbflowLabs\Filament\Pages\WorkflowDelegations;
use DbflowLabs\Filament\Support\Actions\MyWorkflowTaskTableActions;
use DbflowLabs\Filament\Support\DBFlowFilamentPanel;
use DbflowLabs\Filament\Support\Presenters\AssignmentActorPresenter;
use DbflowLabs\Filament\Support\Presenters\RuntimeBadgePresenter;
use DbflowLabs\Filament\Support\Presenters\SafeMetadataPresenter;
use DbflowLabs\Filament\Support\Presenters\WorkflowInstanceTimelinePresenter;
use DbflowLabs\Filament\Support\WorkflowFilamentPermissions;
use DbflowLabs\Filament\Tests\Concerns\BuildsWorkflowTaskFixtures;
use DbflowLabs\Filament\Tests\Support\MapPermissionChecker;
use DbflowLabs\Filament\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

final class Stage11ERuntimeVisibilityTest extends TestCase
{
    use BuildsWorkflowTaskFixtures;
    use RefreshDatabase;

    private const SENTINEL = 'SENTINEL_SECRET_VALUE_9f3a2c1b';

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for Stage 1.1-E tests.');
        }

        parent::setUp();
        app(RuntimeCapabilityRegistry::class)->registerStage11DWebhookDefaults();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../vendor/dbflowlabs/core/database/migrations');
    }

    #[Test]
    public function composer_requires_core_v1_1(): void
    {
        $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('^1.1', $composer['require']['dbflowlabs/core']);
    }

    #[Test]
    public function package_boots_without_pro(): void
    {
        $this->assertFalse(class_exists('DbflowLabs\\FilamentPro\\Providers\\DBFlowFilamentProServiceProvider'));
        $this->assertTrue(class_exists(MyWorkflowTasks::class));
    }

    #[Test]
    public function direct_v1_assignment_falls_back_to_assignee_user_id(): void
    {
        $assignment = $this->createPendingAssignmentForUserId(7);
        $presenter = app(AssignmentActorPresenter::class);
        $actors = $presenter->displayActors($assignment);

        $this->assertFalse($actors['show_both']);
        $this->assertSame('Test User 7', $actors['combined']);
    }

    #[Test]
    public function original_and_effective_actors_remain_distinct_for_delegation(): void
    {
        $assignment = $this->createPendingAssignmentForUserId(7);
        $assignment->forceFill([
            'original_assignee_user_id' => '7',
            'effective_assignee_user_id' => '8',
            'assignment_source' => AssignmentSource::Delegation,
            'delegation_id' => 1,
        ])->save();

        $this->ensureUserExists(8);
        $actors = app(AssignmentActorPresenter::class)->displayActors($assignment->fresh());

        $this->assertTrue($actors['show_both']);
        $this->assertSame('Test User 7', $actors['original']);
        $this->assertSame('Test User 8', $actors['effective']);
    }

    #[Test]
    public function runtime_badges_render_known_and_unknown_values(): void
    {
        $badges = app(RuntimeBadgePresenter::class);

        $this->assertSame('Delegated', $badges->assignmentSourceLabel(AssignmentSource::Delegation));
        $this->assertSame('Unknown', $badges->assignmentSourceLabel('future_source'));
        $this->assertSame('Queued', $badges->actionExecutionStatusLabel(ActionExecutionStatus::Queued));
        $this->assertSame('Reliable Blocking', $badges->executionModeLabel(ActionExecutionMode::ReliableBlocking));
    }

    #[Test]
    public function webhook_metadata_is_redacted_and_sentinel_never_surfaces(): void
    {
        $presenter = app(SafeMetadataPresenter::class);
        $execution = new WorkflowActionExecution([
            'action_key' => 'outbound_webhook',
            'execution_mode' => ActionExecutionMode::ReliableBlocking,
            'status' => ActionExecutionStatus::Failed,
            'logical_execution_key' => 'instance:1:node:notify:visit:1',
            'response_status' => 500,
            'last_error' => 'failed with '.self::SENTINEL,
            'payload_snapshot' => [
                'url' => 'https://api.example.com/hooks/dbflow?token='.self::SENTINEL,
                'method' => 'POST',
            ],
            'result_metadata' => [
                'status_code' => 500,
                'body' => 'Authorization: Bearer '.self::SENTINEL,
            ],
        ]);

        $summary = $presenter->executionSummary($execution);
        $rendered = json_encode($summary, JSON_THROW_ON_ERROR);

        $this->assertSame('api.example.com/hooks/dbflow', $summary['destination']);
        $this->assertSame('500', $summary['response_status']);
        $this->assertStringNotContainsString(self::SENTINEL, $rendered);
        $this->assertStringNotContainsString('?token=', $rendered);
        $this->assertFalse($presenter->containsForbiddenContent($rendered));
    }

    #[Test]
    public function attempt_summary_reads_core_status_code_field(): void
    {
        $attempt = new WorkflowActionAttempt([
            'attempt_number' => 1,
            'status' => 'failed',
            'last_error' => null,
            'request_metadata' => null,
            'response_metadata' => [
                'status_code' => 403,
                'body' => '{"error":"forbidden"}',
            ],
        ]);

        $summary = app(SafeMetadataPresenter::class)->attemptSummary($attempt);

        $this->assertSame('403', $summary['response_status']);
        $this->assertSame('—', $summary['destination']);
        $this->assertSame('—', $summary['method']);
    }

    #[Test]
    public function operational_pages_require_explicit_authorization(): void
    {
        $this->app->instance(PermissionChecker::class, new MapPermissionChecker([], default: false));

        $this->assertFalse(WorkflowDelegations::canAccess());
        $this->assertFalse(WorkflowActionExecutions::canAccess());
        $this->assertFalse(ViewWorkflowActionExecution::canAccess());
    }

    #[Test]
    public function operational_pages_are_hidden_without_permissions_but_tasks_remain_available(): void
    {
        $this->app->instance(PermissionChecker::class, new MapPermissionChecker([
            WorkflowFilamentPermissions::ability('tasks', 'view') => true,
        ]));

        $this->assertTrue(MyWorkflowTasks::canAccess());
        $this->assertFalse(WorkflowDelegations::canAccess());
        $this->assertFalse(WorkflowActionExecutions::canAccess());
    }

    #[Test]
    public function panel_registers_runtime_pages_when_enabled(): void
    {
        config([
            'dbflow-filament.enable_delegations_page' => true,
            'dbflow-filament.enable_action_executions_page' => true,
        ]);

        $this->app->instance(PermissionChecker::class, new MapPermissionChecker([
            WorkflowFilamentPermissions::ability('delegations', 'view_any') => true,
            WorkflowFilamentPermissions::ability('action_executions', 'view_any') => true,
            WorkflowFilamentPermissions::ability('action_executions', 'view') => true,
        ]));

        $pages = DBFlowFilamentPanel::pageClasses();

        $this->assertContains(WorkflowDelegations::class, $pages);
        $this->assertContains(WorkflowActionExecutions::class, $pages);
        $this->assertContains(ViewWorkflowActionExecution::class, $pages);
    }

    #[Test]
    public function task_table_actions_do_not_include_management_controls(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(MyWorkflowTaskTableActions::class))->getFileName());

        foreach (['retry', 'skip', 'cancelExecution', 'createDelegation', 'revokeDelegation'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }

        $this->assertStringContainsString('viewTaskRuntime', $source);
        $this->assertStringContainsString('approveTask', $source);
    }

    #[Test]
    public function timeline_unknown_event_falls_back_safely(): void
    {
        $presenter = app(WorkflowInstanceTimelinePresenter::class);

        $this->assertSame('Unknown event', $presenter->eventLabel('totally_new_event'));
        $this->assertSame(
            'Action execution queued',
            $presenter->eventLabel(WorkflowLogEvent::ActionExecutionQueued->value),
        );
    }

    #[Test]
    public function instance_runtime_queries_are_bounded(): void
    {
        $assignment = $this->createPendingAssignmentForUserId(1);
        $task = $assignment->workflowTask;
        $instance = $task?->workflowInstance;
        $this->assertNotNull($instance);

        WorkflowSlaEvent::query()->create([
            'workflow_task_id' => $task->getKey(),
            'workflow_instance_id' => $instance->getKey(),
            'node_key' => $task->node_key,
            'event_type' => SlaEventType::Reminder,
            'sequence' => 1,
            'scheduled_at' => now('UTC'),
            'status' => SlaEventStatus::Completed,
            'idempotency_key' => 'sla:test:1',
            'attempts' => 1,
            'max_attempts' => 3,
            'processed_at' => now('UTC'),
            'policy_snapshot' => ['source' => 'v1.1_sla'],
        ]);

        $execution = WorkflowActionExecution::query()->create([
            'workflow_instance_id' => $instance->getKey(),
            'workflow_task_id' => $task->getKey(),
            'node_key' => $task->node_key,
            'action_key' => 'outbound_webhook',
            'execution_mode' => ActionExecutionMode::ReliableNonBlocking,
            'status' => ActionExecutionStatus::Succeeded,
            'logical_execution_key' => 'instance:'.$instance->getKey().':node:notify:visit:1',
            'visit_sequence' => 1,
            'attempts' => 1,
            'max_attempts' => 3,
            'queued_at' => now('UTC'),
            'succeeded_at' => now('UTC'),
            'node_snapshot' => ['key' => $task->node_key, 'type' => 'action'],
            'payload_snapshot' => [
                'url' => 'https://api.example.com/hook',
                'method' => 'POST',
            ],
            'result_metadata' => [
                'status_code' => 200,
                'body' => '{"ok":true}',
            ],
        ]);

        WorkflowActionAttempt::query()->create([
            'workflow_action_execution_id' => $execution->getKey(),
            'attempt_number' => 1,
            'status' => 'succeeded',
            'started_at' => now('UTC'),
            'completed_at' => now('UTC'),
            'request_metadata' => null,
            'response_metadata' => [
                'status_code' => 200,
                'body' => '{"ok":true}',
            ],
        ]);

        $this->app->instance(PermissionChecker::class, new MapPermissionChecker([
            WorkflowFilamentPermissions::ability('sla_events', 'view') => true,
            WorkflowFilamentPermissions::ability('action_executions', 'view_any') => true,
            WorkflowFilamentPermissions::ability('action_attempts', 'view') => true,
        ]));

        $page = new \DbflowLabs\Filament\Pages\ViewWorkflowInstance;
        $page->instance = $instance->fresh(['workflow', 'tasks.assignments']);

        $this->assertCount(1, $page->slaEventsForDisplay());
        $this->assertCount(1, $page->actionExecutionsForDisplay());
        $this->assertGreaterThan(0, $page->assignmentHistoryForDisplay()->count());

        $columnsWithoutWebhook = $page->actionExecutionColumnsForDisplay();
        $this->assertArrayNotHasKey('destination', $columnsWithoutWebhook);
        $this->assertArrayNotHasKey('destination', $page->actionExecutionsForDisplay()->first() ?? []);

        $this->app->instance(PermissionChecker::class, new MapPermissionChecker([
            WorkflowFilamentPermissions::ability('sla_events', 'view') => true,
            WorkflowFilamentPermissions::ability('action_executions', 'view_any') => true,
            WorkflowFilamentPermissions::ability('action_attempts', 'view') => true,
            WorkflowFilamentPermissions::ability('webhook_metadata', 'view') => true,
        ]));

        $columnsWithWebhook = $page->actionExecutionColumnsForDisplay();
        $this->assertArrayHasKey('destination', $columnsWithWebhook);
        $this->assertSame(
            'api.example.com/hook',
            $page->actionExecutionsForDisplay()->first()['destination'] ?? null,
        );
    }

    #[Test]
    public function delegation_page_source_is_read_only(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(WorkflowDelegations::class))->getFileName());

        foreach (['CreateAction', 'EditAction', 'DeleteAction', 'createDelegation', 'revokeDelegation', 'migratePendingTasks'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }
}
