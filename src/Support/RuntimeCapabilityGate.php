<?php

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
