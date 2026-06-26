<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Filament\Contracts\PermissionChecker;
use DbflowLabs\Filament\Resources\WorkflowResource;
use DbflowLabs\Filament\Support\DBFlowFilamentPanel;
use DbflowLabs\Filament\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class WorkflowResourceTest extends TestCase
{
    #[Test]
    public function workflow_resource_uses_core_workflow_model(): void
    {
        $this->assertSame(Workflow::class, WorkflowResource::getModel());
    }

    #[Test]
    public function workflow_resource_source_does_not_reference_host_terms(): void
    {
        $contents = (string) file_get_contents(
            dirname(__DIR__, 2).'/src/Resources/WorkflowResource.php',
        );

        foreach (['DBErp', 'PurchaseRequest', 'App\\Filament', 'App\\DBFlow'] as $term) {
            $this->assertStringNotContainsString($term, $contents);
        }
    }

    #[Test]
    public function table_and_form_use_package_translation_keys(): void
    {
        $resourceContents = (string) file_get_contents(
            dirname(__DIR__, 2).'/src/Resources/WorkflowResource.php',
        );

        $this->assertStringContainsString('dbflow-filament::dbflow-filament.tables.workflow_definitions.key', $resourceContents);
        $this->assertStringContainsString('dbflow-filament::dbflow-filament.forms.workflow_definitions.name', $resourceContents);
    }

    #[Test]
    public function panel_includes_workflow_resource_when_enabled(): void
    {
        config(['dbflow-filament.enable_workflow_definition_resource' => true]);

        $this->assertContains(WorkflowResource::class, DBFlowFilamentPanel::resourceClasses());
    }

    #[Test]
    public function panel_excludes_workflow_resource_when_disabled(): void
    {
        config(['dbflow-filament.enable_workflow_definition_resource' => false]);

        $this->assertNotContains(WorkflowResource::class, DBFlowFilamentPanel::resourceClasses());
    }

    #[Test]
    public function permission_checker_controls_view_access(): void
    {
        $checker = new class implements PermissionChecker
        {
            public function can(mixed $user, string $ability, mixed $record = null): bool
            {
                return $ability === 'dbflow.definitions.view';
            }
        };

        $this->app->instance(PermissionChecker::class, $checker);

        $this->assertTrue(WorkflowResource::canViewAny());
        $this->assertFalse(WorkflowResource::canCreate());
    }

    #[Test]
    public function code_binding_mode_is_default(): void
    {
        $this->assertTrue(WorkflowResource::isCodeBindingMode());
        $this->assertFalse(WorkflowResource::isUiBindingMode());
    }

    #[Test]
    public function workflowable_options_are_hydrated_from_config(): void
    {
        config([
            'dbflow.workflowables' => [
                'App\\Models\\PurchaseRequest' => 'Purchase Request',
                'App\\Models\\LeaveRequest' => 'Leave Request',
            ],
        ]);

        $this->assertSame([
            'App\\Models\\PurchaseRequest' => 'Purchase Request',
            'App\\Models\\LeaveRequest' => 'Leave Request',
        ], WorkflowResource::workflowableOptions());
    }

    #[Test]
    public function auto_key_is_generated_from_model_type_class_basename(): void
    {
        $this->assertSame(
            'auto_purchase_request',
            WorkflowResource::generateAutoKeyFromModelType('App\\Models\\PurchaseRequest'),
        );
    }
}
