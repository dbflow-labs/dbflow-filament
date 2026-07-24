<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Concerns;

use DbflowLabs\Core\Actions\CreateWorkflowDraft;
use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Core\Validation\WorkflowDefinitionValidator;
use DbflowLabs\Filament\Support\MinimalWorkflowDefinitionFactory;

trait BuildsWorkflowDefinitionFixtures
{
    protected function createWorkflowDraft(
        string $key = 'package_definition_test',
        string $name = 'Package Definition Test',
        ?string $description = null,
    ): Workflow {
        $definition = MinimalWorkflowDefinitionFactory::withDefaultApprovalStep(
            MinimalWorkflowDefinitionFactory::forMetadata($key, $name, $description),
        );

        return app(CreateWorkflowDraft::class)->handle($definition);
    }

    protected function createValidPublishableDraft(
        string $key = 'publishable_definition',
        string $name = 'Publishable Definition',
    ): Workflow {
        $validator = new WorkflowDefinitionValidator;
        $definition = MinimalWorkflowDefinitionFactory::withDefaultApprovalStep(
            MinimalWorkflowDefinitionFactory::forMetadata($key, $name),
            assigneeType: 'user',
            assigneeValue: '1',
        );

        return (new CreateWorkflowDraft($validator))->handle($definition);
    }
}
