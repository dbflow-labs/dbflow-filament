# DBFlow Filament

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dbflowlabs/filament.svg)](https://packagist.org/packages/dbflowlabs/filament)
[![Total Downloads](https://img.shields.io/packagist/dt/dbflowlabs/filament.svg)](https://packagist.org/packages/dbflowlabs/filament)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-8.3%2B-777bb4.svg)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-13.x-ff2d20.svg)](composer.json)
[![Filament](https://img.shields.io/badge/filament-5.x-f59e0b.svg)](composer.json)

**Standard Filament integration for DBFlow Core.**

DBFlow Filament adds workflow tasks, workflow instances, audit timelines, and form-based workflow definition management to Laravel admin panels built with [Filament](https://filamentphp.com/).

This package is the **standard UI layer** for `dbflowlabs/core`. It intentionally ships read-only runtime surfaces and form-based definition editing. Visual workflow builders, drag-and-drop canvases, and advanced authoring experiences live in the separate commercial `dbflowlabs/filament-pro` package.

## Package overview


| Item            | Value                                                                                |
| --------------- | ------------------------------------------------------------------------------------ |
| Composer name   | `dbflowlabs/filament`                                                                |
| Namespace       | `DbflowLabs\Filament`                                                                |
| First release   | `0.1.0-alpha.1`                                                                      |
| License         | MIT                                                                                  |
| Author          | Baron Wang [hello@dbflow.dev](mailto:hello@dbflow.dev)                               |
| Core dependency | `[dbflowlabs/core](https://packagist.org/packages/dbflowlabs/core)` `^0.1.0-alpha.1` |
| Filament        | `^5.6`                                                                               |
| PHP             | `^8.3`                                                                               |
| Host framework  | Laravel 13.x                                                                         |




## What you get


| Surface                      | Description                                                                          |
| ---------------------------- | ------------------------------------------------------------------------------------ |
| **My Workflow Tasks**        | Pending workflow assignments for the signed-in user, with approve and reject actions |
| **Workflow Instances**       | Searchable runtime workflow instance list                                            |
| **Workflow Instance Detail** | Read-only workflow instance detail page with audit timeline                          |
| **Workflow Definitions**     | Filament resource for draft CRUD, validation, and publishing                         |
| **Extension Contracts**      | Host adapters for permissions, labels, status badges, users, and assignee options    |


Hosts opt in explicitly. This package does **not** auto-register Filament pages or resources during `boot()`.

## Requirements

- PHP `^8.3`
- Laravel 13.x
- Filament `^5.6`
- `[dbflowlabs/core](https://packagist.org/packages/dbflowlabs/core)` `^0.1.0-alpha.1`
- Core database migrations applied before using the workflow UI



## Installation

Install the package with Composer:

```bash
composer require "dbflowlabs/filament:0.1.0-alpha.1"
```

`dbflowlabs/core` is installed automatically.

For ongoing alpha updates after the first pin:

```bash
composer require "dbflowlabs/filament:^0.1.0-alpha.1"
```

To pin Core explicitly:

```bash
composer require "dbflowlabs/core:0.1.0-alpha.1"
```

After installation, confirm that `composer.lock` records the expected alpha version and resolved commit hash.

### Publish assets

Publish the package configuration:

```bash
php artisan vendor:publish --tag=dbflow-filament-config
```

Publish views if you want to customize the UI:

```bash
php artisan vendor:publish --tag=dbflow-filament-views
```

Optional translations:

```bash
php artisan vendor:publish --tag=dbflow-filament-translations
```



### Run Core migrations

Follow the `[dbflowlabs/core](https://github.com/dbflow-labs/dbflow-core)` installation guide, then run migrations:

```bash
php artisan migrate
```

DBFlow Filament uses the runtime tables provided by DBFlow Core.

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
        return DBFlowFilamentPanel::register(
            $panel
                ->id('admin')
                ->path('admin')
                // your pages, resources, middleware, branding...
        );
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


| Key                                   | Default | Purpose                                           |
| ------------------------------------- | ------- | ------------------------------------------------- |
| `enabled`                             | `true`  | Master package toggle                             |
| `enable_my_tasks_page`                | `true`  | Enables the My Workflow Tasks page                |
| `enable_my_task_actions`              | `true`  | Enables approve/reject task actions               |
| `enable_workflow_instances_page`      | `true`  | Enables workflow instance list and detail pages   |
| `enable_workflow_definition_resource` | `true`  | Enables the Workflow Definition resource          |
| `enable_logs_timeline`                | `true`  | Shows the audit timeline on instance detail pages |
| `require_reject_note`                 | `true`  | Requires a note when rejecting a task             |
| `reject_strategy`                     | `end`   | Default Core reject strategy                      |




### Navigation and routing


| Key                 | Default            | Purpose                           |
| ------------------- | ------------------ | --------------------------------- |
| `navigation_group`  | `Workflow`         | Filament navigation group label   |
| `route_prefix`      | `dbflow`           | URL slug prefix for package pages |
| `route_name_prefix` | `dbflow.filament.` | Named route prefix                |


Page and resource class overrides, such as `my_workflow_tasks_page_class` and `workflow_resource_class`, allow host applications to subclass package components without forking the package.

## Extension contracts

Production applications should replace the default support implementations with adapters wired to the host application's auth, user, and domain model systems.


| Contract                            | Config key                                   | Role                                                  |
| ----------------------------------- | -------------------------------------------- | ----------------------------------------------------- |
| `PermissionChecker`                 | `permission_checker_class`                   | Ability checks for pages, resources, and task actions |
| `UserDisplayResolver`               | `user_display_resolver_class`                | Display names for timeline actors and assignees       |
| `WorkflowableLabelResolver`         | `workflowable_label_resolver_class`          | Subject labels in instance lists and detail pages     |
| `StatusBadgeMapper`                 | `status_badge_mapper_class`                  | Filament badge labels and colors for statuses         |
| `UserAssigneeOptionsResolver`       | `user_assignee_options_resolver_class`       | User picker options in definition editors             |
| `PermissionAssigneeOptionsResolver` | `permission_assignee_options_resolver_class` | Permission-based assignee options                     |


Ability strings are configured under `permissions` in `config/dbflow-filament.php`.

Default ability names use the `dbflow.*` namespace, for example:

```text
dbflow.tasks.approve
dbflow.tasks.reject
dbflow.workflow_instances.view
dbflow.workflow_instances.view_any
dbflow.definitions.create
dbflow.definitions.publish
```



### PermissionChecker example

```php
// config/dbflow-filament.php

'permission_checker_class' => \App\Filament\Workflow\HostPermissionChecker::class,
```

```php
<?php

declare(strict_types=1);

namespace App\Filament\Workflow;

use DbflowLabs\Filament\Contracts\PermissionChecker;

final class HostPermissionChecker implements PermissionChecker
{
    public function can(mixed $user, string $ability, mixed $record = null): bool
    {
        // Map DBFlow ability strings to your host application's auth system.
        return $user?->can($ability) ?? false;
    }
}
```



### Workflow definition editor

The standard package ships with a form-based workflow definition editor.

Hosts may replace the editor through the `workflow_definition_editor_resolver` configuration value. The resolver class must implement:

```php
DbflowLabs\Filament\Contracts\WorkflowDefinitionEditorResolver
```

This extension point is also used by Pro integrations to replace the standard form editor with a visual canvas editor.

## Typical integration flow

1. Install `dbflowlabs/core` and `dbflowlabs/filament`.
2. Publish Core migrations and run `php artisan migrate`.
3. Publish DBFlow Filament configuration.
4. Register DBFlow Filament in your Filament `PanelProvider`.
5. Bind extension contracts for permissions, labels, users, and presentation.
6. Define or publish workflows through Core.
7. Open the Filament panel and verify the task, instance, detail, and definition surfaces.



## Alpha scope

`0.1.0-alpha.1` is intended for early adopters. APIs may change between alpha tags.

### Included

- Explicit host opt-in panel registration
- Configurable feature toggles per surface
- My Workflow Tasks page
- Workflow Instances page
- Workflow Instance Detail page
- Workflow Definition resource
- Standard form-based workflow definition editor
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

