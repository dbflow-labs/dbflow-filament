<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Core\Models\WorkflowTaskAssignment;
use DbflowLabs\Filament\Support\Actions\MyWorkflowTaskTableActions;
use DbflowLabs\Filament\Support\Actions\WorkflowInstanceHeaderActions;
use DbflowLabs\Filament\Support\Queries\MyWorkflowTasksQuery;
use DbflowLabs\Filament\Support\WorkflowableShowUrlResolver;
use DbflowLabs\Filament\Tests\Concerns\BuildsWorkflowTaskFixtures;
use DbflowLabs\Filament\Tests\Models\TestRoutableWorkflowSubject;
use DbflowLabs\Filament\Tests\Models\TestWorkflowSubject;
use DbflowLabs\Filament\Tests\Support\TestAuthenticatableUser;
use DbflowLabs\Filament\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

final class CoreRuntimeFilamentUiTest extends TestCase
{
    use BuildsWorkflowTaskFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for Core runtime Filament UI tests.');
        }

        parent::setUp();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../vendor/dbflowlabs/core/database/migrations');
    }

    #[Test]
    public function task_actions_are_hidden_when_core_runtime_is_disabled(): void
    {
        config(['dbflow.enabled' => false]);

        $assignment = $this->createPendingAssignmentForUserId(assigneeUserId: 10);
        $user = new TestAuthenticatableUser(10);

        $this->assertFalse(MyWorkflowTaskTableActions::canShowApprove($assignment, $user));
        $this->assertFalse(MyWorkflowTaskTableActions::canShowReject($assignment, $user));
        $this->assertFalse(MyWorkflowTaskTableActions::canShowReassign($assignment, $user));
    }

    #[Test]
    public function instance_cancel_action_is_hidden_when_core_runtime_is_disabled(): void
    {
        config(['dbflow.enabled' => false]);

        $assignment = $this->createPendingAssignmentForUserId(assigneeUserId: 10);
        $instance = $assignment->workflowTask?->workflowInstance;

        $this->assertNotNull($instance);
        $this->assertFalse(WorkflowInstanceHeaderActions::canShowCancel($instance, new TestAuthenticatableUser(10)));
    }

    #[Test]
    public function workflowable_show_url_resolver_returns_null_for_non_resolvable_models(): void
    {
        $subject = TestWorkflowSubject::query()->create();

        $this->assertNull(app(WorkflowableShowUrlResolver::class)->resolve($subject));
    }

    #[Test]
    public function workflowable_show_url_resolver_returns_url_for_workflow_route_resolvable_models(): void
    {
        $subject = TestRoutableWorkflowSubject::query()->create();

        $this->assertSame(
            '/test-workflow-subjects/'.$subject->getKey(),
            app(WorkflowableShowUrlResolver::class)->resolve($subject),
        );
    }

    #[Test]
    public function pending_query_eager_loads_workflowable_for_subject_links(): void
    {
        $this->createPendingAssignmentForUserId(
            assigneeUserId: 10,
            workflowableType: TestRoutableWorkflowSubject::class,
        );

        /** @var WorkflowTaskAssignment $assignment */
        $assignment = app(MyWorkflowTasksQuery::class)->pendingForUser('10')->firstOrFail();

        $this->assertTrue($assignment->relationLoaded('workflowTask'));
        $this->assertTrue($assignment->workflowTask?->relationLoaded('workflowInstance'));
        $this->assertTrue($assignment->workflowTask?->workflowInstance?->relationLoaded('workflowable'));
    }
}
