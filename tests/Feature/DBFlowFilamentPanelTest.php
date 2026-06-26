<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Filament\Pages\MyWorkflowTasks;
use DbflowLabs\Filament\Resources\WorkflowResource;
use DbflowLabs\Filament\Support\DBFlowFilamentPanel;
use DbflowLabs\Filament\Tests\TestCase;
use Filament\Panel;
use PHPUnit\Framework\Attributes\Test;

final class DBFlowFilamentPanelTest extends TestCase
{
    #[Test]
    public function page_classes_returns_an_array(): void
    {
        $this->assertIsArray(DBFlowFilamentPanel::pageClasses());
    }

    #[Test]
    public function resource_classes_returns_an_array(): void
    {
        $this->assertIsArray(DBFlowFilamentPanel::resourceClasses());
    }

    #[Test]
    public function registrar_excludes_page_class_when_disabled(): void
    {
        config(['dbflow-filament.enable_my_tasks_page' => false]);

        $this->assertNotContains(MyWorkflowTasks::class, DBFlowFilamentPanel::pageClasses());
    }

    #[Test]
    public function registrar_includes_workflow_resource_when_enabled(): void
    {
        config(['dbflow-filament.enable_workflow_definition_resource' => true]);

        $this->assertContains(WorkflowResource::class, DBFlowFilamentPanel::resourceClasses());
    }

    #[Test]
    public function register_returns_panel_instance_when_no_components_are_enabled(): void
    {
        config([
            'dbflow-filament.enable_my_tasks_page' => false,
            'dbflow-filament.enable_workflow_instances_page' => false,
            'dbflow-filament.enable_workflow_definition_resource' => false,
        ]);

        $panel = Panel::make()->id('test');

        $registered = DBFlowFilamentPanel::register($panel);

        $this->assertInstanceOf(Panel::class, $registered);
        $this->assertSame([], $registered->getPages());
        $this->assertSame([], $registered->getResources());
    }

    #[Test]
    public function register_is_no_op_when_package_is_disabled(): void
    {
        config(['dbflow-filament.enabled' => false]);

        $panel = Panel::make()->id('test');

        $registered = DBFlowFilamentPanel::register($panel);

        $this->assertSame([], $registered->getPages());
        $this->assertSame([], $registered->getResources());
    }
}
