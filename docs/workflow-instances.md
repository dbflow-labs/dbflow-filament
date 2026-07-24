# Workflow Instances Runtime UI

**Package:** `dbflowlabs/filament`  
**Stage:** 2.2E — read-only workflow instance list and timeline detail

---

## Purpose

The Workflow Instances pages provide **read-only runtime tracking** for DBFlow workflow executions:

- **Workflow Instances** — paginated Filament table of `WorkflowInstance` records with filters.
- **View Workflow Instance** — read-only detail view with instance summary, tasks, assignments, and workflow audit trail (timeline).

These pages do **not** include:

- Workflow definition CRUD (`WorkflowResource`)
- Approve/reject task actions (see [my-workflow-tasks-page.md](./my-workflow-tasks-page.md))
- Visual builder, LogicFlow, or Pro canvas assets (`dbflowlabs/filament-pro`)

---

## Registration

Register through the explicit panel registrar (recommended):

```php
use DbflowLabs\Filament\Support\DBFlowFilamentPanel;

DBFlowFilamentPanel::register($panel);
```

Host applications may substitute bridge page classes via config:

```php
'workflow_instances_page_class' => App\Host\Pages\WorkflowInstances::class,
'view_workflow_instance_page_class' => App\Host\Pages\ViewWorkflowInstance::class,
```

---

## Config Flags

| Key | Default | Description |
| --- | --- | --- |
| `enable_workflow_instances_page` | `true` | Registers list + detail pages |
| `enable_workflow_instance_resource` | `true` | Backward-compatible alias for instances toggle |
| `enable_logs_timeline` | `true` | Shows audit trail section on detail page |
| `workflow_instances_page_class` | `WorkflowInstances::class` | List page class |
| `view_workflow_instance_page_class` | `ViewWorkflowInstance::class` | Detail page class |
| `permissions.workflow_instances` | `view_workflow_instances` | Permission ability for access control |
| `navigation_sort.workflow_instances` | `20` | Navigation sort order |
| `route_prefix` | `dbflow` | URL prefix for package pages |

---

## Permission Checker

Both pages call `PermissionChecker::can($user, config('dbflow-filament.permissions.workflow_instances'))` inside `canAccess()`.

When no authenticated user is available (navigation evaluation), the checker receives `null` as the user argument.

---

## Query Behavior

`DbflowLabs\Filament\Support\Queries\WorkflowInstancesQuery::baseQuery()`:

- Model: `DbflowLabs\Core\Models\WorkflowInstance`
- Eager loads: `workflow`, `startedBy`
- Order: `started_at` DESC, then `id` DESC

The list page adds Filament filters for status, workflow definition, and started-at date range.

---

## Timeline Behavior

`DbflowLabs\Filament\Support\Presenters\WorkflowInstanceTimelinePresenter`:

- Reads `DbflowLabs\Core\Models\WorkflowLog` for the instance
- Orders chronologically by `created_at`, then `id`
- Hides `task_skipped` events
- Maps event names to `dbflow-filament::dbflow-filament.timeline.*` translations
- Resolves node labels through Core `WorkflowDefinitionDisplay`
- Resolves actor names through `UserDisplayResolver`
- Extracts comments from log `comment` column or payload keys (`comment`, `reason`, `rejection_reason`)

Detail page sections use:

- `WorkflowInstanceDetailPresenter` for task/assignment comment lookup
- `StatusBadgeMapper` for status labels
- `WorkflowableLabelResolver` for subject display

---

## Resolver Usage

| Surface | Resolver / Helper |
| --- | --- |
| Subject type / ID columns | `WorkflowableLabelResolver` |
| Subject summary on detail page | `WorkflowableLabelResolver::labelFor()` when workflowable model exists |
| Instance / task / assignment status | `StatusBadgeMapper` |
| Started-by / assignee / log actor | `UserDisplayResolver` |
| Workflow / node labels | Core `WorkflowDefinitionDisplay` |

---

## Views

| View | Path |
| --- | --- |
| List page | `dbflow-filament::pages.workflow-instances` |
| Detail page | `dbflow-filament::pages.view-workflow-instance` |
| Timeline table partial | `dbflow-filament::components.timeline` |

---

## Known Limitations

- Read-only: no instance cancellation, task approval, or definition editing from these pages.
- Workflowable subject labels depend on host `WorkflowableLabelResolver` implementation when models implement Core `Workflowable`.
- Full Filament browser rendering is not exercised in package Testbench tests; query/presenter/registrar behavior is covered directly.
- `WorkflowResource` (definition CRUD) remains a later extraction slice (F4).

---

## Host extension pattern

Hosts may extend package pages to add:

- Custom navigation registration and permission gating
- Host translation keys for titles and navigation labels
- Host Blade views for detail pages (optional)
- Localized timeline event labels via custom presenters

Runtime data access remains in the package layer.
