<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Core\Services\WorkflowTaskQueryService;
use DbflowLabs\Filament\Support\Queries\MyWorkflowTasksQuery;
use DbflowLabs\Filament\Tests\Concerns\BuildsWorkflowTaskFixtures;
use DbflowLabs\Filament\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

final class MyWorkflowTasksQueryTest extends TestCase
{
    use BuildsWorkflowTaskFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for workflow task query fixture tests.');
        }

        parent::setUp();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../vendor/dbflowlabs/core/database/migrations');
    }

    #[Test]
    public function pending_for_user_returns_only_pending_assignments_for_target_user(): void
    {
        $mine = $this->createPendingAssignmentForUserId(assigneeUserId: 10);
        $this->createPendingAssignmentForUserId(assigneeUserId: 20);

        $results = app(MyWorkflowTasksQuery::class)->pendingForUser(10)->get();

        $this->assertCount(1, $results);
        $this->assertSame($mine->getKey(), $results->first()?->getKey());
    }

    #[Test]
    public function pending_for_user_excludes_non_pending_assignment_statuses(): void
    {
        $this->createAssignmentForUserId(
            assigneeUserId: 10,
            assignmentStatus: \DbflowLabs\Core\Enums\WorkflowTaskAssignmentStatus::Approved,
            taskStatus: \DbflowLabs\Core\Enums\WorkflowTaskStatus::Approved,
        );

        $this->assertCount(0, app(MyWorkflowTasksQuery::class)->pendingForUser(10)->get());
    }

    #[Test]
    public function pending_for_user_excludes_assignments_when_task_is_not_pending(): void
    {
        $this->createAssignmentForUserId(
            assigneeUserId: 10,
            assignmentStatus: \DbflowLabs\Core\Enums\WorkflowTaskAssignmentStatus::Pending,
            taskStatus: \DbflowLabs\Core\Enums\WorkflowTaskStatus::Approved,
        );

        $this->assertCount(0, app(MyWorkflowTasksQuery::class)->pendingForUser(10)->get());
    }

    #[Test]
    public function pending_for_user_eager_loads_workflow_version_via_core_query_service(): void
    {
        $this->createPendingAssignmentForUserId(assigneeUserId: 10);

        $assignment = app(MyWorkflowTasksQuery::class)->pendingForUser('10')->firstOrFail();

        $this->assertTrue($assignment->relationLoaded('workflowTask'));
        $this->assertTrue($assignment->workflowTask?->relationLoaded('workflowInstance'));
        $this->assertTrue($assignment->workflowTask?->workflowInstance?->relationLoaded('workflowVersion'));
    }

    #[Test]
    public function query_class_delegates_to_core_workflow_task_query_service(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(MyWorkflowTasksQuery::class))->getFileName(),
        );

        $this->assertStringContainsString(WorkflowTaskQueryService::class, $source);
        $this->assertStringContainsString('pendingAssignmentsQueryForUser', $source);
    }
}
