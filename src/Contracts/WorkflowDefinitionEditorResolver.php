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

namespace DbflowLabs\Filament\Contracts;

use Filament\Schemas\Components\Component;

interface WorkflowDefinitionEditorResolver
{
    /**
     * @param  array{
     *     record: \DbflowLabs\Core\Models\Workflow|null,
     *     operation: string,
     *     state_path: string,
     *     resource: class-string,
     * }  $context
     * @return list<Component>
     */
    public function resolve(array $context): array;
}
