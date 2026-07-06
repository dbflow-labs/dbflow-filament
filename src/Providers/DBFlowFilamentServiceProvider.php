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

namespace DbflowLabs\Filament\Providers;

use DbflowLabs\Filament\Contracts\PermissionAssigneeOptionsResolver;
use DbflowLabs\Filament\Contracts\PermissionChecker;
use DbflowLabs\Filament\Contracts\StatusBadgeMapper;
use DbflowLabs\Filament\Contracts\UserAssigneeOptionsResolver;
use DbflowLabs\Filament\Contracts\UserDisplayResolver;
use DbflowLabs\Filament\Contracts\WorkflowableLabelResolver;
use DbflowLabs\Filament\Support\Actions\MyWorkflowTaskActionRunner;
use DbflowLabs\Filament\Support\Actions\WorkflowDraftActionRunner;
use DbflowLabs\Filament\Support\Actions\WorkflowInstanceActionRunner;
use DbflowLabs\Filament\Support\Presenters\WorkflowInstanceTimelinePresenter;
use DbflowLabs\Filament\Support\Queries\MyWorkflowTasksQuery;
use DbflowLabs\Filament\Support\WorkflowableShowUrlResolver;
use DbflowLabs\Filament\Support\WorkflowDefinitionEditorResolverManager;
use DbflowLabs\Filament\Support\WorkflowDraftValidationSync;
use Illuminate\Support\ServiceProvider;

final class DBFlowFilamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/dbflow-filament.php', 'dbflow-filament');

        $this->registerContract(PermissionChecker::class, 'permission_checker_class');
        $this->registerContract(WorkflowableLabelResolver::class, 'workflowable_label_resolver_class');
        $this->registerContract(UserDisplayResolver::class, 'user_display_resolver_class');
        $this->registerContract(UserAssigneeOptionsResolver::class, 'user_assignee_options_resolver_class');
        $this->registerContract(PermissionAssigneeOptionsResolver::class, 'permission_assignee_options_resolver_class');
        $this->registerContract(StatusBadgeMapper::class, 'status_badge_mapper_class');

        $this->app->singleton(MyWorkflowTaskActionRunner::class);
        $this->app->singleton(WorkflowInstanceActionRunner::class);
        $this->app->singleton(MyWorkflowTasksQuery::class);
        $this->app->singleton(WorkflowableShowUrlResolver::class);
        $this->app->singleton(WorkflowInstanceTimelinePresenter::class);
        $this->app->singleton(WorkflowDraftActionRunner::class);
        $this->app->singleton(WorkflowDraftValidationSync::class);
        $this->app->singleton(WorkflowDefinitionEditorResolverManager::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'dbflow-filament');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'dbflow-filament');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/dbflow-filament.php' => config_path('dbflow-filament.php'),
            ], 'dbflow-filament-config');

            $this->publishes([
                __DIR__.'/../../resources/views' => resource_path('views/vendor/dbflow-filament'),
            ], 'dbflow-filament-views');

            $this->publishes([
                __DIR__.'/../../lang' => lang_path('vendor/dbflow-filament'),
            ], 'dbflow-filament-translations');
        }
    }

    /**
     * @param  class-string  $contract
     */
    private function registerContract(string $contract, string $configKey): void
    {
        $this->app->singleton($contract, function () use ($configKey, $contract): object {
            $implementation = config('dbflow-filament.'.$configKey);

            if (! is_string($implementation) || ! class_exists($implementation)) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid dbflow-filament.%s configuration. Expected an existing class implementing %s.',
                    $configKey,
                    $contract,
                ));
            }

            $instance = $this->app->make($implementation);

            if (! $instance instanceof $contract) {
                throw new \InvalidArgumentException(sprintf(
                    'Class [%s] must implement %s.',
                    $implementation,
                    $contract,
                ));
            }

            return $instance;
        });
    }
}
