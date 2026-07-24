# Upgrade from Filament 1.0 to 1.1

## Requirements

- `dbflowlabs/core` `^1.1` (Stage 1.1-A through 1.1-D runtime capabilities)
- `dbflowlabs/filament` `^1.1`
- **MySQL `8.0` or later** for production deployments (v1.1 stable certification target)
- SQLite remains supported for local development and package tests

Filament `^1.1` must not be installed against Core `^1.0` because v1.1 visibility surfaces depend on provenance columns, SLA ledgers, and reliable action execution tables.

## Composer

```bash
composer require dbflowlabs/core:^1.1 dbflowlabs/filament:^1.1
php artisan migrate
```

## New permissions

Configure your `permission_checker_class` to enforce:

| Ability | Default key |
|---------|-------------|
| View delegations | `dbflow.delegations.view_any` |
| View SLA events | `dbflow.sla_events.view` |
| View action executions (list) | `dbflow.action_executions.view_any` |
| View action execution detail | `dbflow.action_executions.view` |
| View attempt history | `dbflow.action_attempts.view` |
| View webhook metadata | `dbflow.webhook_metadata.view` |

Task inbox access (`dbflow.tasks.view`) does not grant operational visibility.

## New configuration

```env
DBFLOW_FILAMENT_DELEGATIONS=true
DBFLOW_FILAMENT_ACTION_EXECUTIONS=true
DBFLOW_FILAMENT_DUE_SOON_HOURS=24
DBFLOW_FILAMENT_RUNTIME_DETAIL_LIMIT=100
```

## Standard vs Pro

Filament Standard 1.1 adds **read-only** runtime visibility only. Delegation management, SLA configuration, action recovery, and retry/skip UI remain in `dbflowlabs/filament-pro`.

See [docs/architecture/v1.1-runtime-visibility.md](docs/architecture/v1.1-runtime-visibility.md).
