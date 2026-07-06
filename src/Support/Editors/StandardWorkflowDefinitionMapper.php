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
use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Core\Support\WorkflowBuilderNodeFactory;
use InvalidArgumentException;

/**
 * Maps Standard Filament Builder form state to Core workflow definition arrays.
 *
 * Output conforms to {@see WorkflowDefinitionSchema} —the persistence contract consumed
 * by DbflowLabs\Core validation and runtime execution.
 */
final class StandardWorkflowDefinitionMapper
{
    private const CONFIG_ACTION_KEY = 'action_key';

    /** @deprecated Legacy config key; read on hydrate only —never written on dehydration. */
    private const LEGACY_CONFIG_ACTION = 'action';

    private const METADATA_STANDARD_EDITOR = 'standard_editor';

    private const METADATA_LAYOUT_LINEAR = 'linear';

    /**
     * @return list<string>
     */
    public static function supportedAssigneeTypes(): array
    {
        return LinearApprovalDefinitionMapper::supportedAssigneeTypes();
    }

    /**
     * @return list<string>
     */
    public static function supportedApprovalModes(): array
    {
        return LinearApprovalDefinitionMapper::supportedApprovalModes();
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{
     *     workflow_steps: list<array{type: string, data: array<string, mixed>}>,
     *     end_outcomes: list<array<string, mixed>>,
     * }|null
     */
    public static function parseFormState(array $definition): ?array
    {
        $workflowSteps = self::parseWorkflowSteps($definition);
        $endOutcomes = self::parseEndOutcomes($definition);

        if ($workflowSteps === null || $endOutcomes === null) {
            return null;
        }

        return [
            'workflow_steps' => $workflowSteps,
            'end_outcomes' => $endOutcomes,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function isStandardFormDefinition(array $definition): bool
    {
        return self::parseFormState($definition) !== null;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<array{
     *     step_key: string,
     *     step_label: string,
     *     end_status: string,
     * }>|null
     */
    public static function parseEndOutcomes(array $definition): ?array
    {
        $nodes = $definition[WorkflowDefinitionSchema::FIELD_NODES] ?? [];

        if (! is_array($nodes) || $nodes === []) {
            return null;
        }

        $endNodes = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                return null;
            }

            if (($node[WorkflowDefinitionSchema::FIELD_TYPE] ?? null) !== WorkflowDefinitionSchema::NODE_TYPE_END) {
                continue;
            }

            $key = $node['key'] ?? null;

            if (! is_string($key) || $key === '') {
                return null;
            }

            $endNodes[] = $node;
        }

        if ($endNodes === []) {
            return null;
        }

        usort($endNodes, static function (array $left, array $right): int {
            $leftKey = (string) ($left['key'] ?? '');
            $rightKey = (string) ($right['key'] ?? '');

            if ($leftKey === 'end') {
                return -1;
            }

            if ($rightKey === 'end') {
                return 1;
            }

            return $leftKey <=> $rightKey;
        });

        $outcomes = [];

        foreach ($endNodes as $node) {
            $outcomes[] = self::parseEndOutcomeRow($node);
        }

        return $outcomes;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{step_key: string, step_label: string, end_status: string}
     */
    private static function parseEndOutcomeRow(array $node): array
    {
        $key = (string) ($node['key'] ?? '');
        $config = is_array($node[WorkflowDefinitionSchema::FIELD_CONFIG] ?? null)
            ? $node[WorkflowDefinitionSchema::FIELD_CONFIG]
            : [];
        $metadata = is_array($node[WorkflowDefinitionSchema::FIELD_METADATA] ?? null)
            ? $node[WorkflowDefinitionSchema::FIELD_METADATA]
            : [];
        $proEditor = is_array($metadata['pro_editor'] ?? null) ? $metadata['pro_editor'] : [];

        $status = $config[WorkflowDefinitionSchema::CONFIG_STATUS]
            ?? $proEditor['end_status']
            ?? StandardWorkflowEndStatuses::COMPLETED;

        return [
            'step_key' => $key,
            'step_label' => (string) ($node['name'] ?? $key),
            'end_status' => StandardWorkflowEndStatuses::normalize($status),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<array{type: string, data: array<string, mixed>}>|null
     */
    public static function parseWorkflowSteps(array $definition): ?array
    {
        $nodes = $definition[WorkflowDefinitionSchema::FIELD_NODES] ?? [];
        $transitions = $definition[WorkflowDefinitionSchema::FIELD_TRANSITIONS] ?? [];

        if (! is_array($nodes) || ! is_array($transitions) || $nodes === []) {
            return null;
        }

        $nodesByKey = self::indexNodesByKey($nodes);

        if ($nodesByKey === null) {
            return null;
        }

        $outgoing = self::indexOutgoingTransitions($transitions);

        $startKeys = array_keys(array_filter(
            $nodesByKey,
            static fn (array $node): bool => ($node[WorkflowDefinitionSchema::FIELD_TYPE] ?? null) === WorkflowDefinitionSchema::NODE_TYPE_START,
        ));

        if (count($startKeys) !== 1) {
            return null;
        }

        $orderedKeys = self::collectStepKeysInVisitOrder($startKeys[0], $nodesByKey, $outgoing);

        if ($orderedKeys === null) {
            return null;
        }

        $stepKeys = array_values(array_filter(
            $orderedKeys,
            static fn (string $key): bool => in_array(
                $nodesByKey[$key][WorkflowDefinitionSchema::FIELD_TYPE] ?? null,
                StandardWorkflowStepTypes::configurable(),
                true,
            ),
        ));

        if ($stepKeys === []) {
            return null;
        }

        $blocks = [];

        foreach ($stepKeys as $index => $stepKey) {
            $node = $nodesByKey[$stepKey];
            $type = (string) ($node[WorkflowDefinitionSchema::FIELD_TYPE] ?? '');
            $block = self::parseNodeBlock($type, $stepKey, $node, $index, $stepKeys, $nodesByKey, $outgoing);

            if ($block === null) {
                return null;
            }

            $blocks[] = $block;
        }

        return $blocks;
    }

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     * @param  list<array<string, mixed>>  $endOutcomes
     * @return array<string, mixed>
     */
    public static function buildDefinition(
        Workflow $workflow,
        array $blocks,
        ?string $name = null,
        ?string $description = null,
        array $endOutcomes = [],
    ): array {
        if ($blocks === []) {
            throw new InvalidArgumentException('At least one workflow step is required.');
        }

        $factory = app(WorkflowBuilderNodeFactory::class);
        $normalizedEndOutcomes = self::normalizeEndOutcomes($endOutcomes);
        $reservedKeys = ['start'];
        $resolvedStepKeys = [];
        $startPosition = ['x' => 100, 'y' => 120];
        $nodes = [
            [
                ...$factory->make(
                    WorkflowDefinitionSchema::NODE_TYPE_START,
                    'start',
                    'Start',
                    $startPosition,
                ),
                WorkflowDefinitionSchema::FIELD_METADATA => self::buildNodeGridMetadata($startPosition),
            ],
        ];

        $y = 220;

        foreach (array_values($blocks) as $index => $block) {
            $type = (string) ($block['type'] ?? '');
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            if (! in_array($type, StandardWorkflowStepTypes::configurable(), true)) {
                throw new InvalidArgumentException("Unsupported workflow step type [{$type}].");
            }

            $stepKey = self::resolveStepKey($type, $data, $index, $reservedKeys);
            $reservedKeys[] = $stepKey;
            $resolvedStepKeys[] = $stepKey;

            $stepLabel = self::resolveStepLabel($type, $data, $index);
            $nodes[] = self::buildNode($factory, $type, $stepKey, $stepLabel, $data, $y);
            $y += 100;
        }

        $builtEnds = self::buildEndNodes($factory, $normalizedEndOutcomes, $reservedKeys, $y);
        $nodes = [...$nodes, ...$builtEnds['nodes']];
        $resolvedEndKeys = $builtEnds['keys'];
        $defaultEndKey = $builtEnds['default_key'];

        $transitions = self::buildTransitions($blocks, $resolvedStepKeys, $resolvedEndKeys, $defaultEndKey);

        $definition = [
            WorkflowDefinitionSchema::FIELD_KEY => $workflow->key,
            WorkflowDefinitionSchema::FIELD_NAME => $name ?? $workflow->name,
            WorkflowDefinitionSchema::FIELD_SCHEMA_VERSION => '1.0',
            WorkflowDefinitionSchema::FIELD_METADATA => self::buildDefinitionMetadata($nodes),
            WorkflowDefinitionSchema::FIELD_NODES => $nodes,
            WorkflowDefinitionSchema::FIELD_TRANSITIONS => $transitions,
        ];

        $resolvedDescription = $description ?? $workflow->description;

        if (is_string($resolvedDescription) && $resolvedDescription !== '') {
            $definition[WorkflowDefinitionSchema::FIELD_DESCRIPTION] = $resolvedDescription;
        }

        return $definition;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, array<string, mixed>>|null
     */
    private static function indexNodesByKey(array $nodes): ?array
    {
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

        return $nodesByKey;
    }

    /**
     * @param  array<int, array<string, mixed>>  $transitions
     * @return array<string, list<array<string, mixed>>>
     */
    private static function indexOutgoingTransitions(array $transitions): array
    {
        $outgoing = [];

        foreach ($transitions as $transition) {
            if (! is_array($transition)) {
                continue;
            }

            $from = $transition[WorkflowDefinitionSchema::FIELD_FROM] ?? null;

            if (! is_string($from) || $from === '') {
                continue;
            }

            $outgoing[$from][] = $transition;
        }

        return $outgoing;
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodesByKey
     * @param  array<string, list<array<string, mixed>>>  $outgoing
     * @return list<string>|null
     */
    private static function collectStepKeysInVisitOrder(
        string $startKey,
        array $nodesByKey,
        array $outgoing,
    ): ?array {
        $startTransitions = $outgoing[$startKey] ?? [];

        if ($startTransitions === []) {
            return null;
        }

        $firstKey = self::resolveDefaultTransitionTarget($startTransitions);

        if ($firstKey === null) {
            return null;
        }

        $ordered = [];
        $queue = [$firstKey];
        $visited = [];

        while ($queue !== []) {
            $currentKey = array_shift($queue);

            if (isset($visited[$currentKey])) {
                continue;
            }

            $visited[$currentKey] = true;
            $ordered[] = $currentKey;

            $node = $nodesByKey[$currentKey] ?? null;

            if (! is_array($node)) {
                return null;
            }

            $type = $node[WorkflowDefinitionSchema::FIELD_TYPE] ?? null;

            if ($type === WorkflowDefinitionSchema::NODE_TYPE_END) {
                continue;
            }

            foreach ($outgoing[$currentKey] ?? [] as $transition) {
                $target = $transition[WorkflowDefinitionSchema::FIELD_TO] ?? null;

                if (! is_string($target) || $target === '') {
                    continue;
                }

                $targetType = $nodesByKey[$target][WorkflowDefinitionSchema::FIELD_TYPE] ?? null;

                if ($targetType === WorkflowDefinitionSchema::NODE_TYPE_END) {
                    continue;
                }

                if (! isset($visited[$target])) {
                    $queue[] = $target;
                }
            }
        }

        return $ordered;
    }

    /**
     * @param  list<array<string, mixed>>  $transitions
     */
    private static function resolveDefaultTransitionTarget(array $transitions): ?string
    {
        foreach ($transitions as $transition) {
            if (($transition[WorkflowDefinitionSchema::FIELD_IS_DEFAULT] ?? false) === true) {
                $to = $transition[WorkflowDefinitionSchema::FIELD_TO] ?? null;

                if (is_string($to) && $to !== '') {
                    return $to;
                }
            }
        }

        if (count($transitions) === 1) {
            $to = $transitions[0][WorkflowDefinitionSchema::FIELD_TO] ?? null;

            return is_string($to) && $to !== '' ? $to : null;
        }

        $to = $transitions[0][WorkflowDefinitionSchema::FIELD_TO] ?? null;

        return is_string($to) && $to !== '' ? $to : null;
    }

    /**
     * @param  list<string>  $stepKeys
     * @param  array<string, array<string, mixed>>  $nodesByKey
     * @param  array<string, list<array<string, mixed>>>  $outgoing
     * @return array{type: string, data: array<string, mixed>}|null
     */
    private static function parseNodeBlock(
        string $type,
        string $stepKey,
        array $node,
        int $index,
        array $stepKeys,
        array $nodesByKey,
        array $outgoing,
    ): ?array {
        $label = (string) ($node['name'] ?? $stepKey);

        return match ($type) {
            StandardWorkflowStepTypes::APPROVAL => self::parseApprovalBlock($stepKey, $label, $node),
            StandardWorkflowStepTypes::ACTION => self::parseActionBlock($stepKey, $label, $node),
            StandardWorkflowStepTypes::CONDITION => self::parseConditionBlock(
                $stepKey,
                $label,
                $node,
                $index,
                $stepKeys,
                $nodesByKey,
                $outgoing,
            ),
            default => null,
        };
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    private static function parseApprovalBlock(string $stepKey, string $label, array $node): array
    {
        $config = is_array($node[WorkflowDefinitionSchema::FIELD_CONFIG] ?? null)
            ? $node[WorkflowDefinitionSchema::FIELD_CONFIG]
            : [];
        $assignees = is_array($config[WorkflowDefinitionSchema::CONFIG_ASSIGNEES] ?? null)
            ? $config[WorkflowDefinitionSchema::CONFIG_ASSIGNEES]
            : [];

        return [
            'type' => StandardWorkflowStepTypes::APPROVAL,
            'data' => [
                'step_key' => $stepKey,
                'step_label' => $label,
                'assignee_type' => (string) ($assignees[WorkflowDefinitionSchema::ASSIGNEE_FIELD_TYPE] ?? WorkflowDefinitionSchema::ASSIGNEE_TYPE_USER),
                'assignee_value' => (string) ($assignees[WorkflowDefinitionSchema::ASSIGNEE_FIELD_VALUE] ?? ''),
                'approval_mode' => (string) ($config[WorkflowDefinitionSchema::CONFIG_APPROVAL_MODE] ?? WorkflowDefinitionSchema::APPROVAL_MODE_ANY),
                ...self::parseTimeoutFormFields($config),
            ],
        ];
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    private static function parseActionBlock(string $stepKey, string $label, array $node): array
    {
        $config = is_array($node[WorkflowDefinitionSchema::FIELD_CONFIG] ?? null)
            ? $node[WorkflowDefinitionSchema::FIELD_CONFIG]
            : [];
        $actionKey = is_string($config[self::CONFIG_ACTION_KEY] ?? null) && $config[self::CONFIG_ACTION_KEY] !== ''
            ? $config[self::CONFIG_ACTION_KEY]
            : (string) ($config[self::LEGACY_CONFIG_ACTION] ?? '');
        $payload = is_array($config['payload'] ?? null) ? $config['payload'] : [];

        return [
            'type' => StandardWorkflowStepTypes::ACTION,
            'data' => [
                'step_key' => $stepKey,
                'step_label' => $label,
                'action_key' => $actionKey,
                'payload' => $payload,
            ],
        ];
    }

    /**
     * @param  list<string>  $stepKeys
     * @param  array<string, array<string, mixed>>  $nodesByKey
     * @param  array<string, list<array<string, mixed>>>  $outgoing
     * @return array{type: string, data: array<string, mixed>}|null
     */
    private static function parseConditionBlock(
        string $stepKey,
        string $label,
        array $node,
        int $index,
        array $stepKeys,
        array $nodesByKey,
        array $outgoing,
    ): ?array {
        $config = is_array($node[WorkflowDefinitionSchema::FIELD_CONFIG] ?? null)
            ? $node[WorkflowDefinitionSchema::FIELD_CONFIG]
            : [];
        $expression = trim((string) ($config[WorkflowDefinitionSchema::CONFIG_EXPRESSION] ?? ''));

        if ($expression === '') {
            return null;
        }

        $trueTarget = null;
        $falseTarget = null;

        foreach ($outgoing[$stepKey] ?? [] as $transition) {
            $to = $transition[WorkflowDefinitionSchema::FIELD_TO] ?? null;

            if (! is_string($to) || $to === '') {
                continue;
            }

            $condition = $transition[WorkflowDefinitionSchema::FIELD_CONDITION] ?? null;
            $isDefault = ($transition[WorkflowDefinitionSchema::FIELD_IS_DEFAULT] ?? false) === true;

            if (is_string($condition) && $condition !== '') {
                $trueTarget = $to;
            } elseif ($isDefault) {
                $falseTarget = $to;
            }
        }

        if ($trueTarget === null || $falseTarget === null) {
            return null;
        }

        return [
            'type' => StandardWorkflowStepTypes::CONDITION,
            'data' => [
                'step_key' => $stepKey,
                'step_label' => $label,
                'expression' => $expression,
                'true_branch' => self::encodeBranchTarget($trueTarget, $index, $stepKeys, $nodesByKey),
                'true_branch_step_key' => self::encodeBranchStepKey($trueTarget, $index, $stepKeys, $nodesByKey),
                'true_branch_end_key' => self::encodeBranchEndKey($trueTarget, $nodesByKey),
                'false_branch' => self::encodeBranchTarget($falseTarget, $index, $stepKeys, $nodesByKey),
                'false_branch_step_key' => self::encodeBranchStepKey($falseTarget, $index, $stepKeys, $nodesByKey),
                'false_branch_end_key' => self::encodeBranchEndKey($falseTarget, $nodesByKey),
            ],
        ];
    }

    /**
     * @param  list<string>  $stepKeys
     * @param  array<string, array<string, mixed>>  $nodesByKey
     */
    private static function encodeBranchTarget(
        string $targetKey,
        int $currentIndex,
        array $stepKeys,
        array $nodesByKey,
    ): string {
        $targetType = $nodesByKey[$targetKey][WorkflowDefinitionSchema::FIELD_TYPE] ?? null;

        if ($targetType === WorkflowDefinitionSchema::NODE_TYPE_END) {
            return StandardWorkflowBranchTargets::END_OUTCOME;
        }

        $targetIndex = array_search($targetKey, $stepKeys, true);

        if ($targetIndex === $currentIndex + 1) {
            return StandardWorkflowBranchTargets::NEXT;
        }

        return StandardWorkflowBranchTargets::STEP;
    }

    /**
     * @param  list<string>  $stepKeys
     * @param  array<string, array<string, mixed>>  $nodesByKey
     */
    private static function encodeBranchStepKey(
        string $targetKey,
        int $currentIndex,
        array $stepKeys,
        array $nodesByKey,
    ): ?string {
        $branch = self::encodeBranchTarget($targetKey, $currentIndex, $stepKeys, $nodesByKey);

        return $branch === StandardWorkflowBranchTargets::STEP ? $targetKey : null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodesByKey
     */
    private static function encodeBranchEndKey(string $targetKey, array $nodesByKey): ?string
    {
        $targetType = $nodesByKey[$targetKey][WorkflowDefinitionSchema::FIELD_TYPE] ?? null;

        return $targetType === WorkflowDefinitionSchema::NODE_TYPE_END ? $targetKey : null;
    }

    /**
     * @param  list<array<string, mixed>>  $endOutcomes
     * @return list<array<string, mixed>>
     */
    private static function normalizeEndOutcomes(array $endOutcomes): array
    {
        if ($endOutcomes === []) {
            return [
                [
                    'step_label' => 'End',
                    'end_status' => StandardWorkflowEndStatuses::COMPLETED,
                    'step_key' => 'end',
                ],
            ];
        }

        return $endOutcomes;
    }

    /**
     * @param  list<array<string, mixed>>  $endOutcomes
     * @param  list<string>  $reservedKeys
     * @return array{
     *     nodes: list<array<string, mixed>>,
     *     keys: list<string>,
     *     default_key: string,
     * }
     */
    private static function buildEndNodes(
        WorkflowBuilderNodeFactory $factory,
        array $endOutcomes,
        array $reservedKeys,
        int $startY,
    ): array {
        $nodes = [];
        $keys = [];
        $y = $startY;

        foreach (array_values($endOutcomes) as $index => $outcome) {
            if (! is_array($outcome)) {
                continue;
            }

            $label = trim((string) ($outcome['step_label'] ?? ''));

            if ($label === '') {
                $label = $index === 0 ? 'End' : 'End '.($index + 1);
            }

            $stepKey = trim((string) ($outcome['step_key'] ?? ''));

            if ($stepKey === '') {
                $stepKey = $index === 0 && ! in_array('end', $reservedKeys, true)
                    ? 'end'
                    : WorkflowStepKeyGenerator::fromLabel($label, WorkflowDefinitionSchema::NODE_TYPE_END, $reservedKeys);
            }

            $reservedKeys[] = $stepKey;
            $keys[] = $stepKey;

            $endPosition = ['x' => 100, 'y' => $y];

            $nodes[] = [
                ...$factory->make(
                    WorkflowDefinitionSchema::NODE_TYPE_END,
                    $stepKey,
                    $label,
                    $endPosition,
                ),
                WorkflowDefinitionSchema::FIELD_METADATA => self::buildNodeGridMetadata($endPosition),
                WorkflowDefinitionSchema::FIELD_CONFIG => [
                    WorkflowDefinitionSchema::CONFIG_STATUS => StandardWorkflowEndStatuses::normalize(
                        $outcome['end_status'] ?? StandardWorkflowEndStatuses::COMPLETED,
                    ),
                ],
            ];

            $y += 100;
        }

        if ($keys === []) {
            throw new InvalidArgumentException('At least one end outcome is required.');
        }

        return [
            'nodes' => $nodes,
            'keys' => $keys,
            'default_key' => $keys[0],
        ];
    }

    /**
     * @param  list<string>  $reservedKeys
     */
    private static function resolveStepKey(string $type, array $data, int $index, array $reservedKeys): string
    {
        $stepKey = trim((string) ($data['step_key'] ?? ''));

        if ($stepKey !== '') {
            return $stepKey;
        }

        $label = trim((string) ($data['step_label'] ?? ''));

        if ($label === '') {
            $label = match ($type) {
                StandardWorkflowStepTypes::CONDITION => 'Condition Step '.($index + 1),
                StandardWorkflowStepTypes::ACTION => 'Action Step '.($index + 1),
                default => $index === 0 ? 'Approval' : 'Approval Step '.($index + 1),
            };
        }

        return WorkflowStepKeyGenerator::fromLabel($label, $type, $reservedKeys);
    }

    private static function resolveStepLabel(string $type, array $data, int $index): string
    {
        $stepLabel = trim((string) ($data['step_label'] ?? ''));

        if ($stepLabel !== '') {
            return $stepLabel;
        }

        return match ($type) {
            StandardWorkflowStepTypes::CONDITION => 'Condition Step '.($index + 1),
            StandardWorkflowStepTypes::ACTION => 'Action Step '.($index + 1),
            default => $index === 0 ? 'Approval' : 'Approval Step '.($index + 1),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function buildNode(
        WorkflowBuilderNodeFactory $factory,
        string $type,
        string $stepKey,
        string $stepLabel,
        array $data,
        int $y,
    ): array {
        $position = ['x' => 100, 'y' => $y];
        $baseNode = $factory->make($type, $stepKey, $stepLabel, $position);

        return match ($type) {
            StandardWorkflowStepTypes::APPROVAL => [
                ...$baseNode,
                WorkflowDefinitionSchema::FIELD_METADATA => self::buildNodeGridMetadata($position),
                WorkflowDefinitionSchema::FIELD_CONFIG => self::buildApprovalConfig($data),
            ],
            StandardWorkflowStepTypes::CONDITION => [
                ...$baseNode,
                WorkflowDefinitionSchema::FIELD_METADATA => self::buildNodeGridMetadata($position),
                WorkflowDefinitionSchema::FIELD_CONFIG => [
                    WorkflowDefinitionSchema::CONFIG_EXPRESSION => trim((string) ($data['expression'] ?? '')),
                ],
            ],
            StandardWorkflowStepTypes::ACTION => [
                ...$baseNode,
                WorkflowDefinitionSchema::FIELD_METADATA => self::buildNodeGridMetadata($position),
                WorkflowDefinitionSchema::FIELD_CONFIG => self::buildActionConfig($data),
            ],
            default => $baseNode,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function buildApprovalConfig(array $data): array
    {
        $assigneeType = (string) ($data['assignee_type'] ?? WorkflowDefinitionSchema::ASSIGNEE_TYPE_USER);

        if (! in_array($assigneeType, self::supportedAssigneeTypes(), true)) {
            $assigneeType = WorkflowDefinitionSchema::ASSIGNEE_TYPE_USER;
        }

        $approvalMode = (string) ($data['approval_mode'] ?? WorkflowDefinitionSchema::APPROVAL_MODE_ANY);

        if (! in_array($approvalMode, self::supportedApprovalModes(), true)) {
            $approvalMode = WorkflowDefinitionSchema::APPROVAL_MODE_ANY;
        }

        $config = [
            WorkflowDefinitionSchema::CONFIG_APPROVAL_MODE => $approvalMode,
            WorkflowDefinitionSchema::CONFIG_ASSIGNEES => [
                WorkflowDefinitionSchema::ASSIGNEE_FIELD_TYPE => $assigneeType,
                WorkflowDefinitionSchema::ASSIGNEE_FIELD_VALUE => trim((string) ($data['assignee_value'] ?? '')),
            ],
        ];

        $timeout = self::buildTimeoutConfig($data);

        if ($timeout !== []) {
            $config[WorkflowDefinitionSchema::CONFIG_TIMEOUT] = $timeout;
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{timeout_due_in: string, timeout_on_timeout: string}
     */
    private static function parseTimeoutFormFields(array $config): array
    {
        $timeout = $config[WorkflowDefinitionSchema::CONFIG_TIMEOUT] ?? null;

        if (! is_array($timeout) || $timeout === []) {
            return [
                'timeout_due_in' => '',
                'timeout_on_timeout' => '',
            ];
        }

        return [
            'timeout_due_in' => is_string($timeout[WorkflowDefinitionSchema::TIMEOUT_DUE_IN] ?? null)
                ? $timeout[WorkflowDefinitionSchema::TIMEOUT_DUE_IN]
                : '',
            'timeout_on_timeout' => is_string($timeout[WorkflowDefinitionSchema::TIMEOUT_ON_TIMEOUT] ?? null)
                ? $timeout[WorkflowDefinitionSchema::TIMEOUT_ON_TIMEOUT]
                : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private static function buildTimeoutConfig(array $data): array
    {
        $dueIn = trim((string) ($data['timeout_due_in'] ?? ''));

        if ($dueIn === '') {
            return [];
        }

        $timeout = [
            WorkflowDefinitionSchema::TIMEOUT_DUE_IN => $dueIn,
        ];

        $onTimeout = trim((string) ($data['timeout_on_timeout'] ?? ''));

        if ($onTimeout !== '') {
            $timeout[WorkflowDefinitionSchema::TIMEOUT_ON_TIMEOUT] = $onTimeout;
        }

        return $timeout;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function buildActionConfig(array $data): array
    {
        $actionKey = trim((string) ($data['action_key'] ?? ''));

        if ($actionKey === '' && is_string($data[self::LEGACY_CONFIG_ACTION] ?? null)) {
            $actionKey = trim($data[self::LEGACY_CONFIG_ACTION]);
        }

        $config = [
            self::CONFIG_ACTION_KEY => $actionKey,
        ];

        $payload = $data['payload'] ?? null;

        if (is_array($payload) && $payload !== []) {
            $config['payload'] = $payload;
        }

        return $config;
    }

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     * @param  list<string>  $resolvedStepKeys
     * @param  list<string>  $resolvedEndKeys
     * @return list<array<string, mixed>>
     */
    private static function buildTransitions(
        array $blocks,
        array $resolvedStepKeys,
        array $resolvedEndKeys,
        string $defaultEndKey,
    ): array {
        $transitions = [];

        foreach ($blocks as $index => $block) {
            $currentKey = $resolvedStepKeys[$index];
            $type = (string) ($block['type'] ?? '');
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            if ($index === 0) {
                $transitions[] = self::defaultTransition('start', $currentKey);
            } elseif (($blocks[$index - 1]['type'] ?? null) === StandardWorkflowStepTypes::CONDITION) {
                $previousData = is_array($blocks[$index - 1]['data'] ?? null)
                    ? $blocks[$index - 1]['data']
                    : [];
                $trueTarget = self::resolveBranchTargetKey(
                    $previousData,
                    'true',
                    $index - 1,
                    $resolvedStepKeys,
                    $resolvedEndKeys,
                    $defaultEndKey,
                );

                if ($trueTarget !== $currentKey) {
                    $transitions[] = self::defaultTransition($resolvedStepKeys[$index - 1], $currentKey);
                }
            } else {
                $transitions[] = self::defaultTransition($resolvedStepKeys[$index - 1], $currentKey);
            }

            if ($type === StandardWorkflowStepTypes::CONDITION) {
                $expression = trim((string) ($data['expression'] ?? ''));
                $trueTarget = self::resolveBranchTargetKey($data, 'true', $index, $resolvedStepKeys, $resolvedEndKeys, $defaultEndKey);
                $falseTarget = self::resolveBranchTargetKey($data, 'false', $index, $resolvedStepKeys, $resolvedEndKeys, $defaultEndKey);

                $transitions[] = [
                    WorkflowDefinitionSchema::FIELD_FROM => $currentKey,
                    WorkflowDefinitionSchema::FIELD_TO => $trueTarget,
                    WorkflowDefinitionSchema::FIELD_CONDITION => $expression,
                ];
                $transitions[] = [
                    WorkflowDefinitionSchema::FIELD_FROM => $currentKey,
                    WorkflowDefinitionSchema::FIELD_TO => $falseTarget,
                    WorkflowDefinitionSchema::FIELD_IS_DEFAULT => true,
                ];
            }
        }

        $outgoingSources = [];

        foreach ($transitions as $transition) {
            $from = (string) ($transition[WorkflowDefinitionSchema::FIELD_FROM] ?? '');
            $outgoingSources[$from] = true;
        }

        foreach ($resolvedStepKeys as $stepIndex => $stepKey) {
            $blockType = (string) ($blocks[$stepIndex]['type'] ?? '');

            if (! in_array($blockType, [StandardWorkflowStepTypes::APPROVAL, StandardWorkflowStepTypes::ACTION], true)) {
                continue;
            }

            if (! isset($outgoingSources[$stepKey])) {
                $transitions[] = self::defaultTransition($stepKey, $defaultEndKey);
            }
        }

        return $transitions;
    }

    /**
     * @param  list<string>  $resolvedStepKeys
     * @param  list<string>  $resolvedEndKeys
     */
    private static function resolveBranchTargetKey(
        array $data,
        string $branch,
        int $index,
        array $resolvedStepKeys,
        array $resolvedEndKeys,
        string $defaultEndKey,
    ): string {
        $prefix = $branch === 'true' ? 'true_branch' : 'false_branch';
        $target = (string) ($data[$prefix] ?? StandardWorkflowBranchTargets::NEXT);

        if (in_array($target, [StandardWorkflowBranchTargets::END, StandardWorkflowBranchTargets::END_OUTCOME], true)) {
            return self::resolveExplicitBranchEndKey($data, $prefix, $resolvedEndKeys, $defaultEndKey);
        }

        return match ($target) {
            StandardWorkflowBranchTargets::STEP => self::resolveExplicitBranchStepKey($data, $prefix, $resolvedStepKeys, $index, $defaultEndKey),
            default => $resolvedStepKeys[$index + 1] ?? $defaultEndKey,
        };
    }

    /**
     * @param  list<string>  $resolvedStepKeys
     */
    private static function resolveExplicitBranchStepKey(
        array $data,
        string $prefix,
        array $resolvedStepKeys,
        int $index,
        string $defaultEndKey,
    ): string {
        $explicitKey = trim((string) ($data[$prefix.'_step_key'] ?? ''));

        if ($explicitKey !== '' && in_array($explicitKey, $resolvedStepKeys, true)) {
            return $explicitKey;
        }

        return $resolvedStepKeys[$index + 1] ?? $defaultEndKey;
    }

    /**
     * @param  list<string>  $resolvedEndKeys
     */
    private static function resolveExplicitBranchEndKey(
        array $data,
        string $prefix,
        array $resolvedEndKeys,
        string $defaultEndKey,
    ): string {
        $explicitKey = trim((string) ($data[$prefix.'_end_key'] ?? ''));

        if ($explicitKey !== '' && in_array($explicitKey, $resolvedEndKeys, true)) {
            return $explicitKey;
        }

        return $defaultEndKey;
    }

    /**
     * Predict step keys for form selects (matches {@see buildDefinition()} resolution).
     *
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     * @return array<string, string>
     */
    public static function previewStepKeyOptions(array $blocks): array
    {
        $reservedKeys = ['start'];
        $options = [];

        foreach (array_values($blocks) as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            if (! in_array($type, StandardWorkflowStepTypes::configurable(), true)) {
                continue;
            }

            $stepKey = self::resolveStepKey($type, $data, $index, $reservedKeys);
            $reservedKeys[] = $stepKey;
            $options[$stepKey] = self::resolveStepLabel($type, $data, $index);
        }

        return $options;
    }

    /**
     * Predict end-outcome keys for form selects (matches {@see buildDefinition()} resolution).
     *
     * @param  list<array<string, mixed>>  $endOutcomes
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     * @return array<string, string>
     */
    public static function previewEndOutcomeKeyOptions(array $endOutcomes, array $blocks = []): array
    {
        $reservedKeys = ['start'];

        foreach (array_values($blocks) as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            if (! in_array($type, StandardWorkflowStepTypes::configurable(), true)) {
                continue;
            }

            $reservedKeys[] = self::resolveStepKey($type, $data, $index, $reservedKeys);
        }

        $normalized = self::normalizeEndOutcomes($endOutcomes);
        $options = [];

        foreach ($normalized as $index => $outcome) {
            $label = trim((string) ($outcome['step_label'] ?? ''));

            if ($label === '') {
                $label = $index === 0 ? 'End' : 'End '.($index + 1);
            }

            $stepKey = trim((string) ($outcome['step_key'] ?? ''));

            if ($stepKey === '') {
                $stepKey = $index === 0 && ! in_array('end', $reservedKeys, true)
                    ? 'end'
                    : WorkflowStepKeyGenerator::fromLabel($label, WorkflowDefinitionSchema::NODE_TYPE_END, $reservedKeys);
            }

            $reservedKeys[] = $stepKey;
            $options[$stepKey] = $label;
        }

        return $options;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    private static function buildDefinitionMetadata(array $nodes): array
    {
        $nodePositions = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $key = $node['key'] ?? null;
            $position = $node['position'] ?? null;

            if (! is_string($key) || $key === '' || ! is_array($position)) {
                continue;
            }

            $nodePositions[$key] = [
                'x' => (int) ($position['x'] ?? 100),
                'y' => (int) ($position['y'] ?? 120),
            ];
        }

        return [
            self::METADATA_STANDARD_EDITOR => [
                'layout' => self::METADATA_LAYOUT_LINEAR,
                'node_positions' => $nodePositions,
            ],
        ];
    }

    /**
     * @param  array{x: int, y: int}  $position
     * @return array<string, mixed>
     */
    private static function buildNodeGridMetadata(array $position): array
    {
        return [
            self::METADATA_STANDARD_EDITOR => [
                'grid' => [
                    'x' => $position['x'],
                    'y' => $position['y'],
                ],
            ],
        ];
    }

    /**
     * @return array{from: string, to: string, is_default: true}
     */
    private static function defaultTransition(string $from, string $to): array
    {
        return [
            WorkflowDefinitionSchema::FIELD_FROM => $from,
            WorkflowDefinitionSchema::FIELD_TO => $to,
            WorkflowDefinitionSchema::FIELD_IS_DEFAULT => true,
        ];
    }
}
