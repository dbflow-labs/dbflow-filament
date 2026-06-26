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

use DbflowLabs\Filament\Contracts\StatusBadgeMapper;

final class DefaultStatusBadgeMapper implements StatusBadgeMapper
{
    public function labelFor(string $status): string
    {
        $normalized = strtolower(trim($status));

        $translationKey = 'dbflow-filament::dbflow-filament.statuses.'.$normalized;
        $translated = __($translationKey);

        if ($translated !== $translationKey) {
            return (string) $translated;
        }

        return $status;
    }

    public function colorFor(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending' => 'warning',
            'running' => 'info',
            'approved', 'completed' => 'success',
            'rejected', 'failed' => 'danger',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }
}
