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

use DbflowLabs\Core\Models\WorkflowTask;
use Illuminate\Support\Carbon;

final class DueDatePresenter
{
    public function formatDateTime(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (! $value instanceof \DateTimeInterface) {
            return '—';
        }

        $configured = config('dbflow-filament.display_timezone');
        $timezone = is_string($configured) && $configured !== ''
            ? $configured
            : (string) config('app.timezone', 'UTC');
        $format = (string) config('dbflow-filament.date_time_format', 'Y-m-d H:i:s');

        return Carbon::instance(\DateTimeImmutable::createFromInterface($value))
            ->utc()
            ->timezone($timezone !== '' ? $timezone : 'UTC')
            ->format($format);
    }

    public function isOverdue(WorkflowTask $task, ?Carbon $asOf = null): bool
    {
        if ($task->overdue_at !== null) {
            return true;
        }

        if ($task->due_at === null || $this->isTerminal($task)) {
            return false;
        }

        $asOf ??= Carbon::now('UTC');

        return $task->due_at->lt($asOf);
    }

    public function overdueLabel(WorkflowTask $task): ?string
    {
        if (! $this->isOverdue($task)) {
            return null;
        }

        return (string) __('dbflow-filament::dbflow-filament.runtime.overdue');
    }

    public function dueSoonThresholdHours(): int
    {
        $hours = config('dbflow-filament.due_soon_hours', 24);

        return is_numeric($hours) ? max(1, (int) $hours) : 24;
    }

    public function isDueSoon(WorkflowTask $task, ?Carbon $asOf = null): bool
    {
        if ($task->due_at === null || $this->isOverdue($task)) {
            return false;
        }

        $asOf ??= Carbon::now('UTC');

        return $task->due_at->gte($asOf)
            && $task->due_at->lte($asOf->copy()->addHours($this->dueSoonThresholdHours()));
    }

    public function remainingSeconds(WorkflowTask $task, ?Carbon $asOf = null): ?int
    {
        if ($task->due_at === null || $this->isTerminal($task)) {
            return null;
        }

        $asOf ??= Carbon::now('UTC');

        return max(0, $task->due_at->getTimestamp() - $asOf->getTimestamp());
    }

    public function remainingLabel(WorkflowTask $task, ?Carbon $asOf = null): ?string
    {
        if ($task->due_at === null || $this->isTerminal($task)) {
            return null;
        }

        if ($this->isOverdue($task, $asOf)) {
            return (string) __('dbflow-filament::dbflow-filament.runtime.remaining_overdue');
        }

        $seconds = $this->remainingSeconds($task, $asOf);

        if ($seconds === null || $seconds <= 0) {
            return (string) __('dbflow-filament::dbflow-filament.runtime.remaining_overdue');
        }

        $minutes = (int) ceil($seconds / 60);

        if ($minutes < 60) {
            return (string) trans_choice(
                'dbflow-filament::dbflow-filament.runtime.remaining.minutes',
                $minutes,
                ['count' => $minutes],
            );
        }

        $hours = (int) ceil($seconds / 3600);

        if ($hours < 48) {
            return (string) trans_choice(
                'dbflow-filament::dbflow-filament.runtime.remaining.hours',
                $hours,
                ['count' => $hours],
            );
        }

        $days = (int) ceil($seconds / 86400);

        return (string) trans_choice(
            'dbflow-filament::dbflow-filament.runtime.remaining.days',
            $days,
            ['count' => $days],
        );
    }

    public function dueDateColumnLabel(WorkflowTask $task, ?Carbon $asOf = null): string
    {
        $formatted = $this->formatDateTime($task->due_at);

        if ($formatted === '—') {
            return '—';
        }

        $remaining = $this->remainingLabel($task, $asOf);

        if ($remaining === null || $remaining === '') {
            return $formatted;
        }

        return $formatted.' · '.$remaining;
    }

    private function isTerminal(WorkflowTask $task): bool
    {
        $status = $task->status;

        if ($status instanceof \DbflowLabs\Core\Enums\WorkflowTaskStatus) {
            return $status->isTerminal();
        }

        return false;
    }
}
