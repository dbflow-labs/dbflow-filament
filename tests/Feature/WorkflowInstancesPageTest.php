<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Core\Models\WorkflowInstance;
use DbflowLabs\Filament\Pages\ViewWorkflowInstance;
use DbflowLabs\Filament\Pages\WorkflowInstances;
use DbflowLabs\Filament\Support\DBFlowFilamentPanel;
use DbflowLabs\Filament\Support\Queries\WorkflowInstancesQuery;
use DbflowLabs\Filament\Tests\Concerns\BuildsWorkflowInstanceFixtures;
use DbflowLabs\Filament\Tests\Concerns\BuildsWorkflowTaskFixtures;
use DbflowLabs\Filament\Tests\TestCase;
use Filament\Panel;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

final class WorkflowInstancesPageTest extends TestCase
{
    use BuildsWorkflowInstanceFixtures;
    use BuildsWorkflowTaskFixtures;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../vendor/dbflowlabs/core/database/migrations');
    }

    #[Test]
    public function page_class_exists_under_package_namespace(): void
    {
        $this->assertTrue(class_exists(WorkflowInstances::class));

        $reflection = new ReflectionClass(WorkflowInstances::class);

        $this->assertSame('DbflowLabs\Filament\Pages', $reflection->getNamespaceName());
        $this->assertStringNotContainsString('App\\DBFlow', (string) file_get_contents($reflection->getFileName()));
    }

    #[Test]
    public function page_uses_package_translations_for_title_and_navigation(): void
    {
        $this->assertSame(
            'Workflow Instances',
            (string) __('dbflow-filament::dbflow-filament.pages.instances.title'),
        );

        $this->assertSame('Workflow Instances', WorkflowInstances::getNavigationLabel());
    }

    #[Test]
    public function navigation_sort_respects_config(): void
    {
        config(['dbflow-filament.navigation_sort.workflow_instances' => 55]);

        $this->assertSame(55, WorkflowInstances::getNavigationSort());
    }

    #[Test]
    public function registrar_includes_workflow_instances_pages_when_enabled(): void
    {
        config(['dbflow-filament.enable_workflow_instances_page' => true]);

        $pages = DBFlowFilamentPanel::pageClasses();

        $this->assertContains(WorkflowInstances::class, $pages);
        $this->assertContains(ViewWorkflowInstance::class, $pages);
    }

    #[Test]
    public function registrar_excludes_workflow_instances_pages_when_disabled(): void
    {
        config([
            'dbflow-filament.enable_workflow_instances_page' => false,
            'dbflow-filament.enable_my_tasks_page' => false,
        ]);

        $this->assertNotContains(WorkflowInstances::class, DBFlowFilamentPanel::pageClasses());
        $this->assertNotContains(ViewWorkflowInstance::class, DBFlowFilamentPanel::pageClasses());
    }

    #[Test]
    public function register_adds_workflow_instances_pages_to_panel(): void
    {
        config(['dbflow-filament.enable_workflow_instances_page' => true]);

        $panel = DBFlowFilamentPanel::register(Panel::make()->id('test'));

        $this->assertContains(WorkflowInstances::class, $panel->getPages());
        $this->assertContains(ViewWorkflowInstance::class, $panel->getPages());
    }

    #[Test]
    public function package_list_view_is_registered(): void
    {
        $this->assertTrue(view()->exists('dbflow-filament::pages.workflow-instances'));
    }

    #[Test]
    public function query_uses_core_workflow_instance_model(): void
    {
        $instance = $this->createWorkflowInstance();

        $results = app(WorkflowInstancesQuery::class)->baseQuery()->get();

        $this->assertCount(1, $results);
        $this->assertInstanceOf(WorkflowInstance::class, $results->first());
        $this->assertSame($instance->getKey(), $results->first()?->getKey());
    }

    #[Test]
    public function page_table_source_uses_workflow_instances_query(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(WorkflowInstances::class))->getFileName());

        $this->assertStringContainsString(WorkflowInstancesQuery::class, $source);
        $this->assertStringContainsString('StatusBadgeMapper', $source);
    }

    #[Test]
    public function navigation_active_route_pattern_includes_detail_page(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(WorkflowInstances::class))->getFileName());

        $this->assertStringContainsString('getNavigationItemActiveRoutePattern', $source);
        $this->assertStringContainsString('ViewWorkflowInstance::getRouteName()', $source);
    }
}
