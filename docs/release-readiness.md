# Release Readiness

**Package:** `dbflowlabs/filament`  
**Distribution:** Packagist (MIT)  
**Author:** Baron Wang <hello@dbflow.dev>

## Dependency constraints

| Package | Constraint | Notes |
| --- | --- | --- |
| `dbflowlabs/core` | `^1.1` | Resolved from Packagist; no `@dev` in release `composer.json` |
| `filament/filament` | `^5.6` | Filament 5 standard UI API |
| `php` | `^8.3` | Matches DBFlow Core baseline |

The package `composer.json` must **not** declare path repositories. Local monorepo development uses path repositories in the **consumer** root `composer.json` only.

Production deployments target **MySQL 8.0+**. SQLite remains supported for local development and package tests.

## Release-check behavior

Release gates live in [`.github/workflows/tests.yml`](../.github/workflows/tests.yml) (not versioned under `scripts/`). Before tagging, mirror the workflow locally:

1. `composer validate --strict`
2. `composer cs-check`
3. Boundary scans (host leakage, stale namespaces, forbidden brands, Pro/canvas runtime terms, credentials, non-English characters in English paths)
4. `composer test`

Requirements:

- `ripgrep` (`rg`)
- PHP 8.3+ with `pdo_sqlite` for Testbench migrations

Architecture `rg` gates scan package release paths (`src/`, `config/`, `lang/en/`, `resources/`, root metadata). They intentionally exclude `tests/` because boundary assertion strings would self-match; `PackageBoundaryTest` in `composer test` covers test-tree leakage.

CI runs the same steps on Ubuntu for PHP 8.3 and 8.4.

## Permission ability map

Configured under `dbflow-filament.permissions`:

| Group | Ability key | Default string |
| --- | --- | --- |
| tasks | `view` | `dbflow.tasks.view` |
| tasks | `approve` | `dbflow.tasks.approve` |
| tasks | `reject` | `dbflow.tasks.reject` |
| tasks | `reassign` | `dbflow.tasks.reassign` |
| workflow_instances | `view` | `dbflow.workflow_instances.view` |
| workflow_instances | `view_any` | `dbflow.workflow_instances.view_any` |
| workflow_instances | `cancel` | `dbflow.workflow_instances.cancel` |
| definitions | `view` | `dbflow.definitions.view` |
| definitions | `create` | `dbflow.definitions.create` |
| definitions | `update` | `dbflow.definitions.update` |
| definitions | `delete` | `dbflow.definitions.delete` |
| definitions | `validate` | `dbflow.definitions.validate` |
| definitions | `publish` | `dbflow.definitions.publish` |
| definitions | `disable` | `dbflow.definitions.disable` |
| definitions | `enable` | `dbflow.definitions.enable` |
| definitions | `archive` | `dbflow.definitions.archive` |
| definitions | `copy` | `dbflow.definitions.copy` |
| delegations | `view_any` | `dbflow.delegations.view_any` |
| sla_events | `view` | `dbflow.sla_events.view` |
| action_executions | `view_any` | `dbflow.action_executions.view_any` |
| action_executions | `view` | `dbflow.action_executions.view` |
| action_attempts | `view` | `dbflow.action_attempts.view` |
| webhook_metadata | `view` | `dbflow.webhook_metadata.view` |

Legacy flat keys still supported:

- `my_tasks` → tasks.view
- `approve_task` → tasks.approve
- `reject_task` → tasks.reject
- `reassign_task` → tasks.reassign
- `cancel_workflow_instance` → workflow_instances.cancel
- string `workflow_instances` config value → workflow_instances.view_any

Hosts override abilities through nested config keys or bind a custom `PermissionChecker` via `permission_checker_class`.

Task inbox access (`dbflow.tasks.view`) does not grant v1.1 operational visibility surfaces. See [UPGRADE-1.1.md](../UPGRADE-1.1.md).

## Consumer smoke verification

After installation in a clean Laravel 13 + Filament 5 application:

1. `composer require dbflowlabs/filament:^1.1 dbflowlabs/core:^1.1`
2. `composer require filament/filament:^5.6` (if not already present)
3. `php artisan migrate`
4. Publish Core and Filament config; set `DBFLOW_AUTH_MODEL`
5. Register `DBFlowFilamentPanel::register($panel)` in a Filament `PanelProvider`
6. Verify My Workflow Tasks, Workflow Instances, Workflow Definitions, and (when enabled) Delegations and Action Executions surfaces load

Planned release tag: **1.1.0**. Upgrade notes: [UPGRADE-1.1.md](../UPGRADE-1.1.md). Release notes: [RELEASE-NOTES-1.1.0.md](../RELEASE-NOTES-1.1.0.md).
