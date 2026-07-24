# DBFlow Filament 1.1.0

Runtime visibility release for the standard Filament integration. Pairs with Core `^1.1`.

## Highlights

- Requires `dbflowlabs/core:^1.1`
- Read-only Delegations and Action Executions pages (capability-gated)
- Assignment provenance on My Workflow Tasks and instance detail
- SLA visibility fields and timeline presenters
- Redacted webhook execution metadata in read-only surfaces
- Built-in UI translations for `en`, `zh_CN`, `zh_TW`, `ja`, `de`, `es`, `fr`, and `pt_BR`

## Upgrade from 1.0.x

```bash
composer require dbflowlabs/core:^1.1 dbflowlabs/filament:^1.1
php artisan migrate
```

See [UPGRADE-1.1.md](UPGRADE-1.1.md) for new permissions, configuration, and Standard vs Pro boundaries.

## Ecosystem pairing

| Package | Tag | Constraint |
| --- | --- | --- |
| `dbflowlabs/core` | `1.1.0` | — |
| `dbflowlabs/filament` | `1.1.0` | `^1.1` on core |
| `dbflowlabs/filament-pro` | `1.1.0` | `^1.1` on core + filament |

## Compare

https://github.com/dbflow-labs/dbflow-filament/compare/1.0.0...1.1.0
