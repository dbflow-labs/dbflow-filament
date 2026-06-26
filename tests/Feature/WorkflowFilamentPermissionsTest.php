<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Filament\Contracts\PermissionChecker;
use DbflowLabs\Filament\Support\WorkflowFilamentPermissions;
use DbflowLabs\Filament\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class WorkflowFilamentPermissionsTest extends TestCase
{
    #[Test]
    public function default_abilities_use_recommended_strings(): void
    {
        $this->assertSame('dbflow.tasks.view', WorkflowFilamentPermissions::ability('tasks', 'view'));
        $this->assertSame('dbflow.tasks.approve', WorkflowFilamentPermissions::ability('tasks', 'approve'));
        $this->assertSame('dbflow.workflow_instances.view_any', WorkflowFilamentPermissions::ability('workflow_instances', 'view_any'));
        $this->assertSame('dbflow.definitions.publish', WorkflowFilamentPermissions::ability('definitions', 'publish'));
        $this->assertSame('dbflow.definitions.copy', WorkflowFilamentPermissions::ability('definitions', 'copy'));
    }

    #[Test]
    public function nested_config_overrides_default_ability(): void
    {
        config(['dbflow-filament.permissions.tasks.view' => 'custom.tasks.view']);

        $this->assertSame('custom.tasks.view', WorkflowFilamentPermissions::ability('tasks', 'view'));
    }

    #[Test]
    public function legacy_flat_my_tasks_key_is_supported(): void
    {
        config([
            'dbflow-filament.permissions.tasks.view' => null,
            'dbflow-filament.permissions.my_tasks' => 'legacy.my_tasks',
        ]);

        $this->assertSame('legacy.my_tasks', WorkflowFilamentPermissions::ability('tasks', 'view'));
    }

    #[Test]
    public function legacy_string_workflow_instances_key_is_supported_for_view_any(): void
    {
        config([
            'dbflow-filament.permissions.workflow_instances' => 'legacy.instances',
        ]);

        $this->assertSame('legacy.instances', WorkflowFilamentPermissions::ability('workflow_instances', 'view_any'));
    }

    #[Test]
    public function permission_checker_controls_access(): void
    {
        $checker = new class implements PermissionChecker
        {
            public function can(mixed $user, string $ability, mixed $record = null): bool
            {
                return $ability === 'dbflow.tasks.approve';
            }
        };

        $this->app->instance(PermissionChecker::class, $checker);

        $this->assertTrue(WorkflowFilamentPermissions::can('tasks', 'approve'));
        $this->assertFalse(WorkflowFilamentPermissions::can('tasks', 'view'));
    }

    #[Test]
    public function package_is_disabled_blocks_access(): void
    {
        config(['dbflow-filament.enabled' => false]);

        $this->assertFalse(WorkflowFilamentPermissions::can('tasks', 'view'));
    }
}
