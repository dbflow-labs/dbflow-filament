<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Filament\Contracts\UserAssigneeOptionsResolver;
use DbflowLabs\Filament\Support\DefaultUserAssigneeOptionsResolver;
use DbflowLabs\Filament\Support\Editors\StandardWorkflowDefinitionEditor;
use DbflowLabs\Filament\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

final class UserAssigneeOptionsResolverTest extends TestCase
{
    #[Test]
    public function default_resolver_returns_empty_options(): void
    {
        $resolver = new DefaultUserAssigneeOptionsResolver();

        $this->assertSame([], $resolver->options());
    }

    #[Test]
    public function contract_is_registered_in_service_container(): void
    {
        $resolver = app(UserAssigneeOptionsResolver::class);

        $this->assertInstanceOf(DefaultUserAssigneeOptionsResolver::class, $resolver);
    }

    #[Test]
    public function standard_editor_exposes_user_assignee_select_helper_when_resolver_is_used(): void
    {
        $source = (string) file_get_contents(
            (new ReflectionClass(StandardWorkflowDefinitionEditor::class))->getFileName(),
        );

        $this->assertStringContainsString(UserAssigneeOptionsResolver::class, $source);
        $this->assertStringContainsString('assignee_user', $source);
    }
}
