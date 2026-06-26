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

use DbflowLabs\Filament\Contracts\PermissionAssigneeOptionsResolver;

final class DefaultPermissionAssigneeOptionsResolver implements PermissionAssigneeOptionsResolver
{
    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function resolvedUserLabels(string $permissionKey): array
    {
        return [];
    }

    public function exists(string $permissionKey): bool
    {
        return false;
    }
}
