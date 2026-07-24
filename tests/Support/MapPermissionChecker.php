<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Support;

use DbflowLabs\Filament\Contracts\PermissionChecker;

final class MapPermissionChecker implements PermissionChecker
{
    /**
     * @param  array<string, bool>  $abilities
     */
    public function __construct(
        private readonly array $abilities,
        private readonly bool $default = false,
    ) {}

    public function can(mixed $user, string $ability, mixed $record = null): bool
    {
        return $this->abilities[$ability] ?? $this->default;
    }
}
