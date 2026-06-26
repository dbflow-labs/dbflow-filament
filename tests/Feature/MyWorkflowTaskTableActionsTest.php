<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Core\Models\WorkflowTaskAssignment;
use DbflowLabs\Filament\Contracts\PermissionChecker;
use DbflowLabs\Filament\Support\Actions\MyWorkflowTaskTableActions;
use DbflowLabs\Filament\Tests\Concerns\BuildsWorkflowTaskFixtures;
use DbflowLabs\Filament\Tests\Support\TestAuthenticatableUser;
use DbflowLabs\Filament\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

final class MyWorkflowTaskTableActionsTest extends TestCase
{
    use BuildsWorkflowTaskFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for workflow task table action tests.');
        }

        parent::setUp();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../vendor/dbflowlabs/core/database/migrations');
    }

    #[Test]
    public function approve_and_reject_actions_use_package_translations(): void
    {
        $approve = MyWorkflowTaskTableActions::approve();
        $reject = MyWorkflowTaskTableActions::reject();

        $this->assertSame('Approve', $approve->getLabel());
        $this->assertSame('Reject', $reject->getLabel());
        $this->assertSame('approveTask', $approve->getName());
        $this->assertSame('rejectTask', $reject->getName());
    }

    #[Test]
    public function visibility_respects_permission_checker_abilities(): void
    {
        $assignment = $this->createPendingAssignmentForUserId(assigneeUserId: 10);
        $user = new TestAuthenticatableUser(10);

        $this->app->instance(PermissionChecker::class, new class implements PermissionChecker
        {
            public function can(mixed $user, string $ability, mixed $record = null): bool
            {
                return $ability === 'dbflow.tasks.approve';
            }
        });

        $this->assertTrue(MyWorkflowTaskTableActions::canShowApprove($assignment, $user));
        $this->assertFalse(MyWorkflowTaskTableActions::canShowReject($assignment, $user));
    }

    #[Test]
    public function unauthenticated_users_cannot_see_actions(): void
    {
        $assignment = $this->createPendingAssignmentForUserId(assigneeUserId: 10);

        $this->assertFalse(MyWorkflowTaskTableActions::canShowApprove($assignment, null));
        $this->assertFalse(MyWorkflowTaskTableActions::canShowReject($assignment, null));
    }

    #[Test]
    public function actions_are_hidden_when_feature_toggle_is_disabled(): void
    {
        config(['dbflow-filament.enable_my_task_actions' => false]);

        $assignment = $this->createPendingAssignmentForUserId(assigneeUserId: 10);
        $user = new TestAuthenticatableUser(10);

        $this->assertFalse(MyWorkflowTaskTableActions::canShowApprove($assignment, $user));
        $this->assertFalse(MyWorkflowTaskTableActions::canShowReject($assignment, $user));
    }

    #[Test]
    public function table_actions_source_has_no_host_namespace_imports(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(MyWorkflowTaskTableActions::class))->getFileName(),
        );

        $this->assertStringNotContainsString('App\\DBFlow', $source);
        $this->assertStringNotContainsString('dbflow.actions.approve', $source);
        $this->assertStringNotContainsString('dberp.', $source);
    }
}
