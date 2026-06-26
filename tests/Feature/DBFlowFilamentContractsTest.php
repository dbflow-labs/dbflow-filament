<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Filament\Contracts\PermissionChecker;
use DbflowLabs\Filament\Contracts\StatusBadgeMapper;
use DbflowLabs\Filament\Contracts\UserDisplayResolver;
use DbflowLabs\Filament\Contracts\WorkflowableLabelResolver;
use DbflowLabs\Filament\Support\AllowAllPermissionChecker;
use DbflowLabs\Filament\Support\DefaultStatusBadgeMapper;
use DbflowLabs\Filament\Support\DefaultUserDisplayResolver;
use DbflowLabs\Filament\Support\DefaultWorkflowableLabelResolver;
use DbflowLabs\Filament\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class DBFlowFilamentContractsTest extends TestCase
{
    #[Test]
    public function default_permission_checker_resolves_from_container(): void
    {
        $checker = app(PermissionChecker::class);

        $this->assertInstanceOf(AllowAllPermissionChecker::class, $checker);
        $this->assertTrue($checker->can(new \stdClass, 'view_any'));
    }

    #[Test]
    public function default_workflowable_label_resolver_returns_safe_fallback(): void
    {
        $resolver = app(WorkflowableLabelResolver::class);

        $this->assertInstanceOf(DefaultWorkflowableLabelResolver::class, $resolver);
        $this->assertSame('—', $resolver->labelFor(null));
    }

    #[Test]
    public function default_user_display_resolver_uses_configured_attribute(): void
    {
        config(['dbflow-filament.user_name_attribute' => 'display_name']);

        $resolver = app(UserDisplayResolver::class);

        $this->assertInstanceOf(DefaultUserDisplayResolver::class, $resolver);
        $this->assertSame('Taylor', $resolver->displayName((object) [
            'display_name' => 'Taylor',
            'email' => 'taylor@example.com',
        ]));
    }

    #[Test]
    public function default_status_badge_mapper_uses_package_translations(): void
    {
        $mapper = app(StatusBadgeMapper::class);

        $this->assertInstanceOf(DefaultStatusBadgeMapper::class, $mapper);
        $this->assertSame('Pending Approval', $mapper->labelFor('pending'));
        $this->assertSame('success', $mapper->colorFor('approved'));
    }

    #[Test]
    public function permission_checker_class_config_can_resolve_custom_implementation(): void
    {
        config(['dbflow-filament.permission_checker_class' => DenyAllPermissionChecker::class]);

        $checker = $this->app->make((string) config('dbflow-filament.permission_checker_class'));

        $this->assertInstanceOf(DenyAllPermissionChecker::class, $checker);
        $this->assertFalse($checker->can(new \stdClass, 'view_any'));
    }
}

final class DenyAllPermissionChecker implements PermissionChecker
{
    public function can(mixed $user, string $ability, mixed $record = null): bool
    {
        return false;
    }
}
