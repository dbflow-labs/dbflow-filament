<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Unit;

use DbflowLabs\Core\Enums\ApprovalMode;
use DbflowLabs\Core\Enums\WorkflowTaskStatus;
use DbflowLabs\Core\Models\WorkflowTask;
use DbflowLabs\Filament\Support\Presenters\DueDatePresenter;
use DbflowLabs\Filament\Tests\TestCase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;

final class DueDatePresenterTest extends TestCase
{
    private DueDatePresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->presenter = new DueDatePresenter;
        app()->setLocale('en');
    }

    #[Test]
    public function remaining_label_returns_null_when_task_has_no_due_date(): void
    {
        $task = $this->makeTask();

        $this->assertNull($this->presenter->remainingLabel($task));
    }

    #[Test]
    public function remaining_label_shows_overdue_when_task_is_marked_overdue(): void
    {
        $task = $this->makeTask(
            dueAt: Carbon::parse('2026-07-23 12:00:00', 'UTC'),
            overdueAt: Carbon::parse('2026-07-23 13:00:00', 'UTC'),
        );

        $this->assertSame(
            'Overdue',
            $this->presenter->remainingLabel($task, Carbon::parse('2026-07-24 12:00:00', 'UTC')),
        );
    }

    #[Test]
    public function remaining_label_shows_hours_when_deadline_is_within_two_days(): void
    {
        $task = $this->makeTask(
            dueAt: Carbon::parse('2026-07-24 18:00:00', 'UTC'),
        );

        $this->assertSame(
            '6 hours remaining',
            $this->presenter->remainingLabel($task, Carbon::parse('2026-07-24 12:00:00', 'UTC')),
        );
    }

    #[Test]
    public function remaining_label_shows_days_when_deadline_is_farther_out(): void
    {
        $task = $this->makeTask(
            dueAt: Carbon::parse('2026-07-27 12:00:00', 'UTC'),
        );

        $this->assertSame(
            '3 days remaining',
            $this->presenter->remainingLabel($task, Carbon::parse('2026-07-24 12:00:00', 'UTC')),
        );
    }

    #[Test]
    public function due_date_column_label_includes_remaining_time(): void
    {
        $task = $this->makeTask(
            dueAt: Carbon::parse('2026-07-24 18:00:00', 'UTC'),
        );

        $label = $this->presenter->dueDateColumnLabel(
            $task,
            Carbon::parse('2026-07-24 12:00:00', 'UTC'),
        );

        $this->assertStringContainsString('2026-07-24 18:00:00', $label);
        $this->assertStringContainsString('6 hours remaining', $label);
    }

    #[Test]
    public function remaining_label_returns_null_for_completed_tasks(): void
    {
        $task = $this->makeTask(
            dueAt: Carbon::parse('2026-07-27 12:00:00', 'UTC'),
            status: WorkflowTaskStatus::Approved,
        );

        $this->assertNull(
            $this->presenter->remainingLabel($task, Carbon::parse('2026-07-24 12:00:00', 'UTC')),
        );
    }

    #[Test]
    public function remaining_label_shows_overdue_when_due_at_has_passed(): void
    {
        $task = $this->makeTask(
            dueAt: Carbon::parse('2026-07-23 12:00:00', 'UTC'),
        );

        $this->assertSame(
            'Overdue',
            $this->presenter->remainingLabel($task, Carbon::parse('2026-07-24 12:00:00', 'UTC')),
        );
        $this->assertTrue(
            $this->presenter->isOverdue($task, Carbon::parse('2026-07-24 12:00:00', 'UTC')),
        );
    }

    private function makeTask(
        ?Carbon $dueAt = null,
        ?Carbon $overdueAt = null,
        WorkflowTaskStatus $status = WorkflowTaskStatus::Pending,
    ): WorkflowTask {
        return new WorkflowTask([
            'node_key' => 'review',
            'node_name' => 'Review',
            'status' => $status,
            'approval_mode' => ApprovalMode::Any,
            'due_at' => $dueAt,
            'overdue_at' => $overdueAt,
        ]);
    }
}
