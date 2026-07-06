<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Core\Enums\WorkflowInstanceStatus;
use DbflowLabs\Filament\Support\Actions\WorkflowInstanceActionRunner;
use DbflowLabs\Filament\Support\Actions\WorkflowTaskActionResult;
use DbflowLabs\Filament\Tests\Concerns\BuildsWorkflowTaskFixtures;
use DbflowLabs\Filament\Tests\Support\TestAuthenticatableUser;
use DbflowLabs\Filament\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

final class WorkflowInstanceActionRunnerTest extends TestCase
{
    use BuildsWorkflowTaskFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for workflow instance action runner tests.');
        }

        parent::setUp();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../vendor/dbflowlabs/core/database/migrations');
    }

    #[Test]
    public function cancel_transitions_running_instance_through_core_api(): void
    {
        $assignment = $this->createPendingAssignmentForUserId(assigneeUserId: 10);
        $instance = $assignment->workflowTask?->workflowInstance;
        $this->assertNotNull($instance);

        $user = new TestAuthenticatableUser(10);

        $result = app(WorkflowInstanceActionRunner::class)->cancel($instance, $user, 'Cancelled in test');

        $this->assertTrue($result->successful);
        $this->assertSame(WorkflowInstanceStatus::Cancelled, $instance->fresh()?->status);
    }

    #[Test]
    public function cancel_returns_not_available_for_terminal_instance(): void
    {
        $assignment = $this->createPendingAssignmentForUserId(assigneeUserId: 10);
        $instance = $assignment->workflowTask?->workflowInstance;
        $this->assertNotNull($instance);

        $instance->forceFill(['status' => WorkflowInstanceStatus::Approved])->save();

        $result = app(WorkflowInstanceActionRunner::class)->cancel(
            $instance->fresh(),
            new TestAuthenticatableUser(10),
        );

        $this->assertFalse($result->successful);
        $this->assertSame(WorkflowTaskActionResult::OUTCOME_TASK_NOT_AVAILABLE, $result->outcome);
    }

    #[Test]
    public function runner_source_uses_dbflow_cancel_facade(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(WorkflowInstanceActionRunner::class))->getFileName(),
        );

        $this->assertStringContainsString('DBFlow::cancel', $source);
    }
}
