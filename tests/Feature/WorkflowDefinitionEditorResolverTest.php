<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Filament\Contracts\WorkflowDefinitionEditorResolver;
use DbflowLabs\Filament\Resources\WorkflowResource;
use DbflowLabs\Filament\Support\WorkflowDefinitionEditorResolverManager;
use DbflowLabs\Filament\Tests\Support\EmptyWorkflowDefinitionEditorResolver;
use DbflowLabs\Filament\Tests\Support\MarkerWorkflowDefinitionEditorResolver;
use DbflowLabs\Filament\Tests\Support\TestWorkflowDefinitionEditorResolver;
use DbflowLabs\Filament\Tests\TestCase;
use DbflowLabs\Filament\Support\Editors\StandardWorkflowDefinitionEditor;
use Filament\Forms\Components\Builder;
use Filament\Schemas\Components\Component;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;

final class WorkflowDefinitionEditorResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        app(WorkflowDefinitionEditorResolverManager::class)->forgetRuntimeResolver();
        TestWorkflowDefinitionEditorResolver::reset();
        config(['dbflow-filament.workflow_definition_editor_resolver' => null]);

        parent::tearDown();
    }

    #[Test]
    public function default_editor_uses_standard_form_when_no_resolver_is_configured(): void
    {
        $workflow = $this->workflowWithDraft();

        $components = WorkflowResource::definitionEditorFields($workflow, 'edit');

        $this->assertCount(5, $components);
        $this->assertContains(Builder::class, array_map(static fn (Component $component): string => $component::class, $components));
    }

    #[Test]
    public function empty_resolver_result_falls_back_to_standard_form_editor(): void
    {
        $workflow = $this->workflowWithDraft();

        app(WorkflowDefinitionEditorResolverManager::class)->registerRuntimeResolver(
            new EmptyWorkflowDefinitionEditorResolver(),
        );

        $components = WorkflowResource::definitionEditorFields($workflow, 'edit');

        $this->assertCount(5, $components);
        $this->assertContains(Builder::class, array_map(static fn (Component $component): string => $component::class, $components));
    }

    #[Test]
    public function workflow_resource_default_path_does_not_use_legacy_json_textarea(): void
    {
        $contents = (string) file_get_contents(
            dirname(__DIR__, 2).'/src/Resources/WorkflowResource.php',
        );

        $this->assertStringContainsString('standardDefinitionEditorComponents', $contents);
        $this->assertStringContainsString('StandardWorkflowDefinitionEditor::formComponents', $contents);
    }
    #[Test]
    public function runtime_resolver_components_replace_standard_form_editor(): void
    {
        $workflow = $this->workflowWithDraft();

        app(WorkflowDefinitionEditorResolverManager::class)->registerRuntimeResolver(
            new MarkerWorkflowDefinitionEditorResolver(),
        );

        $components = WorkflowResource::definitionEditorFields($workflow, 'edit');

        $this->assertCount(1, $components);
        $this->assertSame('custom_definition_editor_marker', $components[0]->getName());
        $this->assertNotContains('definition_json', $this->componentNames($components));
    }


    #[Test]
    public function class_string_resolver_from_config_is_used_when_runtime_resolver_is_not_registered(): void
    {
        $workflow = $this->workflowWithDraft();

        config([
            'dbflow-filament.workflow_definition_editor_resolver' => MarkerWorkflowDefinitionEditorResolver::class,
        ]);

        $components = WorkflowResource::definitionEditorFields($workflow, 'edit');

        $this->assertSame('custom_definition_editor_marker', $components[0]->getName());
    }

    #[Test]
    public function resolver_receives_stable_english_keyed_context(): void
    {
        $workflow = $this->workflowWithDraft();

        $resolver = new class implements WorkflowDefinitionEditorResolver
        {
            public function resolve(array $context): array
            {
                TestWorkflowDefinitionEditorResolver::$lastContext = $context;

                return [];
            }
        };

        app(WorkflowDefinitionEditorResolverManager::class)->registerRuntimeResolver($resolver);

        WorkflowResource::definitionEditorFields($workflow, 'edit');

        $this->assertSame([
            'record' => $workflow,
            'operation' => 'edit',
            'state_path' => 'definition_json',
            'resource' => WorkflowResource::class,
        ], TestWorkflowDefinitionEditorResolver::$lastContext);
    }

    #[Test]
    public function definition_editor_context_builder_is_stable(): void
    {
        $workflow = $this->workflowWithDraft();

        $this->assertSame([
            'record' => $workflow,
            'operation' => 'edit',
            'state_path' => 'definition_json',
            'resource' => WorkflowResource::class,
        ], WorkflowResource::definitionEditorContext($workflow, 'edit'));
    }

    #[Test]
    public function invalid_resolver_output_throws_a_clear_english_exception(): void
    {
        $workflow = $this->workflowWithDraft();

        app(WorkflowDefinitionEditorResolverManager::class)->registerRuntimeResolver(
            new class implements WorkflowDefinitionEditorResolver
            {
                public function resolve(array $context): array
                {
                    return ['invalid'];
                }
            },
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Workflow definition editor resolver must return Filament schema components');

        WorkflowResource::definitionEditorFields($workflow, 'edit');
    }

    #[Test]
    public function edit_workflow_page_uses_workflow_resource_definition_editor_section(): void
    {
        $contents = (string) file_get_contents(
            dirname(__DIR__, 2).'/src/Resources/WorkflowResource/Pages/EditWorkflow.php',
        );

        $this->assertStringContainsString('WorkflowResource::definitionEditorSection', $contents);
        $this->assertStringNotContainsString("Textarea::make('definition_json')", $contents);
    }

    /**
     * @param  list<Component>  $components
     * @return list<string|null>
     */
    private function componentNames(array $components, int $depth = 0): array
    {
        if ($depth > 4) {
            return [];
        }

        $names = [];

        foreach ($components as $component) {
            if (method_exists($component, 'getName') && $component->getName() !== null) {
                $names[] = $component->getName();
            }

            if ($component instanceof \Filament\Schemas\Components\Section) {
                $names = [...$names, ...$this->componentNames($component->getChildComponents(), $depth + 1)];
            }
        }

        return $names;
    }

    private function workflowWithDraft(): Workflow
    {
        $definition = \DbflowLabs\Filament\Support\MinimalWorkflowDefinitionFactory::forMetadata(
            'resolver_test',
            'Resolver Test',
        );

        $workflow = new Workflow();
        $workflow->forceFill([
            'key' => 'resolver_test',
            'name' => 'Resolver Test',
            'draft_definition' => $definition,
        ]);

        return $workflow;
    }
}
