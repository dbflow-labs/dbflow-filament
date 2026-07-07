<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Core\Validation\WorkflowDefinitionValidator;
use DbflowLabs\Filament\Support\Editors\LinearApprovalDefinitionMapper;
use DbflowLabs\Filament\Support\Editors\StandardLinearApprovalDefinitionEditor;
use DbflowLabs\Filament\Support\Editors\StandardWorkflowDefinitionEditor;
use DbflowLabs\Filament\Support\MinimalWorkflowDefinitionFactory;
use DbflowLabs\Filament\Tests\Concerns\BuildsWorkflowDefinitionFixtures;
use DbflowLabs\Filament\Tests\TestCase;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

final class StandardLinearApprovalDefinitionEditorTest extends TestCase
{
    use BuildsWorkflowDefinitionFixtures;
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../vendor/dbflowlabs/core/database/migrations');
    }

    #[Test]
    public function default_editor_uses_workflow_steps_builder_not_raw_json_textarea(): void
    {
        $workflow = $this->createWorkflowDraft();

        $components = StandardWorkflowDefinitionEditor::formComponents($workflow);

        $this->assertCount(5, $components);
        $this->assertInstanceOf(Textarea::class, $components[0]);
        $this->assertInstanceOf(Placeholder::class, $components[1]);
        $this->assertInstanceOf(Builder::class, $components[2]);
        $this->assertInstanceOf(Repeater::class, $components[3]);
        $this->assertInstanceOf(Textarea::class, $components[4]);

        $contents = (string) file_get_contents(
            dirname(__DIR__, 2).'/src/Support/Editors/StandardWorkflowDefinitionEditor.php',
        );

        $this->assertStringContainsString("Builder::make('workflow_steps')", $contents);
        $this->assertStringContainsString("Repeater::make('end_outcomes')", $contents);
        $this->assertStringContainsString('cannot_delete_last_end_outcome', $contents);
        $this->assertStringContainsString('guardRepeaterMinItemsDelete', $contents);
        $builderPos = strpos($contents, "Builder::make('workflow_steps')");
        $endPos = strpos($contents, "Repeater::make('end_outcomes')");
        $this->assertNotFalse($builderPos);
        $this->assertNotFalse($endPos);
        $this->assertLessThan($endPos, $builderPos);
        $this->assertStringContainsString('StandardWorkflowStepTypes::CONDITION', $contents);
        $this->assertStringContainsString('StandardWorkflowStepTypes::ACTION', $contents);
        $this->assertStringContainsString('definitionJsonFallbackField', $contents);
        $this->assertStringNotContainsString('forms.workflow_definitions.advanced', $contents);
        $this->assertStringNotContainsString('advancedStepKeySection', $contents);
    }

    #[Test]
    public function legacy_editor_alias_delegates_to_standard_workflow_definition_editor(): void
    {
        $workflow = $this->createWorkflowDraft();

        $standard = StandardWorkflowDefinitionEditor::formComponents($workflow);
        $legacy = StandardLinearApprovalDefinitionEditor::formComponents($workflow);

        $this->assertCount(count($standard), $legacy);
        $this->assertSame(
            array_map(static fn (object $component): string => $component::class, $standard),
            array_map(static fn (object $component): string => $component::class, $legacy),
        );
    }

    #[Test]
    public function mapper_builds_valid_linear_definition_from_form_steps(): void
    {
        $workflow = $this->createWorkflowDraft('mapper_test', 'Mapper Test');

        $definition = LinearApprovalDefinitionMapper::buildDefinition($workflow, [
            [
                'step_key' => 'manager_review',
                'step_label' => 'Manager Review',
                'assignee_type' => 'user',
                'assignee_value' => '42',
            ],
        ]);

        $result = (new WorkflowDefinitionValidator)->validate($definition, strict: false);

        $this->assertTrue($result->isValid());
        $this->assertSame('manager_review', $definition['nodes'][1]['key'] ?? null);
        $this->assertSame('42', $definition['nodes'][1]['config']['assignees']['value'] ?? null);
    }

    #[Test]
    public function mapper_round_trips_minimal_factory_definition(): void
    {
        $definition = MinimalWorkflowDefinitionFactory::withDefaultApprovalStep(
            MinimalWorkflowDefinitionFactory::forMetadata('round_trip', 'Round Trip'),
        );
        $definition['nodes'][1]['config']['assignees']['value'] = '7';

        $steps = LinearApprovalDefinitionMapper::parseApprovalSteps($definition);

        $this->assertNotNull($steps);
        $this->assertCount(1, $steps);
        $this->assertSame('approval', $steps[0]['step_key']);
        $this->assertSame('7', $steps[0]['assignee_value']);

        $workflow = new Workflow();
        $workflow->forceFill([
            'key' => 'round_trip',
            'name' => 'Round Trip',
        ]);

        $rebuilt = LinearApprovalDefinitionMapper::buildDefinition($workflow, $steps);
        $reparsed = LinearApprovalDefinitionMapper::parseApprovalSteps($rebuilt);

        $this->assertSame($steps, $reparsed);
    }

    #[Test]
    public function condition_branch_selects_read_workflow_steps_from_form_root(): void
    {
        $contents = (string) file_get_contents(
            dirname(__DIR__, 2).'/src/Support/Editors/StandardWorkflowDefinitionEditor.php',
        );

        $this->assertStringContainsString("workflowStepKeyOptions(\$get('/workflow_steps'))", $contents);
        $this->assertStringContainsString("\$get('/end_outcomes')", $contents);
        $this->assertStringContainsString("TextInput::make('step_key')", $contents);
        $this->assertStringContainsString('getOptionLabelUsing', $contents);
        $this->assertStringNotContainsString("workflowStepKeyOptions(\$get('workflow_steps'))", $contents);
    }

    #[Test]
    public function default_editor_does_not_expose_definition_schema_preview(): void
    {
        $contents = (string) file_get_contents(
            dirname(__DIR__, 2).'/src/Support/Editors/StandardWorkflowDefinitionEditor.php',
        );

        $this->assertStringNotContainsString("Textarea::make('definition_schema_preview')", $contents);
        $this->assertStringNotContainsString('sections.schema_preview', $contents);
    }

    #[Test]
    public function approval_steps_repeater_supports_multiple_sequential_steps(): void
    {
        $workflow = $this->createWorkflowDraft('multi_step', 'Multi Step');

        $definition = LinearApprovalDefinitionMapper::buildDefinition($workflow, [
            [
                'step_key' => 'first_review',
                'step_label' => 'First Review',
                'assignee_type' => 'permission',
                'assignee_value' => 'dbflow.tasks.approve',
            ],
            [
                'step_key' => 'second_review',
                'step_label' => 'Second Review',
                'assignee_type' => 'user',
                'assignee_value' => '99',
            ],
        ]);

        $steps = LinearApprovalDefinitionMapper::parseApprovalSteps($definition);

        $this->assertCount(2, $steps);
        $this->assertSame(['start', 'first_review', 'second_review', 'end'], array_map(
            static fn (array $node): string => (string) ($node['key'] ?? ''),
            $definition['nodes'],
        ));
    }

    #[Test]
    public function mapper_auto_generates_step_keys_from_labels_when_missing(): void
    {
        $workflow = $this->createWorkflowDraft('auto_key', 'Auto Key');

        $definition = LinearApprovalDefinitionMapper::buildDefinition($workflow, [
            [
                'step_label' => 'Manager Review',
                'assignee_type' => 'user',
                'assignee_value' => '1',
            ],
            [
                'step_label' => 'Manager Review',
                'assignee_type' => 'user',
                'assignee_value' => '2',
            ],
        ]);

        $this->assertSame('manager_review', $definition['nodes'][1]['key'] ?? null);
        $this->assertSame('manager_review_2', $definition['nodes'][2]['key'] ?? null);
    }

    #[Test]
    public function mapper_round_trips_approval_mode(): void
    {
        $workflow = $this->createWorkflowDraft('approval_mode', 'Approval Mode');

        $definition = LinearApprovalDefinitionMapper::buildDefinition($workflow, [
            [
                'step_key' => 'finance_review',
                'step_label' => 'Finance Review',
                'assignee_type' => 'permission',
                'assignee_value' => 'finance.approve',
                'approval_mode' => 'all',
            ],
        ]);

        $steps = LinearApprovalDefinitionMapper::parseApprovalSteps($definition);

        $this->assertNotNull($steps);
        $this->assertSame('all', $steps[0]['approval_mode']);
        $this->assertSame('all', $definition['nodes'][1]['config']['approval_mode'] ?? null);
    }
}
