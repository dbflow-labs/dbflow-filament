# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-07-23

### Added

- Requires `dbflowlabs/core` `^1.1`.
- Assignment provenance display on My Workflow Tasks and View Workflow Instance.
- Read-only Delegations page when delegation capability is enabled.
- Read-only Action Executions page when reliable-action capability is enabled.
- SLA visibility fields and timeline presenters for v1.1 SLA tasks.
- Webhook metadata redaction in read-only surfaces (no plaintext secrets).
- Permissions: `dbflow.delegations.view_any`, `dbflow.action_executions.view`, and related capability gates.
- Standard page substitution hooks for Pro (`workflow_delegations_page_class`, `view_workflow_action_execution_page_class`).
- Built-in UI translations for `en`, `zh_CN`, `zh_TW`, `ja`, `de`, `es`, `fr`, and `pt_BR`.

### Changed

- Task inbox queries tolerate legacy rows with null provenance (falls back to direct assignment).
- Timeline entries for reassignment, SLA, and action execution events.

### Upgrade notes

- From `1.0.x`: `composer require dbflowlabs/filament:^1.1 dbflowlabs/core:^1.1`, migrate, clear caches.
- See [UPGRADE-1.1.md](UPGRADE-1.1.md) for new permissions, configuration, and Standard vs Pro boundaries.
- No Pro package required; Pro extends via config hooks only.

## [1.0.0] - 2026-07-07

### Changed

- **First stable release.** Requires `dbflowlabs/core` `^1.0`.
- No breaking API changes from `1.0.0-rc.2`; coordinated stable tag with Core `1.0.0`.

### Upgrade notes

- From RC: `composer require dbflowlabs/filament:^1.0 dbflowlabs/core:^1.0`
- No Filament code changes required when upgrading from `1.0.0-rc.2` on the frozen integration contract.

## [1.0.0-rc.2] - 2026-07-07

### Added

- Standard workflow editor: optional approval `timeout.due_in` and `timeout.on_timeout` fields (aligned with Core `0.5.0-alpha.1`).
- Timeline labels for `task_reassigned` and `task_timed_out` audit events.

### Upgrade notes

- Pin `dbflowlabs/filament:1.0.0-rc.2` with `dbflowlabs/core:1.0.0-rc.1`.
- Schedule `php artisan dbflow:process-timeouts` when authors configure approval deadlines in the definition editor.

## [1.0.0-rc.1] - 2026-07-07

### Changed

- Requires `dbflowlabs/core` `^1.0.0-rc.1` (aligned with Core API freeze RC).
- Documentation updated for RC installation pins and version pairing.

### Upgrade notes

- Pin `dbflowlabs/filament:1.0.0-rc.1` with `dbflowlabs/core:1.0.0-rc.1`.
- No Filament API changes from `0.9.0-beta.1`; validate against Core `UPGRADE-1.0.md` when upgrading Core.

## [0.9.0-beta.1] - 2026-07-07

### Added

- **0.9 ecosystem alignment:** `MyWorkflowTasksQuery` delegates to Core `WorkflowTaskQueryService::pendingAssignmentsQueryForUser()` (includes `workflowVersion` eager load).
- Reassign action on My Workflow Tasks (`DBFlow::reassign()`).
- Cancel workflow header action on View Workflow Instance (`DBFlow::cancel()`).
- Subject column with `WorkflowRouteResolvable` links on My Workflow Tasks.
- Runtime-disabled banner and hidden task/instance actions when `DBFLOW_ENABLED=false`.
- `WorkflowableShowUrlResolver` helper.
- Permissions: `dbflow.tasks.reassign`, `dbflow.workflow_instances.cancel`.
- Config toggles: `enable_my_task_reassign_action`, `enable_instance_cancel_action`, `open_workflowable_links_in_new_tab`.

### Changed

- `MyWorkflowTaskActionRunner` routes approve / reject / reassign through `DBFlow` facade.
- Requires `dbflowlabs/core` `^0.9.0-beta`.

### Documentation

- Aligns with Core `docs/integration/filament.md` and acceptance checklist.

### Upgrade notes

- Pin `dbflowlabs/filament:0.9.0-beta.1` with `dbflowlabs/core:0.9.0-beta.1`.
- Register `UserAssigneeOptionsResolver` for reassign target pickers.
- Implement host `PermissionChecker` for `dbflow.tasks.reassign` and `dbflow.workflow_instances.cancel` when exposing new actions.
- Host models may implement `WorkflowRouteResolvable` for linked Subject column URLs.

## [0.3.1-alpha.1] - 2026-07-07

### Changed

- **BREAKING (Core dependency):** Requires `dbflowlabs/core` `^0.3.0-alpha.1`.
- User ID comparisons in `MyWorkflowTaskActionRunner` use string equality (supports UUID/ULID assignees).
- Workflow definition editor no longer restricts user assignee values to integers.
- `WorkflowDraftActionRunner::publishDraft()` accepts `int|string|null` for `$publishedBy`.

### Added

- Test coverage for UUID assignee IDs in task action runner.
- `TestUser` model and `dbflow.auth` config in test bootstrap.
- Timeline presenter test for `TaskCancelled` audit event labels (aligned with Core 0.3.1).

### Documentation

- README updated for Core 0.3 auth config, `dbflow:sync` / `dbflow:validate` commands, and UUID user ID support.
- README `DBFLOW_ENABLED` table aligned with Core 0.3.1 definition-management contract.
- `docs/release-readiness.md` core constraint updated to `^0.3.0-alpha.1`.

[Unreleased]: https://github.com/dbflow-labs/dbflow-filament/compare/1.1.0...HEAD
[1.1.0]: https://github.com/dbflow-labs/dbflow-filament/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/dbflow-labs/dbflow-filament/compare/1.0.0-rc.2...1.0.0
[1.0.0-rc.2]: https://github.com/dbflow-labs/dbflow-filament/compare/1.0.0-rc.1...1.0.0-rc.2
[1.0.0-rc.1]: https://github.com/dbflow-labs/dbflow-filament/compare/0.9.0-beta.1...1.0.0-rc.1
[0.9.0-beta.1]: https://github.com/dbflow-labs/dbflow-filament/compare/0.3.1-alpha.1...0.9.0-beta.1
[0.3.1-alpha.1]: https://github.com/dbflow-labs/dbflow-filament/compare/0.1.0-alpha.1...0.3.1-alpha.1
