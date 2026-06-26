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

use DbflowLabs\Core\Definitions\WorkflowDefinitionSchema;
use DbflowLabs\Core\Support\WorkflowBuilderNodeFactory;
use DbflowLabs\Core\Models\Workflow;
use InvalidArgumentException;

final class LinearApprovalDefinitionMapper
{
    /**
     * @return list<string>
     */
    public static function supportedAssigneeTypes(): array
    {
        return [
            WorkflowDefinitionSchema::ASSIGNEE_TYPE_USER,
            WorkflowDefinitionSchema::ASSIGNEE_TYPE_PERMISSION,
        ];
    }

    /**
     * @return list<string>
     */
    public static function supportedApprovalModes(): array
    {
        return [
            WorkflowDefinitionSchema::APPROVAL_MODE_ANY,
            WorkflowDefinitionSchema::APPROVAL_MODE_ALL,
            WorkflowDefinitionSchema::APPROVAL_MODE_SEQUENTIAL,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<array{
     *     step_key: string,
     *     step_label: string,
     *     assignee_type: string,
     *     assignee_value: string,
     *     approval_mode: string,
     * }>|null
     */
    public static function parseApprovalSteps(array $definition): ?array
    {
        $nodes = $definition[WorkflowDefinitionSchema::FIELD_NODES] ?? [];
        $transitions = $definition[WorkflowDefinitionSchema::FIELD_TRANSITIONS] ?? [];

        if (! is_array($nodes) || ! is_array($transitions)) {
            return null;
        }

        $nodesByKey = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                return null;
            }

            $key = $node['key'] ?? null;

            if (! is_string($key) || $key === '') {
                return null;
            }

            $nodesByKey[$key] = $node;
        }

        $defaultTransitions = [];

        foreach ($transitions as $transition) {
            if (! is_array($transition)) {
                continue;
            }

            if (($transition[WorkflowDefinitionSchema::FIELD_IS_DEFAULT] ?? false) !== true) {
                continue;
            }

            $from = $transition[WorkflowDefinitionSchema::FIELD_FROM] ?? null;
            $to = $transition[WorkflowDefinitionSchema::FIELD_TO] ?? null;

            if (is_string($from) && is_string($to) && $from !== '' && $to !== '') {
                $defaultTransitions[$from] = $to;
            }
        }

        $startKeys = array_values(array_filter(
            array_keys($nodesByKey),
            static fn (string $key): bool => ($nodesByKey[$key][WorkflowDefinitionSchema::FIELD_TYPE] ?? null) === WorkflowDefinitionSchema::NODE_TYPE_START,
        ));

        if (count($startKeys) !== 1) {
            return null;
        }

        $currentKey = $startKeys[0];
        $steps = [];
        $visited = [];
        $guard = 0;

        while (true) {
            if (++$guard > 100) {
                return null;
            }

            if (isset($visited[$currentKey])) {
                return null;
            }

            $visited[$currentKey] = true;
            $nextKey = $defaultTransitions[$currentKey] ?? null;

            if (! is_string($nextKey) || $nextKey === '') {
                return null;
            }

            $nextNode = $nodesByKey[$nextKey] ?? null;

            if (! is_array($nextNode)) {
                return null;
            }

            $nextType = $nextNode[WorkflowDefinitionSchema::FIELD_TYPE] ?? null;

            if ($nextType === WorkflowDefinitionSchema::NODE_TYPE_END) {
                break;
            }

            if ($nextType !== WorkflowDefinitionSchema::NODE_TYPE_APPROVAL) {
                return null;
            }

            $config = $nextNode[WorkflowDefinitionSchema::FIELD_CONFIG] ?? [];
            $assignees = is_array($config) ? ($config[WorkflowDefinitionSchema::CONFIG_ASSIGNEES] ?? []) : [];
            $assigneeType = is_array($assignees)
                ? (string) ($assignees[WorkflowDefinitionSchema::ASSIGNEE_FIELD_TYPE] ?? WorkflowDefinitionSchema::ASSIGNEE_TYPE_USER)
                : WorkflowDefinitionSchema::ASSIGNEE_TYPE_USER;
            $assigneeValue = is_array($assignees)
                ? (string) ($assignees[WorkflowDefinitionSchema::ASSIGNEE_FIELD_VALUE] ?? '')
                : '';
            $approvalMode = is_array($config)
                ? (string) ($config[WorkflowDefinitionSchema::CONFIG_APPROVAL_MODE] ?? WorkflowDefinitionSchema::APPROVAL_MODE_ANY)
                : WorkflowDefinitionSchema::APPROVAL_MODE_ANY;

            if (! in_array($approvalMode, self::supportedApprovalModes(), true)) {
                $approvalMode = WorkflowDefinitionSchema::APPROVAL_MODE_ANY;
            }

            $steps[] = [
                'step_key' => $nextKey,
                'step_label' => (string) ($nextNode['name'] ?? $nextKey),
                'assignee_type' => $assigneeType,
                'assignee_value' => $assigneeValue,
                'approval_mode' => $approvalMode,
            ];

            $currentKey = $nextKey;
        }

        return $steps === [] ? null : $steps;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function isLinearApprovalDefinition(array $definition): bool
    {
        $nodes = $definition[WorkflowDefinitionSchema::FIELD_NODES] ?? [];

        if (! is_array($nodes) || $nodes === []) {
            return false;
        }

        $startCount = 0;
        $endCount = 0;

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                return false;
            }

            $type = $node[WorkflowDefinitionSchema::FIELD_TYPE] ?? null;

            if ($type === WorkflowDefinitionSchema::NODE_TYPE_START) {
                $startCount++;
            }

            if ($type === WorkflowDefinitionSchema::NODE_TYPE_END) {
                $endCount++;
            }

            if (in_array($type, [
                WorkflowDefinitionSchema::NODE_TYPE_CONDITION,
                WorkflowDefinitionSchema::NODE_TYPE_ACTION,
            ], true)) {
                return false;
            }
        }

        if ($startCount !== 1 || $endCount !== 1) {
            return false;
        }

        return self::parseApprovalSteps($definition) !== null;
    }

    /**
     * @param  list<array{
     *     step_key?: string|null,
     *     step_label?: string|null,
     *     assignee_type?: string|null,
     *     assignee_value?: string|null,
     *     approval_mode?: string|null,
     * }>  $steps
     * @return array<string, mixed>
     */
    public static function buildDefinition(
        Workflow $workflow,
        array $steps,
        ?string $name = null,
        ?string $description = null,
    ): array {
        if ($steps === []) {
            throw new InvalidArgumentException('At least one approval step is required.');
        }

        $factory = app(WorkflowBuilderNodeFactory::class);
        $nodes = [
            $factory->make(
                WorkflowDefinitionSchema::NODE_TYPE_START,
                'start',
                'Start',
                ['x' => 100, 'y' => 120],
            ),
        ];

        $approvalKeys = [];
        $reservedKeys = ['start', 'end'];
        $y = 220;

        foreach (array_values($steps) as $index => $step) {
            $stepKey = trim((string) ($step['step_key'] ?? ''));

            $stepLabel = trim((string) ($step['step_label'] ?? ''));

            if ($stepLabel === '') {
                $stepLabel = $index === 0
                    ? 'Approval'
                    : 'Approval Step '.($index + 1);
            }

            if ($stepKey === '') {
                $stepKey = WorkflowStepKeyGenerator::fromLabel(
                    $stepLabel,
                    WorkflowDefinitionSchema::NODE_TYPE_APPROVAL,
                    $reservedKeys,
                );
            }

            $reservedKeys[] = $stepKey;

            $assigneeType = (string) ($step['assignee_type'] ?? WorkflowDefinitionSchema::ASSIGNEE_TYPE_USER);

            if (! in_array($assigneeType, self::supportedAssigneeTypes(), true)) {
                $assigneeType = WorkflowDefinitionSchema::ASSIGNEE_TYPE_USER;
            }

            $assigneeValue = trim((string) ($step['assignee_value'] ?? ''));

            $approvalMode = (string) ($step['approval_mode'] ?? WorkflowDefinitionSchema::APPROVAL_MODE_ANY);

            if (! in_array($approvalMode, self::supportedApprovalModes(), true)) {
                $approvalMode = WorkflowDefinitionSchema::APPROVAL_MODE_ANY;
            }

            $nodes[] = [
                ...$factory->make(
                    WorkflowDefinitionSchema::NODE_TYPE_APPROVAL,
                    $stepKey,
                    $stepLabel,
                    ['x' => 100, 'y' => $y],
                ),
                WorkflowDefinitionSchema::FIELD_CONFIG => [
                    WorkflowDefinitionSchema::CONFIG_APPROVAL_MODE => $approvalMode,
                    WorkflowDefinitionSchema::CONFIG_ASSIGNEES => [
                        WorkflowDefinitionSchema::ASSIGNEE_FIELD_TYPE => $assigneeType,
                        WorkflowDefinitionSchema::ASSIGNEE_FIELD_VALUE => $assigneeValue,
                    ],
                ],
            ];

            $approvalKeys[] = $stepKey;
            $y += 100;
        }

        $nodes[] = $factory->make(
            WorkflowDefinitionSchema::NODE_TYPE_END,
            'end',
            'End',
            ['x' => 100, 'y' => $y],
        );

        $chain = array_merge(['start'], $approvalKeys, ['end']);
        $transitions = [];

        for ($index = 0; $index < count($chain) - 1; $index++) {
            $transitions[] = [
                WorkflowDefinitionSchema::FIELD_FROM => $chain[$index],
                WorkflowDefinitionSchema::FIELD_TO => $chain[$index + 1],
                WorkflowDefinitionSchema::FIELD_IS_DEFAULT => true,
            ];
        }

        $definition = [
            WorkflowDefinitionSchema::FIELD_KEY => $workflow->key,
            WorkflowDefinitionSchema::FIELD_NAME => $name ?? $workflow->name,
            WorkflowDefinitionSchema::FIELD_SCHEMA_VERSION => '1.0',
            WorkflowDefinitionSchema::FIELD_NODES => $nodes,
            WorkflowDefinitionSchema::FIELD_TRANSITIONS => $transitions,
        ];

        $resolvedDescription = $description ?? $workflow->description;

        if (is_string($resolvedDescription) && $resolvedDescription !== '') {
            $definition[WorkflowDefinitionSchema::FIELD_DESCRIPTION] = $resolvedDescription;
        }

        return $definition;
    }
}
