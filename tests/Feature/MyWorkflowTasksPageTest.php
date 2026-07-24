<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Filament\Pages\MyWorkflowTasks;
use DbflowLabs\Filament\Support\DBFlowFilamentPanel;
use DbflowLabs\Filament\Support\Queries\MyWorkflowTasksQuery;
use DbflowLabs\Filament\Tests\TestCase;
use Filament\Panel;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

final class MyWorkflowTasksPageTest extends TestCase
{
    #[Test]
    public function page_class_exists_under_package_namespace(): void
    {
        $this->assertTrue(class_exists(MyWorkflowTasks::class));

        $reflection = new ReflectionClass(MyWorkflowTasks::class);

        $this->assertSame('DbflowLabs\Filament\Pages', $reflection->getNamespaceName());
        $this->assertStringNotContainsString('App\\DBFlow', (string) file_get_contents($reflection->getFileName()));
    }

    #[Test]
    public function page_uses_package_translations_for_title_and_navigation(): void
    {
        $this->assertSame(
            'My Workflow Tasks',
            (string) __('dbflow-filament::dbflow-filament.pages.my_tasks.title'),
        );

        $this->assertSame('My Workflow Tasks', MyWorkflowTasks::getNavigationLabel());
    }

    #[Test]
    public function navigation_sort_respects_config(): void
    {
        config(['dbflow-filament.navigation_sort.my_tasks' => 42]);

        $this->assertSame(42, MyWorkflowTasks::getNavigationSort());
    }

    #[Test]
    public function registrar_includes_page_class_when_enabled(): void
    {
        config(['dbflow-filament.enable_my_tasks_page' => true]);

        $this->assertContains(MyWorkflowTasks::class, DBFlowFilamentPanel::pageClasses());
    }

    #[Test]
    public function registrar_excludes_page_class_when_disabled(): void
    {
        config(['dbflow-filament.enable_my_tasks_page' => false]);

        $this->assertNotContains(MyWorkflowTasks::class, DBFlowFilamentPanel::pageClasses());
    }

    #[Test]
    public function register_adds_my_workflow_tasks_page_to_panel(): void
    {
        config(['dbflow-filament.enable_my_tasks_page' => true]);

        $panel = DBFlowFilamentPanel::register(Panel::make()->id('test'));

        $this->assertContains(MyWorkflowTasks::class, $panel->getPages());
    }

    #[Test]
    public function package_view_is_registered(): void
    {
        $this->assertTrue(view()->exists('dbflow-filament::pages.my-workflow-tasks'));
    }

    #[Test]
    public function pending_tasks_query_method_uses_query_service_boundary(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(MyWorkflowTasks::class))->getFileName());

        $this->assertStringContainsString(MyWorkflowTasksQuery::class, $source);
        $this->assertStringContainsString('pendingForUser', $source);

        $querySource = (string) file_get_contents(
            (new ReflectionClass(MyWorkflowTasksQuery::class))->getFileName(),
        );

        $this->assertStringContainsString('WorkflowTaskQueryService', $querySource);
        $this->assertStringContainsString('pendingAssignmentsQueryForUser', $querySource);
    }

    #[Test]
    public function record_actions_include_package_table_actions_when_enabled(): void
    {
        config(['dbflow-filament.enable_my_task_actions' => true]);

        $page = new MyWorkflowTasks;
        $method = (new ReflectionClass(MyWorkflowTasks::class))->getMethod('myWorkflowTaskRecordActions');
        $method->setAccessible(true);

        $actions = $method->invoke($page);

        $this->assertCount(4, $actions);
        $this->assertSame('viewTaskRuntime', $actions[0]->getName());
        $this->assertSame('approveTask', $actions[1]->getName());
        $this->assertSame('rejectTask', $actions[2]->getName());
        $this->assertSame('reassignTask', $actions[3]->getName());
    }

    #[Test]
    public function record_actions_are_empty_when_disabled_by_config(): void
    {
        config(['dbflow-filament.enable_my_task_actions' => false]);

        $page = new MyWorkflowTasks;
        $method = (new ReflectionClass(MyWorkflowTasks::class))->getMethod('myWorkflowTaskRecordActions');
        $method->setAccessible(true);

        $this->assertSame([], $method->invoke($page));
    }
}
