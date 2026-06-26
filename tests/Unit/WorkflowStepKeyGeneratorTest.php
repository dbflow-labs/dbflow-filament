<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Unit;

use DbflowLabs\Filament\Support\Editors\WorkflowStepKeyGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowStepKeyGeneratorTest extends TestCase
{
    #[Test]
    public function it_slugs_labels_into_node_keys(): void
    {
        $this->assertSame(
            'manager_review',
            WorkflowStepKeyGenerator::fromLabel('Manager Review', 'approval', ['start', 'end']),
        );
    }

    #[Test]
    public function it_deduplicates_colliding_keys(): void
    {
        $this->assertSame(
            'manager_review_2',
            WorkflowStepKeyGenerator::fromLabel('Manager Review', 'approval', ['start', 'end', 'manager_review']),
        );
    }

    #[Test]
    public function it_falls_back_when_label_has_no_slug_characters(): void
    {
        $key = WorkflowStepKeyGenerator::fromLabel('@@@', 'approval', ['start', 'end']);

        $this->assertMatchesRegularExpression('/^approval_\d{4}$/', $key);
    }
}
