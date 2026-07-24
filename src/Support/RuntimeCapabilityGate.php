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

use DbflowLabs\Core\Capabilities\RuntimeCapabilityRegistry;
use DbflowLabs\Core\Enums\RuntimeCapability;

final class RuntimeCapabilityGate
{
    public function __construct(
        private readonly RuntimeCapabilityRegistry $registry,
    ) {}

    public function has(RuntimeCapability $capability): bool
    {
        return $this->registry->has($capability);
    }

    public function delegationVisible(): bool
    {
        return $this->has(RuntimeCapability::Delegation);
    }

    public function slaVisible(): bool
    {
        return $this->has(RuntimeCapability::Sla);
    }

    public function reliableActionVisible(): bool
    {
        return $this->has(RuntimeCapability::ReliableAction);
    }

    public function outboundWebhookVisible(): bool
    {
        return $this->has(RuntimeCapability::OutboundWebhook);
    }
}
