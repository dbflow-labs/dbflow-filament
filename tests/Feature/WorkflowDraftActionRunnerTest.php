<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Core\Actions\PublishWorkflowDraft;
use DbflowLabs\Core\Models\WorkflowVersion;
use DbflowLabs\Core\Validation\WorkflowDefinitionValidator;
use DbflowLabs\Filament\Support\Actions\WorkflowDraftActionRunner;
use DbflowLabs\Filament\Tests\Concerns\BuildsWorkflowDefinitionFixtures;
use DbflowLabs\Filament\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

final class WorkflowDraftActionRunnerTest extends TestCase
{
    use BuildsWorkflowDefinitionFixtures;
    use RefreshDatabase;

    private WorkflowDraftActionRunner $runner;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../vendor/dbflowlabs/core/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->runner = app(WorkflowDraftActionRunner::class);
    }

    #[Test]
    public function validate_draft_reports_missing_draft(): void
    {
        $workflow = $this->createWorkflowDraft();
        $workflow->draft_definition = [];
        $workflow->save();

        $result = $this->runner->validateDraft($workflow->refresh());

        $this->assertFalse($result->success);
        $this->assertSame(
            'dbflow-filament::dbflow-filament.notifications.definitions.no_draft_available',
            $result->titleKey,
        );
    }

    #[Test]
    public function validate_draft_uses_core_validator(): void
    {
        $workflow = $this->createWorkflowDraft();

        $result = $this->runner->validateDraft($workflow);

        $this->assertContains($result->level, ['success', 'warning']);
        $this->assertNotNull($workflow->refresh()->draft_updated_at);
    }

    #[Test]
    public function publish_draft_calls_core_publish_action_for_valid_draft(): void
    {
        $workflow = $this->createValidPublishableDraft('publish_runner_test', 'Publish Runner Test');

        $result = $this->runner->publishDraft($workflow, 1);

        $this->assertTrue($result->success);
        $this->assertInstanceOf(WorkflowVersion::class, $result->publishedVersion);
        $this->assertSame(1, $result->publishedVersion?->version);
    }

    #[Test]
    public function publish_draft_reports_failure_for_invalid_draft(): void
    {
        $workflow = $this->createWorkflowDraft('invalid_publish_test', 'Invalid Publish Test');
        $definition = $workflow->draftDefinition();
        $definition['nodes'][1]['config']['assignees']['value'] = '';
        $workflow->forceFill([
            'draft_definition' => $definition,
            'draft_validation_errors' => app(WorkflowDefinitionValidator::class)->validate($definition)->errors(),
        ])->save();

        $result = $this->runner->publishDraft($workflow->refresh());

        $this->assertFalse($result->success);
        $this->assertSame('danger', $result->level);
        $this->assertNull(WorkflowVersion::query()->where('workflow_id', $workflow->getKey())->first());
    }

    #[Test]
    public function runner_is_bound_to_real_core_publish_action(): void
    {
        $this->assertInstanceOf(
            PublishWorkflowDraft::class,
            $this->app->make(PublishWorkflowDraft::class),
        );
        $this->assertInstanceOf(
            WorkflowDefinitionValidator::class,
            $this->app->make(WorkflowDefinitionValidator::class),
        );
    }
}
