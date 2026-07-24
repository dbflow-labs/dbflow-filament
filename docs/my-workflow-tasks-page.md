# My Workflow Tasks Page

Standard Filament page for listing pending workflow task assignments for the authenticated user.

## Purpose

- Show workflow tasks assigned to the current user.
- Filter to pending assignments with pending workflow tasks.
- Use package-safe contracts for permissions, labels, and status badges.

## Registration

Register through explicit host opt-in:

```php
use DbflowLabs\Filament\Support\DBFlowFilamentPanel;

return DBFlowFilamentPanel::register($panel->id('admin')->path('admin'));
```

The page class is exposed when `dbflow-filament.enable_my_tasks_page` is `true`.

Hosts may override the page class:

```php
'my_workflow_tasks_page_class' => App\DBFlow\Filament\Pages\MyWorkflowTasks::class,
```

## Config flags

| Key | Purpose |
| --- | --- |
| `enable_my_tasks_page` | Include page in `DBFlowFilamentPanel::pageClasses()` |
| `my_workflow_tasks_page_class` | Page class to register |
| `permissions.my_tasks` | Permission ability passed to `PermissionChecker` |
| `navigation_group` / `navigation_sort.my_tasks` | Navigation metadata |
| `route_prefix` | Slug prefix (`dbflow/my-workflow-tasks` by default) |

## Permission checker

`MyWorkflowTasks::canAccess()` requires `dbflow-filament.enabled` to be true.

When an authenticated user is present, `PermissionChecker::can($user, config('permissions.my_tasks'))` is evaluated.

When no user is present (for example during static navigation discovery), the default `PermissionChecker` is consulted with a null user so hosts can opt in without forcing an authenticated session at registration time.

Override `permission_checker_class` in config to integrate host authorization.

## Query behavior

The page calls `MyWorkflowTasksQuery::pendingForUser($userId)` from the page layer only.
The query service does not call `auth()` internally.

Unauthenticated users receive an empty query (`whereRaw('1 = 0')`).

## Translation keys

Examples:

- `dbflow-filament::dbflow-filament.pages.my_tasks.title`
- `dbflow-filament::dbflow-filament.pages.my_tasks.navigation_label`
- `dbflow-filament::dbflow-filament.tables.columns.workflow`

## Known limitations

- Host bridges may extend the page class for host-specific navigation and translations.
- No visual workflow builder, LogicFlow canvas, or Pro assets are included.

## Approve and reject actions

When `enable_my_task_actions` is `true` (default), the page exposes package-native **Approve** and **Reject** row actions via `MyWorkflowTaskTableActions`.

### Action runner

`DbflowLabs\Filament\Support\Actions\MyWorkflowTaskActionRunner` calls Core APIs directly:

- Approve: `DbflowLabs\Core\Actions\ApproveTask::handle($task, $actor, $comment)`
- Reject: `DbflowLabs\Core\Actions\RejectTask::handle($task, $actor, $comment, $rejectStrategy)`

The runner does not call `auth()`; the page/action layer passes the authenticated user.

Reject strategy defaults to `RejectStrategy::End` via `dbflow-filament.reject_strategy` (`end`).

### Permissions

Row action visibility uses `PermissionChecker`:

| Ability | Config key | Default |
| --- | --- | --- |
| Approve | `permissions.approve_task` | `dbflow.tasks.approve` |
| Reject | `permissions.reject_task` | `dbflow.tasks.reject` |

Assignment state checks (`pending` assignment + `pending` task for current assignee) are always applied.

### Notes and comments

- Approve: optional comment field in the confirmation modal.
- Reject: textarea for rejection reason; required when `require_reject_note` is `true` (default).

### Notifications

Success and failure notifications use `dbflow-filament::dbflow-filament.notifications.*` keys.

### Host bridge status

Reference hosts may keep a thin page subclass for navigation and i18n. Approve/reject actions are provided by the package page; legacy host action classes may remain as deprecated delegation shims for backward compatibility.

## Deferred

- `MyWorkflowTaskActionRunner` extraction.
- `MyWorkflowTaskTableActions` extraction.
