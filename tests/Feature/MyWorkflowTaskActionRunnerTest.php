<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Core\Enums\WorkflowInstanceStatus;
use DbflowLabs\Core\Enums\WorkflowTaskAssignmentStatus;
use DbflowLabs\Core\Enums\WorkflowTaskStatus;
use DbflowLabs\Filament\Support\Actions\MyWorkflowTaskActionRunner;
use DbflowLabs\Filament\Support\Actions\WorkflowTaskActionResult;
use DbflowLabs\Filament\Tests\Concerns\BuildsWorkflowTaskFixtures;
use DbflowLabs\Filament\Tests\Support\TestAuthenticatableUser;
use DbflowLabs\Filament\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

final class MyWorkflowTaskActionRunnerTest extends TestCase
{
    use BuildsWorkflowTaskFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for workflow task action runner tests.');
        }

        parent::setUp();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../vendor/dbflowlabs/core/database/migrations');
    }

    #[Test]
    public function approve_transitions_pending_assignment_through_core_api(): void
    {
        $assignment = $this->createPendingAssignmentForUserId(assigneeUserId: 10);
        $user = new TestAuthenticatableUser(10);

        $result = app(MyWorkflowTaskActionRunner::class)->approve($assignment, $user, 'Approved in test');

        $this->assertTrue($result->successful);
        $this->assertSame(WorkflowTaskActionResult::OUTCOME_SUCCESS, $result->outcome);

        $assignment->refresh();
        $this->assertSame(WorkflowTaskAssignmentStatus::Approved, $assignment->status);
        $this->assertSame(WorkflowTaskStatus::Approved, $assignment->workflowTask?->status);
        $this->assertSame(WorkflowInstanceStatus::Approved, $assignment->workflowTask?->workflowInstance?->status);
    }

    #[Test]
    public function reject_transitions_pending_assignment_through_core_api_with_end_strategy(): void
    {
        config(['dbflow-filament.reject_strategy' => 'end']);

        $assignment = $this->createPendingAssignmentForUserId(assigneeUserId: 10);
        $user = new TestAuthenticatableUser(10);

        $result = app(MyWorkflowTaskActionRunner::class)->reject($assignment, $user, 'Rejected in test');

        $this->assertTrue($result->successful);

        $assignment->refresh();
        $this->assertSame(WorkflowTaskAssignmentStatus::Rejected, $assignment->status);
        $this->assertSame(WorkflowTaskStatus::Rejected, $assignment->workflowTask?->status);
        $this->assertSame(WorkflowInstanceStatus::Rejected, $assignment->workflowTask?->workflowInstance?->status);
    }

    #[Test]
    public function approve_returns_task_not_available_for_other_user(): void
    {
        $assignment = $this->createPendingAssignmentForUserId(assigneeUserId: 10);
        $otherUser = new TestAuthenticatableUser(20);

        $result = app(MyWorkflowTaskActionRunner::class)->approve($assignment, $otherUser);

        $this->assertFalse($result->successful);
        $this->assertSame(WorkflowTaskActionResult::OUTCOME_TASK_NOT_AVAILABLE, $result->outcome);
        $this->assertSame(WorkflowTaskAssignmentStatus::Pending, $assignment->fresh()?->status);
    }

    #[Test]
    public function can_act_on_assignment_requires_pending_task_for_assignee(): void
    {
        $assignment = $this->createPendingAssignmentForUserId(assigneeUserId: 10);
        $user = new TestAuthenticatableUser(10);
        $otherUser = new TestAuthenticatableUser(20);

        $this->assertTrue(MyWorkflowTaskActionRunner::canActOnAssignment($assignment, $user));
        $this->assertFalse(MyWorkflowTaskActionRunner::canActOnAssignment($assignment, $otherUser));
        $this->assertFalse(MyWorkflowTaskActionRunner::canActOnAssignment($assignment, null));
    }

    #[Test]
    public function can_act_on_assignment_matches_string_uuid_assignee_ids(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $assignment = $this->createPendingAssignmentForUserId(assigneeUserId: $uuid);
        $user = new TestAuthenticatableUser($uuid);
        $otherUser = new TestAuthenticatableUser('550e8400-e29b-41d4-a716-446655440001');

        $this->assertTrue(MyWorkflowTaskActionRunner::canActOnAssignment($assignment, $user));
        $this->assertFalse(MyWorkflowTaskActionRunner::canActOnAssignment($assignment, $otherUser));

        $result = app(MyWorkflowTaskActionRunner::class)->approve($assignment, $user, 'Approved with UUID assignee');

        $this->assertTrue($result->successful);
        $this->assertSame(WorkflowTaskAssignmentStatus::Approved, $assignment->fresh()?->status);
    }

    #[Test]
    public function runner_source_has_no_host_namespace_imports(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(MyWorkflowTaskActionRunner::class))->getFileName(),
        );

        $this->assertStringNotContainsString('App\\DBFlow', $source);
        $this->assertStringNotContainsString('DBErp', $source);
        $this->assertStringNotContainsString('PurchaseRequest', $source);
    }
}
