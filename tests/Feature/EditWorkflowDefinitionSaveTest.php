<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Filament\Resources\WorkflowResource\Pages\EditWorkflow;
use DbflowLabs\Filament\Support\Editors\LinearApprovalDefinitionMapper;
use DbflowLabs\Filament\Support\Editors\StandardWorkflowDefinitionMapper;
use DbflowLabs\Filament\Support\Editors\StandardWorkflowStepTypes;
use DbflowLabs\Filament\Tests\Concerns\BuildsWorkflowDefinitionFixtures;
use DbflowLabs\Filament\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

final class EditWorkflowDefinitionSaveTest extends TestCase
{
    use BuildsWorkflowDefinitionFixtures;
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../vendor/dbflowlabs/core/database/migrations');
    }

    #[Test]
    public function edit_workflow_save_path_builds_definition_from_approval_steps(): void
    {
        $workflow = $this->createWorkflowDraft('save_path_test', 'Save Path Test');

        $page = app(EditWorkflow::class);
        $method = new \ReflectionMethod(EditWorkflow::class, 'resolveDefinitionForSave');
        $method->setAccessible(true);

        /** @var array<string, mixed> $definition */
        $definition = $method->invoke($page, $workflow, [
            'name' => 'Save Path Test',
            'description' => 'Updated through form steps',
            'approval_steps' => [
                [
                    'step_key' => 'approval',
                    'step_label' => 'Approval',
                    'assignee_type' => 'user',
                    'assignee_value' => '15',
                ],
            ],
        ]);

        $this->assertSame('15', $definition['nodes'][1]['config']['assignees']['value'] ?? null);
        $this->assertTrue(LinearApprovalDefinitionMapper::isLinearApprovalDefinition($definition));
    }

    #[Test]
    public function edit_workflow_save_path_builds_definition_from_workflow_steps_builder(): void
    {
        $workflow = $this->createWorkflowDraft('builder_save_test', 'Builder Save Test');

        $page = app(EditWorkflow::class);
        $method = new \ReflectionMethod(EditWorkflow::class, 'resolveDefinitionForSave');
        $method->setAccessible(true);

        /** @var array<string, mixed> $definition */
        $definition = $method->invoke($page, $workflow, [
            'name' => 'Builder Save Test',
            'workflow_steps' => [
                [
                    'type' => StandardWorkflowStepTypes::CONDITION,
                    'data' => [
                        'step_label' => 'Amount Check',
                        'expression' => 'model.total_amount > 100',
                        'true_branch' => 'next',
                        'false_branch' => 'end_outcome',
                        'false_branch_end_key' => 'end_rejected',
                    ],
                ],
                [
                    'type' => StandardWorkflowStepTypes::ACTION,
                    'data' => [
                        'step_label' => 'Notify Team',
                        'action_key' => 'notify_team',
                        'payload' => ['channel' => 'slack'],
                    ],
                ],
            ],
        ]);

        $this->assertTrue(StandardWorkflowDefinitionMapper::isStandardFormDefinition($definition));
        $this->assertSame('condition', $definition['nodes'][1]['type'] ?? null);
        $this->assertSame('notify_team', $definition['nodes'][2]['config']['action_key'] ?? null);
    }

    #[Test]
    public function edit_workflow_save_path_rejects_malformed_definition_json(): void
    {
        $workflow = $this->createWorkflowDraft('json_malform_test', 'JSON Malform Test');

        $page = app(EditWorkflow::class);
        $method = new \ReflectionMethod(EditWorkflow::class, 'resolveDefinitionForSave');
        $method->setAccessible(true);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $method->invoke($page, $workflow, [
            'name' => 'JSON Malform Test',
            'definition_json' => '{"nodes": [',
        ]);
    }

    #[Test]
    public function edit_workflow_hydrates_definition_json_from_draft(): void
    {
        $workflow = $this->createWorkflowDraft('json_hydrate_test', 'JSON Hydrate Test');

        $page = app(EditWorkflow::class);
        $page->record = $workflow;

        $method = new \ReflectionMethod(EditWorkflow::class, 'mutateFormDataBeforeFill');
        $method->setAccessible(true);

        /** @var array<string, mixed> $data */
        $data = $method->invoke($page, ['name' => $workflow->name]);

        $this->assertIsString($data['definition_json'] ?? null);
        $this->assertStringContainsString('"key": "json_hydrate_test"', (string) $data['definition_json']);
    }
}
