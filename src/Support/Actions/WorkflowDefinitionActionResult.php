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

namespace DbflowLabs\Filament\Support\Actions;

use DbflowLabs\Core\Models\WorkflowVersion;

final class WorkflowDefinitionActionResult
{
    /**
     * @param  array<string, int|string>  $bodyReplacements
     */
    private function __construct(
        public readonly bool $success,
        public readonly string $titleKey,
        public readonly ?string $bodyKey = null,
        public readonly array $bodyReplacements = [],
        public readonly string $level = 'success',
        public readonly ?WorkflowVersion $publishedVersion = null,
    ) {}

    public static function success(string $titleKey, ?string $bodyKey = null, array $bodyReplacements = []): self
    {
        return new self(
            success: true,
            titleKey: $titleKey,
            bodyKey: $bodyKey,
            bodyReplacements: $bodyReplacements,
        );
    }

    public static function warning(string $titleKey, ?string $bodyKey = null, array $bodyReplacements = []): self
    {
        return new self(
            success: false,
            titleKey: $titleKey,
            bodyKey: $bodyKey,
            bodyReplacements: $bodyReplacements,
            level: 'warning',
        );
    }

    public static function danger(string $titleKey, ?string $bodyKey = null, array $bodyReplacements = []): self
    {
        return new self(
            success: false,
            titleKey: $titleKey,
            bodyKey: $bodyKey,
            bodyReplacements: $bodyReplacements,
            level: 'danger',
        );
    }

    public static function published(WorkflowVersion $version): self
    {
        return new self(
            success: true,
            titleKey: 'dbflow-filament::dbflow-filament.notifications.definitions.draft_published',
            bodyKey: 'dbflow-filament::dbflow-filament.notifications.definitions.published_version_number',
            bodyReplacements: ['version' => $version->version],
            publishedVersion: $version,
        );
    }
}
