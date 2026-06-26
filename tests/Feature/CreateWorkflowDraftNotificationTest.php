<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Core\Validation\WorkflowDefinitionValidator;
use DbflowLabs\Filament\Support\Editors\StandardWorkflowDefinitionMapper;
use DbflowLabs\Filament\Support\MinimalWorkflowDefinitionFactory;
use DbflowLabs\Filament\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class CreateWorkflowDraftNotificationTest extends TestCase
{
    #[Test]
    public function minimal_create_skeleton_is_a_valid_start_to_end_draft(): void
    {
        $definition = MinimalWorkflowDefinitionFactory::forMetadata('new_workflow', 'New Workflow');
        $result = app(WorkflowDefinitionValidator::class)->validate($definition);

        $this->assertTrue($result->isValid());
        $this->assertCount(2, $definition['nodes'] ?? []);
        $this->assertSame('start', $definition['nodes'][0]['key'] ?? null);
        $this->assertSame('end', $definition['nodes'][1]['key'] ?? null);
    }

    #[Test]
    public function create_workflow_draft_skeleton_is_compatible_with_standard_form_editor(): void
    {
        $definition = MinimalWorkflowDefinitionFactory::withDefaultApprovalStep(
            MinimalWorkflowDefinitionFactory::forMetadata('new_workflow', 'New Workflow'),
        );

        $this->assertTrue(StandardWorkflowDefinitionMapper::isStandardFormDefinition($definition));
        $this->assertCount(3, $definition['nodes'] ?? []);
        $this->assertSame(
            'user',
            $definition['nodes'][1]['config']['assignees']['type'] ?? null,
        );
        $this->assertSame('', $definition['nodes'][1]['config']['assignees']['value'] ?? null);
    }
}
