<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Filament\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class DBFlowFilamentTranslationsTest extends TestCase
{
    #[Test]
    public function translation_file_exists(): void
    {
        $this->assertFileExists(__DIR__.'/../../lang/en/dbflow-filament.php');
    }

    #[Test]
    public function baseline_translation_keys_resolve(): void
    {
        $this->assertSame('Workflows', trans('dbflow-filament::dbflow-filament.navigation.group'));
        $this->assertSame('My Workflow Tasks', trans('dbflow-filament::dbflow-filament.pages.my_tasks.title'));
        $this->assertSame('Approve', trans('dbflow-filament::dbflow-filament.actions.approve'));
        $this->assertSame('Pending Approval', trans('dbflow-filament::dbflow-filament.statuses.pending'));
        $this->assertSame('Workflow Definitions', trans('dbflow-filament::dbflow-filament.resources.workflow_definitions.plural_model_label'));
        $this->assertSame('Check Configuration', trans('dbflow-filament::dbflow-filament.actions.definitions.validate_draft'));
    }
}
