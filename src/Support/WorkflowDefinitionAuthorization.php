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

use DbflowLabs\Filament\Contracts\PermissionChecker;
use Illuminate\Support\Facades\Auth;

final class WorkflowDefinitionAuthorization
{
    public static function checker(): PermissionChecker
    {
        return WorkflowFilamentPermissions::checker();
    }

    public static function ability(string $action): string
    {
        return WorkflowFilamentPermissions::ability('definitions', $action);
    }

    public static function can(string $action, mixed $record = null): bool
    {
        if (! (bool) config('dbflow-filament.enabled', true)) {
            return false;
        }

        return self::checker()->can(Auth::user(), self::ability($action), $record);
    }
}
