<?php

/**
 * This file is part of the dbflowlabs/filament package.
 *
 * Copyright (c) 2026 Baron Wang <hello@dbflow.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT
 * @link    https://dbflow.dev
 * @see     https://github.com/dbflow-labs/dbflow-filament
 */

declare(strict_types=1);

namespace DbflowLabs\Filament\Support;

use DbflowLabs\Filament\Contracts\PermissionChecker;
use Illuminate\Support\Facades\Auth;

final class WorkflowFilamentPermissions
{
    /**
     * @var array<string, array<string, string>>
     */
    private const DEFAULT_ABILITIES = [
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
    ];

    /**
     * @var array<string, string>
     */
    private const LEGACY_FLAT_KEYS = [
        'tasks.view' => 'my_tasks',
        'workflow_instances.view_any' => 'workflow_instances',
        'tasks.approve' => 'approve_task',
        'tasks.reject' => 'reject_task',
        'tasks.reassign' => 'reassign_task',
        'workflow_instances.cancel' => 'cancel_workflow_instance',
    ];

    public static function checker(): PermissionChecker
    {
        return app(PermissionChecker::class);
    }

    public static function ability(string $group, string $action): string
    {
        $configured = config("dbflow-filament.permissions.{$group}.{$action}");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if ($group === 'workflow_instances' && $action === 'view_any') {
            $legacyInstances = config('dbflow-filament.permissions.workflow_instances');

            if (is_string($legacyInstances) && $legacyInstances !== '') {
                return $legacyInstances;
            }
        }

        $legacyKey = self::LEGACY_FLAT_KEYS["{$group}.{$action}"] ?? null;

        if (is_string($legacyKey)) {
            $legacyValue = config("dbflow-filament.permissions.{$legacyKey}");

            if (is_string($legacyValue) && $legacyValue !== '') {
                return $legacyValue;
            }
        }

        return self::DEFAULT_ABILITIES[$group][$action]
            ?? "{$group}.{$action}";
    }

    public static function can(string $group, string $action, mixed $record = null, mixed $user = null): bool
    {
        if (! (bool) config('dbflow-filament.enabled', true)) {
            return false;
        }

        $resolvedUser = $user ?? Auth::user();

        return self::checker()->can($resolvedUser, self::ability($group, $action), $record);
    }
}
