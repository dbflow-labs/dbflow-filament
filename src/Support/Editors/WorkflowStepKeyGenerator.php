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

final class WorkflowStepKeyGenerator
{
    /**
     * Generate a unique node key from a human-readable label.
     *
     * Mirrors the slug strategy used by dbflow-filament-pro canvas palette.
     *
     * @param  list<string>  $existingKeys
     */
    public static function fromLabel(string $label, string $type, array $existingKeys): string
    {
        $reserved = array_fill_keys($existingKeys, true);

        $slug = strtolower($label);
        $slug = (string) preg_replace('/[\s\-]+/', '_', $slug);
        $slug = (string) preg_replace('/[^a-z0-9_]/', '', $slug);
        $slug = ltrim($slug, '0123456789');
        $slug = rtrim($slug, '_');
        $slug = substr($slug, 0, 48);

        if ($slug === '') {
            $slug = $type.'_'.random_int(1000, 9999);
        }

        if (! isset($reserved[$slug])) {
            return $slug;
        }

        $counter = 2;

        while (isset($reserved[$slug.'_'.$counter])) {
            $counter++;
        }

        return $slug.'_'.$counter;
    }
}
