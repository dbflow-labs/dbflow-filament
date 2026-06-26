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
 * @deprecated Use {@see StandardWorkflowDefinitionEditor} instead.
 */
final class StandardLinearApprovalDefinitionEditor
{
    /**
     * @return list<\Filament\Schemas\Components\Component>
     */
    public static function formComponents(?\DbflowLabs\Core\Models\Workflow $record): array
    {
        return StandardWorkflowDefinitionEditor::formComponents($record);
    }
}
