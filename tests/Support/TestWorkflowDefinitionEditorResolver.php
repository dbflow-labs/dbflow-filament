<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Support;

use DbflowLabs\Filament\Contracts\WorkflowDefinitionEditorResolver;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

final class TestWorkflowDefinitionEditorResolver implements WorkflowDefinitionEditorResolver
{
    /**
     * @var list<Component>|null
     */
    private static ?array $components = null;

    /**
     * @var array<string, mixed>|null
     */
    public static ?array $lastContext = null;

    /**
     * @param  list<Component>  $components
     */
    public static function using(array $components): self
    {
        self::$components = $components;

        return new self();
    }

    public static function reset(): void
    {
        self::$components = null;
        self::$lastContext = null;
    }

    public function resolve(array $context): array
    {
        self::$lastContext = $context;

        return self::$components ?? [];
    }
}

final class EmptyWorkflowDefinitionEditorResolver implements WorkflowDefinitionEditorResolver
{
    public function resolve(array $context): array
    {
        return [];
    }
}

final class MarkerWorkflowDefinitionEditorResolver implements WorkflowDefinitionEditorResolver
{
    public function resolve(array $context): array
    {
        return [
            TextInput::make('custom_definition_editor_marker')
                ->label('Custom Definition Editor Marker'),
        ];
    }
}
