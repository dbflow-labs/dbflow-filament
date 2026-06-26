<?php

/**
 * This file is part of the dbflowlabs/filament package.
 *
 * Copyright (c) 2026 Baron Wang <hello@dbflow.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT
 * @link    https://dbflow.dev
 * @see     https://github.com/dbflow-labs/dbflow-filament
 */

declare(strict_types=1);

namespace DbflowLabs\Filament\Support\Editors;

/**
 * Builds the read-only workflow flow preview string for the Standard form editor.
 */
final class StandardWorkflowFlowPreviewFormatter
{
    public static function format(mixed $steps, mixed $endOutcomes): string
    {
        if (! is_array($steps) || $steps === []) {
            return (string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.flow_preview_empty');
        }

        $stepLabels = self::formatStepLabels($steps);

        if ($stepLabels === []) {
            return (string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.flow_preview_empty');
        }

        $chain = [
            (string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.flow_nodes.start'),
            ...$stepLabels,
        ];

        $endLabels = self::formatEndOutcomeLabels($endOutcomes);

        if ($endLabels === []) {
            return implode(' → ', [
                ...$chain,
                (string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.flow_preview_end_pending'),
            ]);
        }

        if (count($endLabels) === 1) {
            return implode(' → ', [...$chain, $endLabels[0]]);
        }

        $endsSegment = (string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.flow_preview_ends_prefix')
            .': '
            .implode(' | ', $endLabels);

        return implode(' → ', $chain).' → '.$endsSegment;
    }

    /**
     * @param  array<int, mixed>  $steps
     * @return list<string>
     */
    private static function formatStepLabels(array $steps): array
    {
        $labels = [];

        foreach ($steps as $step) {
            if (! is_array($step)) {
                continue;
            }

            $data = is_array($step['data'] ?? null) ? $step['data'] : [];
            $type = (string) ($step['type'] ?? '');
            $labels[] = self::formatStepLabel($type, $data);
        }

        return $labels;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function formatStepLabel(string $type, array $data): string
    {
        $label = trim((string) ($data['step_label'] ?? ''));

        if ($label === '') {
            $label = match ($type) {
                StandardWorkflowStepTypes::CONDITION => (string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.blocks.condition'),
                StandardWorkflowStepTypes::ACTION => (string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.blocks.action'),
                default => (string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.blocks.approval'),
            };
        }

        if ($type === StandardWorkflowStepTypes::CONDITION) {
            return $label.' ('.(string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.flow_preview_kinds.condition').')';
        }

        if ($type === StandardWorkflowStepTypes::ACTION) {
            return $label.' ('.(string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.flow_preview_kinds.action').')';
        }

        return $label;
    }

    /**
     * @return list<string>
     */
    private static function formatEndOutcomeLabels(mixed $endOutcomes): array
    {
        if (! is_array($endOutcomes) || $endOutcomes === []) {
            return [];
        }

        $labels = [];

        foreach ($endOutcomes as $outcome) {
            if (! is_array($outcome)) {
                continue;
            }

            $labels[] = self::formatEndOutcomeLabel($outcome);
        }

        return $labels;
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    private static function formatEndOutcomeLabel(array $outcome): string
    {
        $name = trim((string) ($outcome['step_label'] ?? ''));

        if ($name === '') {
            $name = (string) __('dbflow-filament::dbflow-filament.forms.workflow_definitions.end_outcome_untitled');
        }

        $status = StandardWorkflowEndStatuses::normalize($outcome['end_status'] ?? null);
        $statusLabel = (string) __("dbflow-filament::dbflow-filament.forms.workflow_definitions.end_statuses.{$status}");

        return $name.' ('.$statusLabel.')';
    }
}
