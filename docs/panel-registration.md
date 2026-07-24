# Panel Registration

`dbflowlabs/filament` uses **explicit host opt-in** panel registration. The package does not auto-discover or mutate Filament panels during `boot()`.

## Why explicit registration

- Multi-panel Laravel apps must control which panel receives workflow UI.
- Hosts keep ownership of navigation, permissions, branding, and middleware.
- Pro canvas/builder assets remain outside this package.
- Business adapters remain in the host application.

## Recommended integration

Call `DbflowLabs\Filament\Support\DBFlowFilamentPanel::register()` from your Filament `PanelProvider`:

```php
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
                ->pages([
                    // host pages...
                ])
                ->resources([
                    // host resources...
                ]),
        );
    }
}
```

## Config flags

| Key | Purpose |
| --- | --- |
| `dbflow-filament.enabled` | Master package toggle |
| `dbflow-filament.panel_registration_mode` | `explicit` (default) or `disabled` |
| `dbflow-filament.enable_my_tasks_page` | Include My Workflow Tasks page when extracted |
| `dbflow-filament.enable_workflow_instance_resource` | Include instance list/detail pages when extracted |
| `dbflow-filament.enable_workflow_definition_resource` | Include Workflow Definition resource when extracted |

When no package pages or resources are available yet, `register()` is a safe no-op.

## Manual registration alternative

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

## Translation namespace

Package translations load under the `dbflow-filament` namespace from `lang/en/dbflow-filament.php`.

Example:

```php
trans('dbflow-filament::dbflow-filament.pages.my_tasks.title');
```

Publish translations when needed:

```bash
php artisan vendor:publish --tag=dbflow-filament-translations
```

## Not included

- Visual workflow builder / LogicFlow canvas
- Graph preview UI
- Commercial licensing or billing
- Host business adapters
- Automatic panel mutation without host opt-in
