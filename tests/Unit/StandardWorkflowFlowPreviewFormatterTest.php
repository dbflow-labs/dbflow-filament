<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Unit;

use DbflowLabs\Filament\Support\Editors\StandardWorkflowEndStatuses;
use DbflowLabs\Filament\Support\Editors\StandardWorkflowFlowPreviewFormatter;
use DbflowLabs\Filament\Support\Editors\StandardWorkflowStepTypes;
use DbflowLabs\Filament\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class StandardWorkflowFlowPreviewFormatterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setLocale('en');
    }

    #[Test]
    public function it_formats_a_single_approval_step_and_end_outcome_in_english(): void
    {
        $preview = StandardWorkflowFlowPreviewFormatter::format([
            [
                'type' => StandardWorkflowStepTypes::APPROVAL,
                'data' => [
                    'step_label' => 'Manager Review',
                ],
            ],
        ], [
            [
                'step_label' => 'Approved',
                'end_status' => StandardWorkflowEndStatuses::COMPLETED,
            ],
        ]);

        $this->assertSame('Start → Manager Review → Approved (Completed)', $preview);
    }

    #[Test]
    public function it_formats_multiple_end_outcomes_with_a_prefix_segment(): void
    {
        $preview = StandardWorkflowFlowPreviewFormatter::format([
            [
                'type' => StandardWorkflowStepTypes::APPROVAL,
                'data' => [
                    'step_label' => 'Manager Review',
                ],
            ],
        ], [
            [
                'step_label' => 'Approved',
                'end_status' => StandardWorkflowEndStatuses::COMPLETED,
            ],
            [
                'step_label' => 'Rejected',
                'end_status' => StandardWorkflowEndStatuses::REJECTED,
            ],
        ]);

        $this->assertSame(
            'Start → Manager Review → End outcomes: Approved (Completed) | Rejected (Rejected)',
            $preview,
        );
    }

    #[Test]
    public function it_uses_localized_step_type_labels_when_step_name_is_empty(): void
    {
        $preview = StandardWorkflowFlowPreviewFormatter::format([
            [
                'type' => StandardWorkflowStepTypes::APPROVAL,
                'data' => [],
            ],
        ], [
            [
                'step_label' => 'End',
                'end_status' => StandardWorkflowEndStatuses::COMPLETED,
            ],
        ]);

        $this->assertSame('Start → Approval Step → End (Completed)', $preview);
    }
}
