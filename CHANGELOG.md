# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.9.0-beta.1] - Unreleased

### Added

- **0.9 ecosystem alignment:** `MyWorkflowTasksQuery` delegates to Core `WorkflowTaskQueryService::pendingAssignmentsQueryForUser()` (includes `workflowVersion` eager load).
- Reassign action on My Workflow Tasks (`DBFlow::reassign()`).
- Cancel workflow header action on View Workflow Instance (`DBFlow::cancel()`).
- Permissions: `dbflow.tasks.reassign`, `dbflow.workflow_instances.cancel`.
- Config toggles: `enable_my_task_reassign_action`, `enable_instance_cancel_action`.

### Changed

- `MyWorkflowTaskActionRunner` routes approve / reject / reassign through `DBFlow` facade.
- Requires `dbflowlabs/core` `^0.5.0-alpha.1` (pair with Core `0.9.x-beta` at release).

### Documentation

- Aligns with Core `docs/integration/filament.md` and acceptance checklist.

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

[0.3.1-alpha.1]: https://github.com/dbflow-labs/dbflow-filament/compare/0.1.0-alpha.1...0.3.1-alpha.1
