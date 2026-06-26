<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Core\Enums\WorkflowLogEvent;
use DbflowLabs\Core\Enums\WorkflowTaskStatus;
use DbflowLabs\Filament\Pages\ViewWorkflowInstance;
use DbflowLabs\Filament\Support\Presenters\WorkflowInstanceDetailPresenter;
use DbflowLabs\Filament\Support\Presenters\WorkflowInstanceTimelinePresenter;
use DbflowLabs\Filament\Tests\Concerns\BuildsWorkflowInstanceFixtures;
use DbflowLabs\Filament\Tests\Concerns\BuildsWorkflowTaskFixtures;
use DbflowLabs\Filament\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

final class WorkflowInstanceTimelinePresenterTest extends TestCase
{
    use BuildsWorkflowInstanceFixtures;
    use BuildsWorkflowTaskFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for workflow instance timeline tests.');
        }

        parent::setUp();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../vendor/dbflowlabs/core/database/migrations');
    }

    #[Test]
    public function presenter_maps_event_names_to_package_translations(): void
    {
        $presenter = app(WorkflowInstanceTimelinePresenter::class);

        $this->assertSame('Workflow started', $presenter->eventLabel(WorkflowLogEvent::WorkflowStarted->value));
        $this->assertSame('Task approved', $presenter->eventLabel(WorkflowLogEvent::TaskApproved->value));
        $this->assertSame('Unknown event', $presenter->eventLabel('custom_unknown_event'));
    }

    #[Test]
    public function presenter_orders_timeline_entries_chronologically(): void
    {
        $instance = $this->createWorkflowInstance();
        $task = $this->createWorkflowTask($instance);

        $this->createWorkflowLog(
            instance: $instance,
            event: WorkflowLogEvent::WorkflowStarted,
            createdAt: now()->subMinutes(2),
        );

        $this->createWorkflowLog(
            instance: $instance,
            event: WorkflowLogEvent::TaskCreated,
            task: $task,
            createdAt: now()->subMinute(),
        );

        $this->createWorkflowLog(
            instance: $instance,
            event: WorkflowLogEvent::TaskApproved,
            task: $task,
            comment: 'Approved with comment',
            payload: ['from_node' => 'review', 'to_node' => 'approved'],
            createdAt: now(),
        );

        $timeline = app(WorkflowInstanceTimelinePresenter::class)->timelineForInstance($instance->fresh(['workflow']));

        $this->assertCount(3, $timeline);
        $this->assertSame(WorkflowLogEvent::WorkflowStarted->value, $timeline[0]['event']);
        $this->assertSame(WorkflowLogEvent::TaskCreated->value, $timeline[1]['event']);
        $this->assertSame(WorkflowLogEvent::TaskApproved->value, $timeline[2]['event']);
        $this->assertSame('Approved with comment', $timeline[2]['comment']);
    }

    #[Test]
    public function presenter_uses_user_display_resolver_for_actor_names(): void
    {
        $instance = $this->createWorkflowInstance(startedByUserId: 42);
        $this->ensureUserExists(42);

        $this->createWorkflowLog(
            instance: $instance,
            event: WorkflowLogEvent::WorkflowStarted,
            actorUserId: 42,
        );

        $timeline = app(WorkflowInstanceTimelinePresenter::class)->timelineForInstance($instance->fresh());

        $this->assertSame('Test User 42', $timeline[0]['actor_name']);
    }

    #[Test]
    public function presenter_uses_system_label_for_null_actor(): void
    {
        $presenter = app(WorkflowInstanceTimelinePresenter::class);

        $this->assertSame('System', $presenter->actorDisplayName(null));
    }

    #[Test]
    public function presenter_only_includes_logs_for_requested_instance(): void
    {
        $first = $this->createWorkflowInstance();
        $second = $this->createWorkflowInstance();

        $this->createWorkflowLog($first, WorkflowLogEvent::WorkflowStarted);
        $this->createWorkflowLog($second, WorkflowLogEvent::WorkflowCompleted);

        $timeline = app(WorkflowInstanceTimelinePresenter::class)->timelineForInstance($first->fresh());

        $this->assertCount(1, $timeline);
        $this->assertSame(WorkflowLogEvent::WorkflowStarted->value, $timeline[0]['event']);
    }

    #[Test]
    public function detail_presenter_finds_task_comment_in_logs(): void
    {
        $instance = $this->createWorkflowInstance();
        $task = $this->createWorkflowTask($instance, taskStatus: WorkflowTaskStatus::Approved);

        $this->createWorkflowLog(
            instance: $instance,
            event: WorkflowLogEvent::TaskApproved,
            task: $task,
            comment: 'Task result comment',
        );

        $comment = app(WorkflowInstanceDetailPresenter::class)->taskComment($task->fresh());

        $this->assertSame('Task result comment', $comment);
    }

    #[Test]
    public function view_page_prepares_empty_timeline_safely(): void
    {
        $instance = $this->createWorkflowInstance();
        $page = new ViewWorkflowInstance;
        $page->instance = $instance->fresh(['workflow', 'workflowVersion', 'startedBy', 'tasks.assignments.assignee']);

        $this->assertSame([], $page->timelineForDisplay());
    }
}
