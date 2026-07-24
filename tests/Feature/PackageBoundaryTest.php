<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Filament\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class PackageBoundaryTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const FORBIDDEN_HOST_TERMS = [
        'DBErp',
        'PurchaseRequest',
        'DberpPermissionService',
        'PurchaseRequestStatus',
    ];

    #[Test]
    public function package_source_does_not_contain_forbidden_host_terms(): void
    {
        $violations = $this->collectForbiddenMatches(self::FORBIDDEN_HOST_TERMS, [
            dirname(__DIR__, 2).'/src',
            dirname(__DIR__, 2).'/lang',
            dirname(__DIR__, 2).'/resources',
            dirname(__DIR__, 2).'/config',
        ]);

        $this->assertSame([], $violations);
    }

    #[Test]
    public function package_source_does_not_reference_stale_app_dbflow_namespace(): void
    {
        $violations = $this->collectForbiddenMatches([
            'namespace App\\DBFlow',
            'use App\\DBFlow',
        ], [
            dirname(__DIR__, 2).'/src',
            dirname(__DIR__, 2).'/tests',
        ]);

        $this->assertSame([], $violations);
    }

    #[Test]
    public function package_source_does_not_contain_chinese_characters(): void
    {
        $violations = [];

        foreach ($this->packagePhpFiles() as $file) {
            $normalizedPath = str_replace('\\', '/', $file);

            if (preg_match('#/lang/(?!en/)#', $normalizedPath) === 1) {
                continue;
            }

            $contents = (string) file_get_contents($file);

            if (preg_match('/[\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{F900}-\x{FAFF}]/u', $contents) === 1) {
                $violations[] = $file;
            }
        }

        $this->assertSame([], $violations);
    }

    /**
     * @param  list<string>  $terms
     * @param  list<string>  $directories
     * @return list<string>
     */
    private function collectForbiddenMatches(array $terms, array $directories): array
    {
        $violations = [];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach ($this->phpFilesIn($directory) as $file) {
                $contents = (string) file_get_contents($file);

                foreach ($terms as $term) {
                    if (str_contains($contents, $term)) {
                        $violations[] = "{$file} contains [{$term}]";
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function packagePhpFiles(): array
    {
        return array_merge(
            $this->phpFilesIn(dirname(__DIR__, 2).'/src'),
            $this->phpFilesIn(dirname(__DIR__, 2).'/tests'),
            $this->phpFilesIn(dirname(__DIR__, 2).'/lang'),
            $this->phpFilesIn(dirname(__DIR__, 2).'/config'),
        );
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                $files[] = $fileInfo->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
