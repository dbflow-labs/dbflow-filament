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

namespace DbflowLabs\Filament\Support;

use DbflowLabs\Core\Definitions\WorkflowDefinitionSchema;
use DbflowLabs\Core\Support\WorkflowBuilderNodeFactory;

final class MinimalWorkflowDefinitionFactory
{
    /**
     * @return array<string, mixed>
     */
    public static function forMetadata(string $key, string $name, ?string $description = null): array
    {
        $factory = app(WorkflowBuilderNodeFactory::class);

        $nodes = [
            $factory->make(
                WorkflowDefinitionSchema::NODE_TYPE_START,
                'start',
                'Start',
                ['x' => 120, 'y' => 200],
            ),
            $factory->make(
                WorkflowDefinitionSchema::NODE_TYPE_END,
                'end',
                'End',
                ['x' => 400, 'y' => 200],
            ),
        ];

        $definition = [
            WorkflowDefinitionSchema::FIELD_KEY => $key,
            WorkflowDefinitionSchema::FIELD_NAME => $name,
            WorkflowDefinitionSchema::FIELD_SCHEMA_VERSION => '1.0',
            WorkflowDefinitionSchema::FIELD_NODES => $nodes,
            WorkflowDefinitionSchema::FIELD_TRANSITIONS => [
                [
                    WorkflowDefinitionSchema::FIELD_FROM => 'start',
                    WorkflowDefinitionSchema::FIELD_TO => 'end',
                    WorkflowDefinitionSchema::FIELD_IS_DEFAULT => true,
                ],
            ],
        ];

        if ($description !== null && $description !== '') {
            $definition[WorkflowDefinitionSchema::FIELD_DESCRIPTION] = $description;
        }

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public static function withDefaultApprovalStep(
        array $definition,
        string $approvalKey = 'approval',
        string $approvalName = 'Approval',
        string $assigneeType = WorkflowDefinitionSchema::ASSIGNEE_TYPE_USER,
        string $assigneeValue = '',
    ): array {
        $factory = app(WorkflowBuilderNodeFactory::class);
        $nodes = is_array($definition[WorkflowDefinitionSchema::FIELD_NODES] ?? null)
            ? $definition[WorkflowDefinitionSchema::FIELD_NODES]
            : [];

        $startNode = collect($nodes)->firstWhere('key', 'start');
        $endNode = collect($nodes)->firstWhere('key', 'end');

        if (! is_array($startNode) || ! is_array($endNode)) {
            return $definition;
        }

        $approvalNode = $factory->make(
            WorkflowDefinitionSchema::NODE_TYPE_APPROVAL,
            $approvalKey,
            $approvalName,
            ['x' => 260, 'y' => 200],
        );

        $approvalNode['config'][WorkflowDefinitionSchema::CONFIG_ASSIGNEES][WorkflowDefinitionSchema::ASSIGNEE_FIELD_TYPE] = $assigneeType;
        $approvalNode['config'][WorkflowDefinitionSchema::CONFIG_ASSIGNEES][WorkflowDefinitionSchema::ASSIGNEE_FIELD_VALUE] = $assigneeValue;

        $definition[WorkflowDefinitionSchema::FIELD_NODES] = [
            $startNode,
            $approvalNode,
            $endNode,
        ];
        $definition[WorkflowDefinitionSchema::FIELD_TRANSITIONS] = [
            [
                WorkflowDefinitionSchema::FIELD_FROM => 'start',
                WorkflowDefinitionSchema::FIELD_TO => $approvalKey,
                WorkflowDefinitionSchema::FIELD_IS_DEFAULT => true,
            ],
            [
                WorkflowDefinitionSchema::FIELD_FROM => $approvalKey,
                WorkflowDefinitionSchema::FIELD_TO => 'end',
                WorkflowDefinitionSchema::FIELD_IS_DEFAULT => true,
            ],
        ];

        return $definition;
    }
}
