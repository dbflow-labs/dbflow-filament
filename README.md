# DBFlow Filament

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dbflowlabs/filament.svg)](https://packagist.org/packages/dbflowlabs/filament)
[![Total Downloads](https://img.shields.io/packagist/dt/dbflowlabs/filament.svg)](https://packagist.org/packages/dbflowlabs/filament)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-8.3%2B-777bb4.svg)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-13.x-ff2d20.svg)](composer.json)
[![Filament](https://img.shields.io/badge/filament-5.x-f59e0b.svg)](composer.json)

**Standard Filament integration for DBFlow Core.**

DBFlow Filament adds workflow tasks, workflow instances, audit timelines, and form-based workflow definition management to Laravel admin panels built with [Filament](https://filamentphp.com/).

This package is the **standard UI layer** for [`dbflowlabs/core`](https://github.com/dbflow-labs/dbflow-core). It intentionally ships read-only runtime surfaces and form-based definition editing. Visual workflow builders, drag-and-drop canvases, and advanced authoring experiences live in the separate commercial `dbflowlabs/filament-pro` package.

> [!IMPORTANT]
> **Filament UI is only half of an integration.** You also need Core runtime setup: migrations, user resolution, workflow definitions (code or UI), assignee resolvers, and host business triggers (`DBFlow::start()`). See [End-to-end integration checklist](#end-to-end-integration-checklist) and the [Core README](https://github.com/dbflow-labs/dbflow-core/blob/main/README.md).

> [!WARNING]
> **Replace the default `AllowAllPermissionChecker` before any shared environment.** Until you set `permission_checker_class` to a host implementation, every authenticated user can access workflow pages and approve/reject tasks.

## Contents

- [Package overview](#package-overview)
- [What you get](#what-you-get)
- [Requirements](#requirements)
- [Installation](#installation)
- [Core prerequisites](#core-prerequisites)
- [Register with Filament](#register-with-filament)
- [Configuration](#configuration)
- [Extension contracts](#extension-contracts)
- [End-to-end integration checklist](#end-to-end-integration-checklist)
- [Troubleshooting](#troubleshooting)
- [Typical integration flow](#typical-integration-flow)
- [Release candidate scope](#release-candidate-scope)
- [Development](#development)
- [Support](#support)

## Package overview

| Item            | Value                                                                                |
| --------------- | ------------------------------------------------------------------------------------ |
| Composer name   | `dbflowlabs/filament`                                                                |
| Namespace       | `DbflowLabs\Filament`                                                                |
| First release   | `0.1.0-alpha.1`                                                                      |
| License         | MIT                                                                                  |
| Author          | Baron Wang [hello@dbflow.dev](mailto:hello@dbflow.dev)                               |
| Core dependency | [dbflowlabs/core](https://packagist.org/packages/dbflowlabs/core) `^1.0.0-rc.1` |
| Filament        | `^5.6`                                                                               |
| PHP             | `^8.3`                                                                               |
| Host framework  | Laravel 13.x                                                                         |

## What you get

| Surface                      | Description                                                                          |
| ---------------------------- | ------------------------------------------------------------------------------------ |
| **My Workflow Tasks**        | Pending assignments with approve, reject, reassign; subject links via `WorkflowRouteResolvable` |
| **Workflow Instances**       | Searchable runtime workflow instance list                                            |
| **Workflow Instance Detail** | Read-only workflow instance detail page with audit timeline                          |
| **Workflow Definitions**     | Filament resource for draft CRUD, validation, and publishing                         |
| **Extension Contracts**      | Host adapters for permissions, labels, status badges, users, and assignee options    |

Hosts opt in explicitly. This package does **not** auto-register Filament pages or resources during `boot()`. You must call `DBFlowFilamentPanel::register($panel)` (or the manual equivalents) from your `PanelProvider`.

## Requirements

- PHP `^8.3`
- Laravel 13.x
- Filament `^5.6`
- [`dbflowlabs/core`](https://packagist.org/packages/dbflowlabs/core) `^1.0.0-rc.1`
- Core database migrations applied (`php artisan migrate`)
- Host user model configured in Core (`DBFLOW_AUTH_MODEL`, see [Core prerequisites](#core-prerequisites))

## Installation

### Packagist (release candidate)

If your application uses `minimum-stability: stable` (the Laravel default), pin RC packages with an explicit stability flag:

```bash
composer require "dbflowlabs/filament:1.0.0-rc.2@RC" "dbflowlabs/core:1.0.0-rc.1@RC"
```

If your project already allows prerelease stability, you may use:

```bash
composer require "dbflowlabs/filament:1.0.0-rc.2"
```

`dbflowlabs/core` is installed automatically as a dependency of this package.

For ongoing RC updates after the first pin:

```bash
composer require "dbflowlabs/filament:^1.0.0-rc.2@RC"
```

To pin Core explicitly:

```bash
composer require "dbflowlabs/core:1.0.0-rc.1@RC"
```

After installation, confirm that `composer.lock` records the expected RC version and resolved commit hash.

### Publish assets

Publish the package configuration:

```bash
php artisan vendor:publish --tag=dbflow-filament-config
```

Publish views if you want to customize the UI:

```bash
php artisan vendor:publish --tag=dbflow-filament-views
```

Optional translations (the package also ships translations under `lang/` and loads them automatically):

```bash
php artisan vendor:publish --tag=dbflow-filament-translations
```

## Core prerequisites

Filament surfaces read and mutate data through Core. Complete these steps **before** expecting the UI to work.

### Migrations

Core loads migrations automatically via `DBFlowServiceProvider`. In most host applications you only need:

```bash
php artisan migrate
```

Publishing Core migrations (`vendor:publish --tag=dbflow-migrations`) is **optional** and only needed when you want migration files copied into `database/migrations/`.

### Core configuration

Publish Core config when you need to customize auth or runtime flags:

```bash
php artisan vendor:publish --tag=dbflow-config
```

Set the host user model explicitly in `.env`:

```env
DBFLOW_AUTH_MODEL=App\Models\User
DBFLOW_AUTH_GUARD=web
DBFLOW_AUTH_TABLE=users
```

Core stores workflow user references (`assignee_user_id`, `started_by_user_id`, `actor_user_id`) as strings. Integer primary keys still work (stored as `"1"`); UUID/ULID primary keys are supported in Core 0.3+.

See the [dbflowlabs/core README](https://github.com/dbflow-labs/dbflow-core/blob/main/README.md) for `binding_mode`, hooks, and runtime APIs.

### Two configuration files

| Config file | Primary switch | What it controls |
| ----------- | -------------- | ---------------- |
| `config/dbflow.php` | `enabled` | Core runtime (`DBFLOW_ENABLED=false` disables runtime APIs; definition sync/validate remain available — see Core README) |
| `config/dbflow-filament.php` | `enabled` | Whether Filament pages/resources are exposed when registered |

`DBFlowFilamentPanel` checks **`dbflow-filament.enabled`** and **`panel_registration_mode`**. It does **not** read `dbflow.enabled`. Hosts that want a single feature flag should wrap both configs in their own toggle.

### Workflow definitions (code-first)

Filament does not seed workflows. For code-first definitions:

1. Register a `WorkflowDefinitionProvider` in a host service provider (see Core README).
2. Register any `AssigneeResolver` keys referenced by approval nodes.
3. Sync definitions into the database:

```bash
php artisan dbflow:sync
php artisan dbflow:validate --strict
```

Or programmatically:

```php
use DbflowLabs\Core\Actions\SyncWorkflowDefinitions;

app(SyncWorkflowDefinitions::class)->handle();
```

When using the **UI definition editor**, approval steps may optionally set `timeout.due_in` (ISO 8601 duration) and `on_timeout` (`reject_end` for auto-reject). Schedule `php artisan dbflow:process-timeouts` when using deadlines (see Core README).

Core ships official `dbflow:sync` (`--dry-run`, `--workflow=`) and `dbflow:validate` (`--strict`, `--workflow=`, `--source=`) commands. Filament does not bundle its own sync command.

> [!NOTE]
> **Code sync vs UI-authored definitions:** When a workflow is owned by the Filament definition resource (`source = ui`), code sync stores new versions as history but does not replace the UI workflow's active version pointer. Hosts using code-first pilots often disable `enable_workflow_definition_resource` or treat the resource as read-only ops tooling. See [docs/workflow-definitions.md](docs/workflow-definitions.md).

### Assignee configuration (Core runtime)

Approval nodes may reference assignees by type:

| `assignees.type` | Runtime behaviour |
| ---------------- | ----------------- |
| `user` | Single user id in `value` |
| `permission` | **`value` is an `AssigneeResolverRegistry` key**, not a host RBAC permission string |
| `callback` | Same as `permission`: `value` / `callback` is a resolver registry key |

Register resolvers during boot:

```php
DBFlow::registerAssigneeResolver(
    app(AssigneeResolverRegistry::class),
    'finance_team',
    $myAssigneeResolver,
);
```

The Filament `PermissionAssigneeOptionsResolver` contract is **UI-only**: it populates labels in the definition editor when authors pick permission-style assignees. It does not resolve runtime assignees. Keep resolver keys in sync between Core registration and Filament `options()` / `exists()`.

### Host model contracts (Core)

For links from tasks/instances back to business records, implement on your Eloquent models (see Core):

- `DbflowLabs\Core\Traits\HasWorkflow` — `startWorkflow()`, instance helpers
- `DbflowLabs\Core\Contracts\WorkflowRouteResolvable` — `getWorkflowShowUrl()`
- `DbflowLabs\Core\Contracts\Workflowable` — display metadata

## Register with Filament

Register DBFlow Filament from your Filament `PanelProvider`:

```php
<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use DbflowLabs\Filament\Support\DBFlowFilamentPanel;
use Filament\Panel;
use Filament\PanelProvider;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('admin')
            ->path('admin') // or '' when the panel is mounted at the site root
            // your pages, resources, middleware, branding...
        ;

        if ($this->shouldRegisterDbflow()) {
            return DBFlowFilamentPanel::register($panel);
        }

        return $panel;
    }

    private function shouldRegisterDbflow(): bool
    {
        return (bool) config('dbflow-filament.enabled', true);
        // Hosts often also gate on config('dbflow.enabled') or a product feature flag.
    }
}
```

When the corresponding feature toggles are enabled, registration exposes:

| Surface                  | Class                                            |
| ------------------------ | ------------------------------------------------ |
| My Workflow Tasks        | `DbflowLabs\Filament\Pages\MyWorkflowTasks`      |
| Workflow Instances       | `DbflowLabs\Filament\Pages\WorkflowInstances`    |
| Workflow Instance Detail | `DbflowLabs\Filament\Pages\ViewWorkflowInstance` |
| Workflow Definitions     | `DbflowLabs\Filament\Resources\WorkflowResource` |

### Routes and panel path

Package pages use slugs shaped like:

```text
{panel_path}/{route_prefix}/my-workflow-tasks
{panel_path}/{route_prefix}/workflow-instances
{panel_path}/{route_prefix}/workflow-instances/{record}
```

Defaults: `route_prefix = dbflow`. Examples:

- Panel path `admin` → `/admin/dbflow/my-workflow-tasks`
- Panel path `` (empty) → `/dbflow/my-workflow-tasks`

Named routes use `route_name_prefix` (default `dbflow.filament.`).

### Manual registration

If you prefer manual registration, use the helper methods:

```php
use DbflowLabs\Filament\Support\DBFlowFilamentPanel;

$panel->pages([
    ...$panel->getPages(),
    ...DBFlowFilamentPanel::pageClasses(),
]);

$panel->resources([
    ...$panel->getResources(),
    ...DBFlowFilamentPanel::resourceClasses(),
]);
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=dbflow-filament-config
```

Then edit `config/dbflow-filament.php`.

### Feature toggles

| Key | Env (optional) | Default | Purpose |
| --- | -------------- | ------- | ------- |
| `enabled` | `DBFLOW_FILAMENT_ENABLED` | `true` | Master Filament package toggle |
| `panel_registration_mode` | `DBFLOW_FILAMENT_PANEL_REGISTRATION` | `explicit` | `explicit` (register via `DBFlowFilamentPanel`) or `disabled` |
| `enable_my_tasks_page` | `DBFLOW_FILAMENT_MY_TASKS` | `true` | My Workflow Tasks page |
| `enable_my_task_actions` | `DBFLOW_FILAMENT_MY_TASK_ACTIONS` | `true` | Approve/reject actions on the tasks page |
| `enable_workflow_instances_page` | `DBFLOW_FILAMENT_INSTANCES` | `true` | Instance list and detail pages |
| `enable_workflow_definition_resource` | `DBFLOW_FILAMENT_DEFINITIONS` | `true` | Workflow Definition resource |
| `enable_logs_timeline` | `DBFLOW_FILAMENT_LOGS_TIMELINE` | `true` | Audit timeline on instance detail |
| `require_reject_note` | `DBFLOW_FILAMENT_REQUIRE_REJECT_NOTE` | `true` | Require a note when rejecting from the tasks UI |
| `reject_strategy` | `DBFLOW_FILAMENT_REJECT_STRATEGY` | `end` | Core `RejectTask` strategy (see below) |

`reject_strategy` accepts Core `RejectStrategy` values:

| Value | Meaning |
| ----- | ------- |
| `end` | Terminate the workflow (default from tasks UI) |
| `starter` | Return toward the submitter |
| `previous_node` | Return to the previous approval node |
| `specific_node` | Jump to a configured node key |

### Navigation and routing

| Key | Env (optional) | Default | Purpose |
| --- | -------------- | ------- | ------- |
| `navigation_group` | `DBFLOW_FILAMENT_NAV_GROUP` | `Workflow` | Filament navigation group label |
| `navigation_sort` | — | see config | Sort order for tasks / instances / definitions |
| `should_register_navigation` | — | `null` | Optional `callable($user): bool` to gate all nav items |
| `route_prefix` | — | `dbflow` | URL slug prefix for package pages |
| `route_name_prefix` | — | `dbflow.filament.` | Named route prefix |
| `middleware` | — | `[]` | Extra middleware for package pages |

### Class overrides

| Key | Purpose |
| --- | ------- |
| `my_workflow_tasks_page_class` | Subclass `MyWorkflowTasks` |
| `workflow_instances_page_class` | Subclass `WorkflowInstances` |
| `view_workflow_instance_page_class` | Subclass `ViewWorkflowInstance` |
| `workflow_resource_class` | Subclass `WorkflowResource` |
| `workflow_definition_editor_resolver` | Replace the standard form editor (`WorkflowDefinitionEditorResolver`) |

### Internationalization

- Package strings live under `lang/vendor/dbflow-filament` when published, or in the package `lang/` directory by default.
- `navigation_group` falls back to `__('dbflow-filament::dbflow-filament.navigation.group')` when empty.
- Hosts targeting non-English panels should set `DBFLOW_FILAMENT_NAV_GROUP` or publish and override translations.

### Legacy permission config keys

Nested abilities under `permissions.tasks`, `permissions.workflow_instances`, and `permissions.definitions` are canonical. Flat keys (`my_tasks`, `approve_task`, `reject_task`, …) remain for backward compatibility. If you rename abilities in config, your `PermissionChecker` receives the **resolved** strings from `WorkflowFilamentPermissions::ability()`.

Prefer `*_class` contract bindings over legacy callables (`permission_checker`, `workflowable_label_resolver`, `status_badge_mapper`) in `config/dbflow-filament.php`.

## Extension contracts

Production applications should replace the default support implementations with adapters wired to the host application's auth, user, and domain model systems.

| Contract | Config key | Role |
| -------- | ---------- | ---- |
| `PermissionChecker` | `permission_checker_class` | Ability checks for pages, resources, and task actions |
| `UserDisplayResolver` | `user_display_resolver_class` | Display names for timeline actors and assignees |
| `WorkflowableLabelResolver` | `workflowable_label_resolver_class` | Subject labels in instance lists and detail pages |
| `StatusBadgeMapper` | `status_badge_mapper_class` | Filament badge labels and colors (`labelFor` / `colorFor`) |
| `UserAssigneeOptionsResolver` | `user_assignee_options_resolver_class` | User picker options in definition editors |
| `PermissionAssigneeOptionsResolver` | `permission_assignee_options_resolver_class` | Labels for permission-style assignee keys in the definition editor |
| `WorkflowDefinitionEditorResolver` | `workflow_definition_editor_resolver` | Replace the standard linear approval form editor |

Default `permission_checker_class` is `DbflowLabs\Filament\Support\AllowAllPermissionChecker` (**allows everything**).

### Permission abilities

Ability strings are configured under `permissions` in `config/dbflow-filament.php`.

Default ability names use the `dbflow.*` namespace:

```text
dbflow.tasks.view
dbflow.tasks.approve
dbflow.tasks.reject
dbflow.workflow_instances.view
dbflow.workflow_instances.view_any
dbflow.definitions.view
dbflow.definitions.create
dbflow.definitions.update
dbflow.definitions.delete
dbflow.definitions.validate
dbflow.definitions.publish
dbflow.definitions.disable
dbflow.definitions.enable
dbflow.definitions.archive
dbflow.definitions.copy
```

`dbflow.tasks.view` is required for the **My Workflow Tasks** navigation item and page access.

### PermissionChecker example

Map DBFlow ability strings to your host permission system. Do **not** assume Laravel Gate policies exist for `dbflow.*` unless you register them.

```php
// config/dbflow-filament.php

'permission_checker_class' => \App\Filament\Workflow\HostPermissionChecker::class,
```

```php
<?php

declare(strict_types=1);

namespace App\Filament\Workflow;

use App\Services\HostPermissionService;
use DbflowLabs\Filament\Contracts\PermissionChecker;
use Illuminate\Contracts\Auth\Authenticatable;

final class HostPermissionChecker implements PermissionChecker
{
    public function __construct(
        private readonly HostPermissionService $permissions,
    ) {}

    public function can(mixed $user, string $ability, mixed $record = null): bool
    {
        if (! $user instanceof Authenticatable) {
            return false;
        }

        return match ($ability) {
            'dbflow.tasks.view',
            'dbflow.tasks.approve',
            'dbflow.tasks.reject' => $this->permissions->allows($user, 'workflow.approve'),
            'dbflow.workflow_instances.view',
            'dbflow.workflow_instances.view_any' => $this->permissions->allows($user, 'workflow.view'),
            'dbflow.definitions.view',
            'dbflow.definitions.create',
            'dbflow.definitions.update',
            'dbflow.definitions.delete',
            'dbflow.definitions.validate',
            'dbflow.definitions.publish',
            'dbflow.definitions.disable',
            'dbflow.definitions.enable',
            'dbflow.definitions.archive',
            'dbflow.definitions.copy' => $this->permissions->allows($user, 'system.settings'),
            default => false,
        };
    }
}
```

### PermissionAssigneeOptionsResolver vs Core assignee resolvers

| Layer | Contract / API | Purpose |
| ----- | -------------- | ------- |
| Filament definition editor | `PermissionAssigneeOptionsResolver` | Human-readable labels for assignee keys authors can select |
| Core runtime | `AssigneeResolverRegistry::register($key, …)` | Resolves user ids when a workflow runs |

Use the **same string key** in both places—for example `finance_team`—but implement different classes.

### Workflow definition editor

The standard package ships with a form-based workflow definition editor.

Hosts may replace the editor through `workflow_definition_editor_resolver`. The resolver class must implement:

```php
DbflowLabs\Filament\Contracts\WorkflowDefinitionEditorResolver
```

This extension point is also used by Pro integrations to replace the standard form editor with a visual canvas editor. See [docs/workflow-definitions.md](docs/workflow-definitions.md).

## End-to-end integration checklist

Use this list to verify a working stack (UI + runtime):

1. `composer require` Filament + Core alpha packages (with `@alpha` when needed).
2. `php artisan migrate` — `dbflow_*` tables exist.
3. Publish and set `DBFLOW_AUTH_MODEL` in Core config.
4. Publish `dbflow-filament-config` and set `permission_checker_class` (**not** the default allow-all checker).
5. Register `DBFlowFilamentPanel::register($panel)` when your host feature flag is on.
6. Register `WorkflowDefinitionProvider`(s), assignee resolvers, and optional `WorkflowHooks` in a service provider.
7. Run `SyncWorkflowDefinitions::handle()` (or your wrapper) so published workflows exist in the database.
8. From host business code, call `DBFlow::start($workflowKey, $model, $actor)` when a document is submitted.
9. Log in as an assignee → open **My Workflow Tasks** → approve or reject.
10. Confirm host business guards (for example "confirm order") respect completed workflow state.

Filament-only steps (4–5, 9) do not replace Core steps (6–8).

## Troubleshooting

| Symptom | Likely cause | What to check |
| ------- | ------------ | ------------- |
| No "Workflow" navigation group | Panel not registered or Filament disabled | `DBFlowFilamentPanel::register()`, `dbflow-filament.enabled`, `panel_registration_mode !== disabled` |
| Navigation missing but routes work | Permission mapping | `PermissionChecker` must allow `dbflow.tasks.view` for the current user |
| Empty task list after submit | Assignee resolution | Resolver registered? Resolver returns user ids? Current user in assignee set? |
| `Unknown assignee resolver` on start | Missing Core registration | `DBFlow::registerAssigneeResolver()` for each `permission` / `callback` key in the definition |
| Workflow tables missing | Migrations not run | `php artisan migrate`; verify `dbflow_workflows` exists |
| 404 on package URLs | Panel path mismatch | Compare `{panel_path}/{route_prefix}/…` with your `Panel::path()` |
| Definitions UI empty | Not synced / not created | Run sync or create a draft via `WorkflowResource` |
| Everyone can approve | Default permission checker | Replace `AllowAllPermissionChecker` |
| UI shows but actions fail | Core disabled / auth model | `DBFLOW_AUTH_MODEL`, Core logs; Filament does not gate on `dbflow.enabled` |

## Typical integration flow

1. Install `dbflowlabs/core` and `dbflowlabs/filament` (see [Installation](#installation)).
2. Run `php artisan migrate`.
3. Publish Core + Filament configuration; set `DBFLOW_AUTH_MODEL`.
4. Replace `permission_checker_class` and other extension contracts.
5. Register `DBFlowFilamentPanel::register($panel)` behind your host feature flag.
6. Register workflow definitions, assignee resolvers, and hooks; sync definitions to the database.
7. Trigger `DBFlow::start()` from host business actions; guard downstream operations until workflows complete.
8. Open the Filament panel and verify tasks, instances, detail, and (if enabled) definition surfaces.

## Release candidate scope

`1.0.0-rc.2` pairs with Core `1.0.0-rc.1` under the frozen integration contract. Pin exact RC tags until stable `1.0.0`.

### Included

- Explicit host opt-in panel registration
- Configurable feature toggles per surface
- My Workflow Tasks page
- Workflow Instances page
- Workflow Instance Detail page
- Workflow Definition resource
- Standard form-based workflow definition editor (optional approval `timeout.due_in` / `on_timeout` from `1.0.0-rc.2`)
- Extension contracts for auth, labels, users, assignee options, and presentation
- Integration with Core definitions, versions, instances, tasks, assignments, and logs

### Not included

- Visual workflow builder or canvas authoring UI
- Billing, licensing, or premium feature gating
- Host domain models or product-specific adapters
- Automatic Filament panel mutation
- Production SLA

Visual authoring belongs to the separate commercial `dbflowlabs/filament-pro` package.

## Development

For package maintainers:

```bash
composer install
composer test
```

Before tagging a release:

```bash
bash scripts/release-check.sh
```

Local monorepo development may use path repositories in the **consumer application's** `composer.json` only.

This package resolves public dependencies from Packagist.

## Support

- Issues: [github.com/dbflow-labs/dbflow-filament/issues](https://github.com/dbflow-labs/dbflow-filament/issues)
- Core package: [github.com/dbflow-labs/dbflow-core](https://github.com/dbflow-labs/dbflow-core)
- Packagist: [packagist.org/packages/dbflowlabs/filament](https://packagist.org/packages/dbflowlabs/filament)
- Website: [dbflow.dev](https://dbflow.dev)
