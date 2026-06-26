<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Core\Validation\WorkflowDefinitionValidator;
use DbflowLabs\Filament\Support\Editors\StandardWorkflowBranchTargets;
use DbflowLabs\Filament\Support\Editors\StandardWorkflowDefinitionMapper;
use DbflowLabs\Filament\Support\Editors\StandardWorkflowEndStatuses;
use DbflowLabs\Filament\Support\Editors\StandardWorkflowStepTypes;
use DbflowLabs\Filament\Tests\Concerns\BuildsWorkflowDefinitionFixtures;
use DbflowLabs\Filament\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

final class StandardWorkflowDefinitionMapperTest extends TestCase
{
    use BuildsWorkflowDefinitionFixtures;
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../vendor/dbflowlabs/core/database/migrations');
    }

    #[Test]
    public function it_builds_a_valid_linear_approval_definition_from_builder_blocks(): void
    {
        $workflow = $this->createWorkflowDraft('approval_builder', 'Approval Builder');

        $definition = StandardWorkflowDefinitionMapper::buildDefinition($workflow, [
            [
                'type' => StandardWorkflowStepTypes::APPROVAL,
                'data' => [
                    'step_label' => 'Manager Review',
                    'assignee_type' => 'user',
                    'assignee_value' => '42',
                    'approval_mode' => 'any',
                ],
            ],
        ]);

        $result = (new WorkflowDefinitionValidator)->validate($definition, strict: false);

        $this->assertTrue($result->isValid());
        $this->assertSame('manager_review', $definition['nodes'][1]['key'] ?? null);
        $this->assertSame('approval', $definition['nodes'][1]['type'] ?? null);
        $this->assertSame('completed', $definition['nodes'][2]['config']['status'] ?? null);
    }

    #[Test]
    public function it_builds_condition_and_action_nodes_compatible_with_core_schema(): void
    {
        $workflow = $this->createWorkflowDraft('mixed_builder', 'Mixed Builder');

        $definition = StandardWorkflowDefinitionMapper::buildDefinition($workflow, [
            [
                'type' => StandardWorkflowStepTypes::CONDITION,
                'data' => [
                    'step_key' => 'amount_condition',
                    'step_label' => 'Amount Check',
                    'expression' => 'model.total_amount > 5000',
                    'true_branch' => 'next',
                    'false_branch' => StandardWorkflowBranchTargets::END_OUTCOME,
                    'false_branch_end_key' => 'end',
                ],
            ],
            [
                'type' => StandardWorkflowStepTypes::APPROVAL,
                'data' => [
                    'step_key' => 'approval',
                    'step_label' => 'Finance Approval',
                    'assignee_type' => 'permission',
                    'assignee_value' => 'finance.approve',
                    'approval_mode' => 'any',
                ],
            ],
            [
                'type' => StandardWorkflowStepTypes::ACTION,
                'data' => [
                    'step_key' => 'notify_finance',
                    'step_label' => 'Notify Finance',
                    'action_key' => 'send_notification',
                    'payload' => ['channel' => 'email'],
                ],
            ],
        ], endOutcomes: [
            [
                'step_key' => 'end',
                'step_label' => 'Completed',
                'end_status' => StandardWorkflowEndStatuses::COMPLETED,
            ],
        ]);

        $result = (new WorkflowDefinitionValidator)->validate($definition, strict: false);

        $this->assertTrue($result->isValid());
        $this->assertSame('condition', $definition['nodes'][1]['type'] ?? null);
        $this->assertSame('model.total_amount > 5000', $definition['nodes'][1]['config']['expression'] ?? null);
        $this->assertSame('action', $definition['nodes'][3]['type'] ?? null);
        $this->assertSame('send_notification', $definition['nodes'][3]['config']['action_key'] ?? null);
    }

    #[Test]
    public function it_supports_multiple_end_outcomes_like_pro_canvas(): void
    {
        $workflow = $this->createWorkflowDraft('multi_end', 'Multi End');

        $definition = StandardWorkflowDefinitionMapper::buildDefinition(
            $workflow,
            [
                [
                    'type' => StandardWorkflowStepTypes::APPROVAL,
                    'data' => [
                        'step_key' => 'manager_review',
                        'step_label' => 'Manager Review',
                        'assignee_type' => 'role',
                        'assignee_value' => 'manager',
                        'approval_mode' => 'any',
                    ],
                ],
            ],
            endOutcomes: [
                [
                    'step_key' => 'end_completed',
                    'step_label' => 'Completed',
                    'end_status' => StandardWorkflowEndStatuses::COMPLETED,
                ],
                [
                    'step_key' => 'end_rejected',
                    'step_label' => 'Rejected',
                    'end_status' => StandardWorkflowEndStatuses::REJECTED,
                ],
            ],
        );

        $endNodes = array_values(array_filter(
            $definition['nodes'],
            static fn (array $node): bool => ($node['type'] ?? null) === 'end',
        ));

        $this->assertCount(2, $endNodes);
        $this->assertSame('rejected', $endNodes[1]['config']['status'] ?? null);
        $this->assertSame(
            'user',
            $definition['nodes'][1]['config']['assignees']['type'] ?? null,
            'Unsupported role assignee input must not be emitted in mapper output.',
        );

        $parsed = StandardWorkflowDefinitionMapper::parseEndOutcomes($definition);

        $this->assertNotNull($parsed);
        $this->assertCount(2, $parsed);
        $this->assertSame('end_rejected', $parsed[1]['step_key']);
    }

    #[Test]
    public function it_round_trips_the_core_conditional_approval_template_without_data_loss(): void
    {
        $template = app(\DbflowLabs\Core\Templates\WorkflowTemplateRegistry::class)->find('conditional_approval');

        $this->assertNotNull($template);
        $definition = $template['definition'];
        $definition['key'] = 'conditional_approval';
        $definition['name'] = 'Conditional Approval';

        $formState = StandardWorkflowDefinitionMapper::parseFormState($definition);

        $this->assertNotNull($formState);
        $this->assertCount(2, $formState['workflow_steps']);
        $this->assertGreaterThanOrEqual(1, count($formState['end_outcomes']));

        $workflow = new Workflow();
        $workflow->forceFill([
            'key' => 'conditional_approval',
            'name' => 'Conditional Approval',
        ]);

        $rebuilt = StandardWorkflowDefinitionMapper::buildDefinition(
            $workflow,
            $formState['workflow_steps'],
            endOutcomes: $formState['end_outcomes'],
        );
        $reparsed = StandardWorkflowDefinitionMapper::parseFormState($rebuilt);

        $this->assertNotNull($reparsed);
        $this->assertSame($formState['workflow_steps'], $reparsed['workflow_steps']);
        $this->assertSame($formState['end_outcomes'], $reparsed['end_outcomes']);

        $validation = (new WorkflowDefinitionValidator)->validate($rebuilt, strict: false);
        $this->assertTrue($validation->isValid());
    }

    #[Test]
    public function it_hydrates_builder_blocks_from_a_saved_definition_for_edit_form_fill(): void
    {
        $workflow = $this->createWorkflowDraft('hydrate_test', 'Hydrate Test');

        $definition = StandardWorkflowDefinitionMapper::buildDefinition($workflow, [
            [
                'type' => StandardWorkflowStepTypes::ACTION,
                'data' => [
                    'step_label' => 'Archive Record',
                    'action_key' => 'archive_record',
                    'payload' => ['reason' => 'approved'],
                ],
            ],
        ], endOutcomes: [
            [
                'step_label' => 'Archived',
                'end_status' => StandardWorkflowEndStatuses::COMPLETED,
            ],
        ]);

        $formState = StandardWorkflowDefinitionMapper::parseFormState($definition);

        $this->assertNotNull($formState);
        $this->assertSame('archive_record', $formState['workflow_steps'][0]['data']['action_key'] ?? null);
        $this->assertSame('Archived', $formState['end_outcomes'][0]['step_label'] ?? null);
    }

    #[Test]
    public function it_previews_step_and_end_keys_from_labels_when_keys_are_omitted(): void
    {
        $stepOptions = StandardWorkflowDefinitionMapper::previewStepKeyOptions([
            [
                'type' => StandardWorkflowStepTypes::APPROVAL,
                'data' => [
                    'step_label' => 'Manager Review',
                    'assignee_type' => 'user',
                    'assignee_value' => '1',
                ],
            ],
        ]);

        $endOptions = StandardWorkflowDefinitionMapper::previewEndOutcomeKeyOptions([
            [
                'step_label' => 'Approved',
                'end_status' => StandardWorkflowEndStatuses::COMPLETED,
            ],
            [
                'step_label' => 'Rejected',
                'end_status' => StandardWorkflowEndStatuses::REJECTED,
            ],
        ], [
            [
                'type' => StandardWorkflowStepTypes::APPROVAL,
                'data' => [
                    'step_label' => 'Manager Review',
                    'assignee_type' => 'user',
                    'assignee_value' => '1',
                ],
            ],
        ]);

        $this->assertSame(['manager_review' => 'Manager Review'], $stepOptions);
        $this->assertSame([
            'end' => 'Approved',
            'rejected' => 'Rejected',
        ], $endOptions);
    }

    #[Test]
    public function it_dehydrates_action_config_with_action_key_only(): void
    {
        $workflow = $this->createWorkflowDraft('action_key_only', 'Action Key Only');

        $definition = StandardWorkflowDefinitionMapper::buildDefinition($workflow, [
            [
                'type' => StandardWorkflowStepTypes::ACTION,
                'data' => [
                    'step_label' => 'Notify',
                    'action_key' => 'send_notification',
                ],
            ],
        ]);

        $config = $definition['nodes'][1]['config'] ?? [];

        $this->assertSame('send_notification', $config['action_key'] ?? null);
        $this->assertArrayNotHasKey('action', $config);
    }

    #[Test]
    public function it_hydrates_action_key_from_legacy_action_config(): void
    {
        $workflow = $this->createWorkflowDraft('legacy_action', 'Legacy Action');

        $definition = StandardWorkflowDefinitionMapper::buildDefinition($workflow, [
            [
                'type' => StandardWorkflowStepTypes::ACTION,
                'data' => [
                    'step_key' => 'legacy_notify',
                    'step_label' => 'Legacy Notify',
                    'action_key' => 'placeholder',
                ],
            ],
        ]);

        foreach ($definition['nodes'] as $index => $node) {
            if (($node['type'] ?? null) !== StandardWorkflowStepTypes::ACTION) {
                continue;
            }

            $definition['nodes'][$index]['config'] = [
                'action' => 'archive_record',
                'payload' => ['reason' => 'done'],
            ];
        }

        $formState = StandardWorkflowDefinitionMapper::parseFormState($definition);

        $this->assertNotNull($formState);
        $this->assertSame('archive_record', $formState['workflow_steps'][0]['data']['action_key'] ?? null);
    }

    #[Test]
    public function it_assigns_standard_editor_metadata_with_linear_grid_coordinates(): void
    {
        $workflow = $this->createWorkflowDraft('metadata_grid', 'Metadata Grid');

        $definition = StandardWorkflowDefinitionMapper::buildDefinition($workflow, [
            [
                'type' => StandardWorkflowStepTypes::APPROVAL,
                'data' => [
                    'step_key' => 'manager_review',
                    'step_label' => 'Manager Review',
                    'assignee_type' => 'user',
                    'assignee_value' => '1',
                    'approval_mode' => 'any',
                ],
            ],
        ]);

        $metadata = $definition['metadata']['standard_editor'] ?? null;

        $this->assertIsArray($metadata);
        $this->assertSame('linear', $metadata['layout'] ?? null);
        $this->assertSame(
            ['x' => 100, 'y' => 220],
            $metadata['node_positions']['manager_review'] ?? null,
        );
        $this->assertSame(
            ['x' => 100, 'y' => 220],
            $definition['nodes'][1]['metadata']['standard_editor']['grid'] ?? null,
        );
    }
}
