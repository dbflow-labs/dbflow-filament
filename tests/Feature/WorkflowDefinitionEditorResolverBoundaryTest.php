<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Filament\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class WorkflowDefinitionEditorResolverBoundaryTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const FORBIDDEN_COMMERCIAL_TERMS = [
        'filament-pro',
        'FilamentPro',
        'ProCanvas',
        'commercial',
        'billing',
        'license check',
        'telemetry',
    ];

    /**
     * @var list<string>
     */
    private const FORBIDDEN_PERSISTENCE_PATTERNS = [
        'PublishWorkflowDraft',
        'SaveWorkflowDraft::handle',
        'compileToDatabase',
    ];

    #[Test]
    public function resolver_hook_sources_remain_english_only_without_commercial_references(): void
    {
        $violations = [];

        foreach ($this->scannedFiles() as $file) {
            $contents = (string) file_get_contents($file);

            if (preg_match('/[\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{F900}-\x{FAFF}]/u', $contents) === 1) {
                $violations[] = "{$file} contains non-English characters";
            }

            foreach (self::FORBIDDEN_COMMERCIAL_TERMS as $term) {
                if (str_contains($contents, $term)) {
                    $violations[] = "{$file} contains forbidden commercial term [{$term}]";
                }
            }

            if (str_ends_with($file, '.php')) {
                foreach (self::FORBIDDEN_PERSISTENCE_PATTERNS as $pattern) {
                    if (str_contains($contents, $pattern)) {
                        $violations[] = "{$file} contains forbidden persistence pattern [{$pattern}]";
                    }
                }
            }
        }

        $this->assertSame([], $violations);
    }

    /**
     * @return list<string>
     */
    private function scannedFiles(): array
    {
        $root = dirname(__DIR__, 2);

        return [
            $root.'/src/Contracts/WorkflowDefinitionEditorResolver.php',
            $root.'/src/Support/WorkflowDefinitionEditorResolverManager.php',
            $root.'/src/Resources/WorkflowResource.php',
            $root.'/src/Resources/WorkflowResource/Pages/EditWorkflow.php',
            $root.'/src/Providers/DBFlowFilamentServiceProvider.php',
            $root.'/config/dbflow-filament.php',
            $root.'/tests/Feature/WorkflowDefinitionEditorResolverTest.php',
            $root.'/tests/Support/TestWorkflowDefinitionEditorResolver.php',
        ];
    }
}
