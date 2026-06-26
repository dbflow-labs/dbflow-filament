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
 * Branch target tokens used by the Standard workflow form editor.
 */
final class StandardWorkflowBranchTargets
{
    public const NEXT = 'next';

    public const END = 'end';

    /** @deprecated Use {@see END_OUTCOME} with an explicit end key. */
    public const END_OUTCOME = 'end_outcome';

    public const STEP = 'step';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::NEXT,
            self::END_OUTCOME,
            self::END,
            self::STEP,
        ];
    }
}
