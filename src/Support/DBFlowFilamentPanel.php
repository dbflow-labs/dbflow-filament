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

use DbflowLabs\Filament\Contracts\WorkflowableLabelResolver;
use DbflowLabs\Filament\Pages\MyWorkflowTasks;
use DbflowLabs\Filament\Pages\ViewWorkflowInstance;
use DbflowLabs\Filament\Pages\WorkflowInstances;
use Filament\Panel;

final class DBFlowFilamentPanel
{
    /**
     * @return list<class-string>
     */
    public static function pageClasses(): array
    {
        if (! self::shouldExposeComponents()) {
            return [];
        }

        $pages = [];

        if ((bool) config('dbflow-filament.enable_my_tasks_page', true)) {
            $pageClass = config('dbflow-filament.my_workflow_tasks_page_class', MyWorkflowTasks::class);

            if (is_string($pageClass) && class_exists($pageClass)) {
                $pages[] = $pageClass;
            }
        }

        if (self::isWorkflowInstancesEnabled()) {
            $listPageClass = config('dbflow-filament.workflow_instances_page_class', WorkflowInstances::class);

            if (is_string($listPageClass) && class_exists($listPageClass)) {
                $pages[] = $listPageClass;
            }

            $detailPageClass = config('dbflow-filament.view_workflow_instance_page_class', ViewWorkflowInstance::class);

            if (is_string($detailPageClass) && class_exists($detailPageClass)) {
                $pages[] = $detailPageClass;
            }
        }

        return $pages;
    }

    /**
     * @return list<class-string>
     */
    public static function resourceClasses(): array
    {
        if (! self::shouldExposeComponents()) {
            return [];
        }

        $resources = [];

        if ((bool) config('dbflow-filament.enable_workflow_definition_resource', true)) {
            $resourceClass = config('dbflow-filament.workflow_resource_class', \DbflowLabs\Filament\Resources\WorkflowResource::class);

            if (is_string($resourceClass) && class_exists($resourceClass)) {
                $resources[] = $resourceClass;
            }
        }

        return $resources;
    }

    public static function register(Panel $panel): Panel
    {
        if (! self::shouldExposeComponents()) {
            return $panel;
        }

        $pages = self::pageClasses();
        $resources = self::resourceClasses();

        if ($pages !== []) {
            $panel->pages(array_merge($panel->getPages(), $pages));
        }

        if ($resources !== []) {
            $panel->resources(array_merge($panel->getResources(), $resources));
        }

        return $panel;
    }

    private static function shouldExposeComponents(): bool
    {
        if (! (bool) config('dbflow-filament.enabled', true)) {
            return false;
        }

        return config('dbflow-filament.panel_registration_mode', 'explicit') !== 'disabled';
    }

    private static function isWorkflowInstancesEnabled(): bool
    {
        if (config()->has('dbflow-filament.enable_workflow_instances_page')) {
            return (bool) config('dbflow-filament.enable_workflow_instances_page');
        }

        return (bool) config('dbflow-filament.enable_workflow_instance_resource', true);
    }
}
