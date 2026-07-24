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

namespace DbflowLabs\Filament\Support\Presenters;

use DbflowLabs\Core\Enums\ActionExecutionMode;
use DbflowLabs\Core\Enums\ActionExecutionStatus;
use DbflowLabs\Core\Enums\AssignmentSource;
use DbflowLabs\Core\Enums\DelegationLifecycle;
use DbflowLabs\Core\Enums\SlaEventStatus;
use DbflowLabs\Core\Enums\SlaEventType;

final class RuntimeBadgePresenter
{
    public function assignmentSourceLabel(AssignmentSource|string|null $source): string
    {
        $value = $this->normalizeEnumValue($source) ?? AssignmentSource::Direct->value;

        $key = 'dbflow-filament::dbflow-filament.runtime.assignment_sources.'.$value;
        $translated = __($key);

        return $translated !== $key
            ? (string) $translated
            : (string) __('dbflow-filament::dbflow-filament.runtime.assignment_sources.unknown');
    }

    public function assignmentSourceColor(AssignmentSource|string|null $source): string
    {
        $value = $this->normalizeEnumValue($source) ?? AssignmentSource::Direct->value;

        return match ($value) {
            AssignmentSource::Direct->value => 'gray',
            AssignmentSource::Reassignment->value => 'warning',
            AssignmentSource::Delegation->value => 'info',
            AssignmentSource::Escalation->value => 'danger',
            default => 'gray',
        };
    }

    public function delegationLifecycleLabel(DelegationLifecycle|string|null $lifecycle): string
    {
        $value = $this->normalizeEnumValue($lifecycle) ?? 'unknown';
        $key = 'dbflow-filament::dbflow-filament.runtime.delegation_lifecycle.'.$value;
        $translated = __($key);

        return $translated !== $key
            ? (string) $translated
            : (string) __('dbflow-filament::dbflow-filament.runtime.delegation_lifecycle.unknown');
    }

    public function delegationLifecycleColor(DelegationLifecycle|string|null $lifecycle): string
    {
        $value = $this->normalizeEnumValue($lifecycle);

        return match ($value) {
            DelegationLifecycle::Scheduled->value => 'gray',
            DelegationLifecycle::Active->value => 'success',
            DelegationLifecycle::Expired->value => 'warning',
            DelegationLifecycle::Revoked->value => 'danger',
            default => 'gray',
        };
    }

    public function slaEventTypeLabel(SlaEventType|string|null $type): string
    {
        $value = $this->normalizeEnumValue($type) ?? 'unknown';
        $key = 'dbflow-filament::dbflow-filament.runtime.sla_event_types.'.$value;
        $translated = __($key);

        return $translated !== $key
            ? (string) $translated
            : (string) __('dbflow-filament::dbflow-filament.runtime.sla_event_types.unknown');
    }

    public function slaEventStatusLabel(SlaEventStatus|string|null $status): string
    {
        $value = $this->normalizeEnumValue($status) ?? 'unknown';
        $key = 'dbflow-filament::dbflow-filament.runtime.sla_event_statuses.'.$value;
        $translated = __($key);

        return $translated !== $key
            ? (string) $translated
            : (string) __('dbflow-filament::dbflow-filament.runtime.sla_event_statuses.unknown');
    }

    public function slaEventStatusColor(SlaEventStatus|string|null $status): string
    {
        $value = $this->normalizeEnumValue($status);

        return match ($value) {
            SlaEventStatus::Pending->value => 'gray',
            SlaEventStatus::Processing->value => 'info',
            SlaEventStatus::Completed->value => 'success',
            SlaEventStatus::Failed->value => 'danger',
            SlaEventStatus::Cancelled->value => 'warning',
            default => 'gray',
        };
    }

    public function actionExecutionStatusLabel(ActionExecutionStatus|string|null $status): string
    {
        $value = $this->normalizeEnumValue($status) ?? 'unknown';
        $key = 'dbflow-filament::dbflow-filament.runtime.action_execution_statuses.'.$value;
        $translated = __($key);

        return $translated !== $key
            ? (string) $translated
            : (string) __('dbflow-filament::dbflow-filament.runtime.action_execution_statuses.unknown');
    }

    public function actionExecutionStatusColor(ActionExecutionStatus|string|null $status): string
    {
        $value = $this->normalizeEnumValue($status);

        return match ($value) {
            ActionExecutionStatus::Queued->value => 'gray',
            ActionExecutionStatus::Running->value => 'info',
            ActionExecutionStatus::Succeeded->value => 'success',
            ActionExecutionStatus::Failed->value => 'warning',
            ActionExecutionStatus::Exhausted->value => 'danger',
            ActionExecutionStatus::Cancelled->value => 'warning',
            ActionExecutionStatus::Skipped->value => 'gray',
            default => 'gray',
        };
    }

    public function executionModeLabel(ActionExecutionMode|string|null $mode): string
    {
        $value = $this->normalizeEnumValue($mode) ?? 'unknown';
        $key = 'dbflow-filament::dbflow-filament.runtime.execution_modes.'.$value;
        $translated = __($key);

        return $translated !== $key
            ? (string) $translated
            : (string) __('dbflow-filament::dbflow-filament.runtime.execution_modes.unknown');
    }

    private function normalizeEnumValue(object|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_object($value) && property_exists($value, 'value')) {
            return (string) $value->value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
