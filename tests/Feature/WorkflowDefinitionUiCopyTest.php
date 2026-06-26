<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Filament\Resources\WorkflowResource;
use DbflowLabs\Filament\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class WorkflowDefinitionUiCopyTest extends TestCase
{
    #[Test]
    public function english_translations_use_workflow_definition_section_title(): void
    {
        $translations = require dirname(__DIR__, 2).'/lang/en/dbflow-filament.php';

        $this->assertSame(
            'Workflow Definition',
            $translations['forms']['workflow_definitions']['sections']['definition'],
        );

        $this->assertStringContainsString(
            'Add approval steps in order',
            $translations['forms']['workflow_definitions']['definition_form_helper'],
        );
        $this->assertNotEmpty($translations['forms']['workflow_definitions']['blocks']['condition']);
        $this->assertNotEmpty($translations['forms']['workflow_definitions']['blocks']['action']);
    }

    #[Test]
    public function english_translations_do_not_expose_legacy_ui_copy(): void
    {
        $translations = require dirname(__DIR__, 2).'/lang/en/dbflow-filament.php';
        $encoded = json_encode($translations['forms']['workflow_definitions'], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('Definition JSON', $encoded);
        $this->assertStringNotContainsString('dbflowlabs/filament-pro', $encoded);
        $this->assertStringNotContainsString('Full visual editing belongs', $encoded);
        $this->assertStringNotContainsString('Advanced: edit the stored draft definition as JSON', $encoded);
        $this->assertStringNotContainsString('Symfony', $encoded);
        $this->assertStringNotContainsString('ExpressionLanguage', $encoded);
        $this->assertStringNotContainsString('Core', $encoded);
        $this->assertStringNotContainsString('handler', $encoded);
        $this->assertStringNotContainsString('topology', $encoded);
        $this->assertStringNotContainsString('Pro Canvas', $encoded);
        $this->assertStringNotContainsString('HasWorkflow', $encoded);
        $this->assertStringNotContainsString('runtime', $encoded);
    }

    #[Test]
    public function definition_editor_section_is_not_collapsed_or_collapsible(): void
    {
        $workflowResource = (string) file_get_contents(
            dirname(__DIR__, 2).'/src/Resources/WorkflowResource.php',
        );

        $this->assertStringContainsString('definitionEditorSection', $workflowResource);
        $this->assertStringNotContainsString('->collapsed()', $workflowResource);
        $this->assertStringNotContainsString('->collapsible()', $workflowResource);
    }

    #[Test]
    public function workflow_resource_references_workflow_definition_translation_keys(): void
    {
        $contents = (string) file_get_contents(
            dirname(__DIR__, 2).'/src/Resources/WorkflowResource.php',
        );

        $this->assertStringContainsString('forms.workflow_definitions.sections.definition', $contents);
        $this->assertStringContainsString('forms.workflow_definitions.definition_form_helper', $contents);
        $this->assertStringNotContainsString('definition_json_helper', $contents);
    }
}
