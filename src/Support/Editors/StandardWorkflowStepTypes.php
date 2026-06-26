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
 * Builder block identifiers for the Standard workflow definition editor.
 */
final class StandardWorkflowStepTypes
{
    public const APPROVAL = 'approval';

    public const CONDITION = 'condition';

    public const ACTION = 'action';

    /**
     * @return list<string>
     */
    public static function configurable(): array
    {
        return [
            self::APPROVAL,
            self::CONDITION,
            self::ACTION,
        ];
    }
}
