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

use DbflowLabs\Filament\Contracts\WorkflowDefinitionEditorResolver;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

final class WorkflowDefinitionEditorResolverManager
{
    private ?WorkflowDefinitionEditorResolver $runtimeResolver = null;

    public function __construct(
        private readonly Application $app,
    ) {}

    public function registerRuntimeResolver(?WorkflowDefinitionEditorResolver $resolver): void
    {
        $this->runtimeResolver = $resolver;
    }

    public function forgetRuntimeResolver(): void
    {
        $this->runtimeResolver = null;
    }

    /**
     * @param  array{
     *     record: \DbflowLabs\Core\Models\Workflow|null,
     *     operation: string,
     *     state_path: string,
     *     resource: class-string,
     * }  $context
     * @return list<Component>
     */
    public function resolve(array $context): array
    {
        $resolver = $this->runtimeResolver ?? $this->resolveConfiguredResolver();

        if ($resolver === null) {
            return [];
        }

        return $this->normalizeComponents($resolver->resolve($context));
    }

    private function resolveConfiguredResolver(): ?WorkflowDefinitionEditorResolver
    {
        $implementation = config('dbflow-filament.workflow_definition_editor_resolver');

        if (! is_string($implementation) || $implementation === '') {
            return null;
        }

        if (! class_exists($implementation)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid dbflow-filament.workflow_definition_editor_resolver configuration. Expected an existing class implementing %s.',
                WorkflowDefinitionEditorResolver::class,
            ));
        }

        $instance = $this->app->make($implementation);

        if (! $instance instanceof WorkflowDefinitionEditorResolver) {
            throw new InvalidArgumentException(sprintf(
                'Class [%s] must implement %s.',
                $implementation,
                WorkflowDefinitionEditorResolver::class,
            ));
        }

        return $instance;
    }

    /**
     * @param  list<Component>  $components
     * @return list<Component>
     */
    private function normalizeComponents(array $components): array
    {
        if ($components === []) {
            return [];
        }

        $normalized = [];

        foreach ($components as $index => $component) {
            if (! $component instanceof Component) {
                throw new InvalidArgumentException(sprintf(
                    'Workflow definition editor resolver must return Filament schema components. Invalid entry at index [%d].',
                    $index,
                ));
            }

            $normalized[] = $component;
        }

        return $normalized;
    }
}
