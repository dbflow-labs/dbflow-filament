<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | DBFlow Filament Integration
    |--------------------------------------------------------------------------
    |
    | Standard Filament UI integration settings for DBFlow Core workflows.
    | Host applications opt in by registering package pages/resources through
    | DbflowLabs\Filament\Support\DBFlowFilamentPanel (or manual equivalents)
    | inside their Filament PanelProvider.
    |
    */

    'enabled' => env('DBFLOW_FILAMENT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Panel Registration Mode
    |--------------------------------------------------------------------------
    |
    | explicit: host must call DBFlowFilamentPanel::register($panel) (recommended).
    | disabled: package never mutates Filament panels automatically.
    |
    */

    'panel_registration_mode' => env('DBFLOW_FILAMENT_PANEL_REGISTRATION', 'explicit'),

    /*
    |--------------------------------------------------------------------------
    | Feature Toggles
    |--------------------------------------------------------------------------
    |
    | Hosts can expose only the standard UI surfaces they need. Optional custom
    | workflow definition editors may be registered through the resolver hook.
    |
    */

    'enable_my_tasks_page' => env('DBFLOW_FILAMENT_MY_TASKS', true),

    'enable_my_task_actions' => env('DBFLOW_FILAMENT_MY_TASK_ACTIONS', true),

    'enable_my_task_reassign_action' => env('DBFLOW_FILAMENT_MY_TASK_REASSIGN', true),

    'enable_instance_cancel_action' => env('DBFLOW_FILAMENT_INSTANCE_CANCEL', true),

    'require_reject_note' => env('DBFLOW_FILAMENT_REQUIRE_REJECT_NOTE', true),

    /*
    | Reject strategy passed to DbflowLabs\Core\Actions\RejectTask when rejecting
    | from the My Workflow Tasks page. Default "end" terminates the workflow.
    */

    'reject_strategy' => env('DBFLOW_FILAMENT_REJECT_STRATEGY', 'end'),

    /*
    | Hosts may point to a subclass that adds host-specific actions or navigation.
    */

    'my_workflow_tasks_page_class' => \DbflowLabs\Filament\Pages\MyWorkflowTasks::class,

    'permissions' => [
        'tasks' => [
            'view' => 'dbflow.tasks.view',
            'approve' => 'dbflow.tasks.approve',
            'reject' => 'dbflow.tasks.reject',
            'reassign' => 'dbflow.tasks.reassign',
        ],
        'workflow_instances' => [
            'view' => 'dbflow.workflow_instances.view',
            'view_any' => 'dbflow.workflow_instances.view_any',
            'cancel' => 'dbflow.workflow_instances.cancel',
        ],
        'definitions' => [
            'view' => 'dbflow.definitions.view',
            'create' => 'dbflow.definitions.create',
            'update' => 'dbflow.definitions.update',
            'delete' => 'dbflow.definitions.delete',
            'validate' => 'dbflow.definitions.validate',
            'publish' => 'dbflow.definitions.publish',
            'disable' => 'dbflow.definitions.disable',
            'enable' => 'dbflow.definitions.enable',
            'archive' => 'dbflow.definitions.archive',
            'copy' => 'dbflow.definitions.copy',
        ],
        'delegations' => [
            'view_any' => 'dbflow.delegations.view_any',
        ],
        'sla_events' => [
            'view' => 'dbflow.sla_events.view',
        ],
        'action_executions' => [
            'view_any' => 'dbflow.action_executions.view_any',
            'view' => 'dbflow.action_executions.view',
        ],
        'action_attempts' => [
            'view' => 'dbflow.action_attempts.view',
        ],
        'webhook_metadata' => [
            'view' => 'dbflow.webhook_metadata.view',
        ],
        /*
        | Backward-compatible flat keys retained for hosts that configured
        | permissions before the nested ability map was introduced.
        */
        'my_tasks' => 'dbflow.tasks.view',
        'approve_task' => 'dbflow.tasks.approve',
        'reject_task' => 'dbflow.tasks.reject',
        'reassign_task' => 'dbflow.tasks.reassign',
        'cancel_workflow_instance' => 'dbflow.workflow_instances.cancel',
    ],

    'enable_workflow_instances_page' => env('DBFLOW_FILAMENT_INSTANCES', true),

    /*
    | Backward-compatible alias retained for earlier extraction docs.
    */

    'enable_workflow_instance_resource' => env('DBFLOW_FILAMENT_INSTANCES', true),

    'workflow_instances_page_class' => \DbflowLabs\Filament\Pages\WorkflowInstances::class,

    'view_workflow_instance_page_class' => \DbflowLabs\Filament\Pages\ViewWorkflowInstance::class,

    'enable_logs_timeline' => env('DBFLOW_FILAMENT_LOGS_TIMELINE', true),

    'enable_delegations_page' => env('DBFLOW_FILAMENT_DELEGATIONS', true),

    'workflow_delegations_page_class' => \DbflowLabs\Filament\Pages\WorkflowDelegations::class,

    'enable_action_executions_page' => env('DBFLOW_FILAMENT_ACTION_EXECUTIONS', true),

    'workflow_action_executions_page_class' => \DbflowLabs\Filament\Pages\WorkflowActionExecutions::class,

    'view_workflow_action_execution_page_class' => \DbflowLabs\Filament\Pages\ViewWorkflowActionExecution::class,

    'due_soon_hours' => (int) env('DBFLOW_FILAMENT_DUE_SOON_HOURS', 24),

    'runtime_detail_limit' => (int) env('DBFLOW_FILAMENT_RUNTIME_DETAIL_LIMIT', 100),

    'enable_workflow_definition_resource' => env('DBFLOW_FILAMENT_DEFINITIONS', true),

    'workflow_resource_class' => \DbflowLabs\Filament\Resources\WorkflowResource::class,

    /*
    | Optional class implementing DbflowLabs\Filament\Contracts\WorkflowDefinitionEditorResolver.
    | When null, the Standard linear approval form editor is rendered.
    | Pro Canvas or other custom editors may replace the Standard editor through this hook.
    | Must remain null or a class string for config-cache safety.
    */

    'workflow_definition_editor_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation_group' => env('DBFLOW_FILAMENT_NAV_GROUP', 'Workflow'),

    'navigation_sort' => [
        'my_tasks' => 10,
        'workflow_instances' => 20,
        'workflow_definitions' => 25,
        'delegations' => 30,
        'action_executions' => 35,
    ],

    /*
    | When null, each page/resource controls navigation via canAccess().
    | When a callable is set, it receives the authenticated user (or null)
    | and must return whether navigation items should register.
    */

    'should_register_navigation' => null,

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    */

    'route_prefix' => 'dbflow',

    'route_name_prefix' => 'dbflow.filament.',

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Additional middleware applied to package Filament pages when registered
    | through DBFlowFilamentPanel. Host panel middleware still applies first.
    |
    | @var list<class-string>
    */

    'middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | User Resolution
    |--------------------------------------------------------------------------
    |
    | Display-only defaults for Filament tables and timelines. Runtime approval
    | identity continues to use DbflowLabs\Core user resolver configuration.
    |
    */

    'user_model' => null,

    'user_name_attribute' => 'name',

    /*
    |--------------------------------------------------------------------------
    | Host Extension Hooks
    |--------------------------------------------------------------------------
    |
    | Prefer small callables or class-string contracts. Return types are
    | documented on each contract in DbflowLabs\Filament\Contracts.
    |
    | permission_checker:
    |   bool|null callable(Authenticatable $user, string $ability, mixed $record = null)
    |   When null, package pages fall back to config('dbflow.enabled') or canAccess defaults.
    |
    | workflowable_label_resolver:
    |   string callable(?string $workflowableType, ?int $workflowableId, ?WorkflowInstance $instance = null)
    |
    | status_badge_mapper:
    |   array{color: string, label: string} callable(mixed $status, mixed $record = null)
    |
    */

    'permission_checker' => null,

    'workflowable_label_resolver' => null,

    'status_badge_mapper' => null,

    /*
    |--------------------------------------------------------------------------
    | Contract Implementations
    |--------------------------------------------------------------------------
    |
    | Hosts may override these class strings to customize package UI behavior.
    | Each class must implement the corresponding contract under
    | DbflowLabs\Filament\Contracts.
    |
    */

    'permission_checker_class' => \DbflowLabs\Filament\Support\AllowAllPermissionChecker::class,

    'workflowable_label_resolver_class' => \DbflowLabs\Filament\Support\DefaultWorkflowableLabelResolver::class,

    'user_display_resolver_class' => \DbflowLabs\Filament\Support\DefaultUserDisplayResolver::class,

    'user_assignee_options_resolver_class' => \DbflowLabs\Filament\Support\DefaultUserAssigneeOptionsResolver::class,

    'permission_assignee_options_resolver_class' => \DbflowLabs\Filament\Support\DefaultPermissionAssigneeOptionsResolver::class,

    'status_badge_mapper_class' => \DbflowLabs\Filament\Support\DefaultStatusBadgeMapper::class,

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    'date_time_format' => 'Y-m-d H:i:s',

    /*
    | Display timezone for custom (non-Filament Table) datetime formatting.
    | Stored UTC values are never mutated; null falls back to app.timezone.
    */

    'display_timezone' => env('DBFLOW_FILAMENT_DISPLAY_TIMEZONE'),

    'open_workflowable_links_in_new_tab' => env('DBFLOW_FILAMENT_OPEN_WORKFLOWABLE_IN_NEW_TAB', false),

    'table_polling_interval' => null,

];
