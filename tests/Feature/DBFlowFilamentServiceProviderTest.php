<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Filament\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class DBFlowFilamentServiceProviderTest extends TestCase
{
    #[Test]
    public function service_provider_merges_package_config(): void
    {
        $this->assertSame('Workflow', config('dbflow-filament.navigation_group'));
        $this->assertTrue((bool) config('dbflow-filament.enabled'));
        $this->assertSame('explicit', config('dbflow-filament.panel_registration_mode'));
        $this->assertTrue((bool) config('dbflow-filament.enable_my_tasks_page'));
        $this->assertSame('dbflow', config('dbflow-filament.route_prefix'));
        $this->assertSame('Y-m-d H:i:s', config('dbflow-filament.date_time_format'));
        $this->assertSame(
            \DbflowLabs\Filament\Support\AllowAllPermissionChecker::class,
            config('dbflow-filament.permission_checker_class'),
        );
    }

    #[Test]
    public function package_translations_are_registered(): void
    {
        $this->assertSame('Workflows', trans('dbflow-filament::dbflow-filament.navigation.group'));
    }

    #[Test]
    public function package_views_are_registered(): void
    {
        $this->assertTrue(view()->exists('dbflow-filament::placeholder'));
    }

    #[Test]
    public function config_publish_tag_is_registered(): void
    {
        $paths = $this->app['config']->get('view.paths', []);

        $this->assertNotEmpty($paths);
        $this->assertSame('Workflow', config('dbflow-filament.navigation_group'));
    }
}
