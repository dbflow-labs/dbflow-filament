# Workflow Definitions (Standard CRUD Shell)

**Package:** `dbflowlabs/filament`  
**Stage:** 2.2F  
**Author:** Baron Wang <hello@dbflow.dev>

## Purpose

Provides a package-native Filament resource shell for workflow definition administration:

- list workflow definitions;
- create/edit definition metadata;
- show draft/published version indicators;
- validate and publish drafts through `dbflowlabs/core` actions;
- optional advanced draft editing via JSON textarea (metadata-first shell).

This is **not** a bundled visual builder, graph preview surface, or custom editor product.

## Registration

Register through `DBFlowFilamentPanel::register($panel)` in your Filament `PanelProvider`:

```php
use DbflowLabs\Filament\Support\DBFlowFilamentPanel;

return DBFlowFilamentPanel::register($panel);
```

The resource is included when:

- `dbflow-filament.enabled` is `true`;
- `dbflow-filament.panel_registration_mode` is not `disabled`;
- `dbflow-filament.enable_workflow_definition_resource` is `true`.

Override the resource class:

```php
'workflow_resource_class' => \DbflowLabs\Filament\Resources\WorkflowResource::class,
```

## Config flags

| Key | Default | Purpose |
| --- | --- | --- |
| `enable_workflow_definition_resource` | `true` | Register `WorkflowResource` |
| `workflow_resource_class` | package class | Host subclass override |
| `navigation_sort.workflow_definitions` | `25` | Navigation order |

## Permission checker abilities

Configured under `dbflow-filament.permissions.definitions`:

| Ability key | Default |
| --- | --- |
| `view` | `dbflow.definitions.view` |
| `create` | `dbflow.definitions.create` |
| `update` | `dbflow.definitions.update` |
| `validate` | `dbflow.definitions.validate` |
| `publish` | `dbflow.definitions.publish` |
| `delete` | `dbflow.definitions.delete` |

Hosts may bind a custom `PermissionChecker` implementation.

## Core API usage

| UI action | Core API |
| --- | --- |
| Create definition | `CreateWorkflowDraft::handle()` with `MinimalWorkflowDefinitionFactory` skeleton |
| Save draft edits | `SaveWorkflowDraft::handle()` |
| Validate draft | `WorkflowDefinitionValidator` via `WorkflowDraftValidationSync` |
| Publish draft | `PublishWorkflowDraft::handle()` |
| Copy / disable / enable / archive / delete | `CopyWorkflow`, `DisableWorkflow`, `EnableWorkflow`, `ArchiveWorkflow`, `DeleteWorkflow` |

Lifecycle fields are **not** mutated directly from Filament forms.

## Metadata-only form scope

- **Create:** key, name, description; draft seeded with a minimal start → approval → end skeleton.
- **Edit:** metadata fields plus collapsed JSON textarea when a draft exists, unless a custom definition editor resolver returns replacement components.
- Structure repeaters, custom visual editors, and host-specific builder save hooks are **excluded** from this package slice.

## Definition editor resolver hook

An optional, config-cache-safe hook is available for custom workflow definition editor components.

| Key | Default | Purpose |
| --- | --- | --- |
| `workflow_definition_editor_resolver` | `null` | Class implementing `DbflowLabs\Filament\Contracts\WorkflowDefinitionEditorResolver` |

Behavior:

- When `null`, the existing `definition_json` textarea renders unchanged.
- When a resolver class is configured, `EditWorkflow` asks the resolver for Filament schema components for the definition section.
- When a resolver returns an empty array, the default textarea is used.
- Runtime resolver registration is available through `WorkflowDefinitionEditorResolverManager` for tests and host applications.
- No persistence, validate, or publish behavior is changed by the hook itself.

Resolver context keys:

- `record`
- `operation`
- `state_path`
- `resource`

The standard package does not bundle any external editor. Hosts or separate packages may register a resolver later.

## Draft / validation / publish behavior

1. Creating a workflow stores a draft through `CreateWorkflowDraft`.
2. Validate runs strict `WorkflowDefinitionValidator` against the stored draft and persists errors/warnings on the workflow row. Hosts may also run `php artisan dbflow:validate --strict` (Core) or set `DBFLOW_EXPRESSION_STRICT=true` to catch invalid condition expressions early.
3. Publish calls `PublishWorkflowDraft`, which rejects invalid drafts via `InvalidWorkflowDefinitionException`.
4. Published versions are immutable snapshots in `dbflow_workflow_versions`.

## Known limitations

- No bundled visual node editor or drag-and-drop layout.
- No bundled graph preview UI.
- No version comparison UI.
- No template picker on the package create page (host may extend `CreateWorkflow`).
- JSON textarea remains the default advanced escape hatch when no custom resolver is configured.

## Explicit non-goals for the standard package

Deferred to host extensions or separately distributed editor packages:

- bundled custom editor Blade/components
- bundled graph preview helpers
- bundled editor JavaScript/CSS pipelines
- node position drag-and-drop APIs (`UpdateWorkflowDraftNodePositions` UI)
- structure repeater forms with host-specific banners

## Reference host bridge

Host applications may extend the package resource and retain:

- `dbflow.enabled` access gating;
- host navigation translations;
- host create/edit pages with template picker, structure form, custom editor surfaces, and builder hooks.
