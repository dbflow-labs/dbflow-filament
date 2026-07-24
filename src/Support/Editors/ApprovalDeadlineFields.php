<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Support\Editors;

use Closure;
use DbflowLabs\Filament\Support\Duration\Iso8601Duration;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;

final class ApprovalDeadlineFields
{
    /**
     * @return list<Fieldset>
     */
    public static function schema(): array
    {
        return [
            Fieldset::make('approval_deadline')
                ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.approval_deadline_section'))
                ->schema([
                    TextInput::make('timeout_due_in')
                        ->hiddenLabel()
                        ->hidden()
                        ->dehydrateStateUsing(static function ($state, Get $get): string {
                            if ($get('timeout_enabled') !== true) {
                                return '';
                            }

                            $custom = trim((string) $get('timeout_custom'));

                            if ($custom !== '') {
                                return $custom;
                            }

                            $amount = (int) $get('timeout_amount');
                            $unit = (string) $get('timeout_unit');

                            return Iso8601Duration::format($amount, $unit) ?? trim((string) $state);
                        })
                        ->rule(static function (Get $get): Closure {
                            return static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if ($get('timeout_enabled') !== true) {
                                    return;
                                }

                                $duration = trim((string) $value);

                                if ($duration === '') {
                                    $fail((string) __('dbflow-filament::dbflow-filament.duration.validation.required'));

                                    return;
                                }

                                if (! Iso8601Duration::isValid($duration)) {
                                    $fail((string) __('dbflow-filament::dbflow-filament.duration.validation.invalid'));
                                }
                            };
                        }),
                    Toggle::make('timeout_enabled')
                        ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.approval_deadline_enabled'))
                        ->dehydrated(false)
                        ->live()
                        ->afterStateHydrated(static function (Toggle $component, mixed $state, Get $get): void {
                            $component->state(trim((string) $get('timeout_due_in')) !== '');
                        })
                        ->afterStateUpdated(static function (bool $state, Set $set, Get $get): void {
                            if (! $state) {
                                $set('timeout_due_in', '');
                                $set('timeout_on_timeout', null);
                                $set('timeout_custom', '');

                                return;
                            }

                            if (trim((string) $get('timeout_due_in')) !== '') {
                                return;
                            }

                            $set('timeout_amount', 1);
                            $set('timeout_unit', Iso8601Duration::UNIT_DAYS);
                            $set('timeout_due_in', 'P1D');
                        }),
                    Select::make('timeout_preset')
                        ->label(__('dbflow-filament::dbflow-filament.duration.presets.label'))
                        ->dehydrated(false)
                        ->options(static fn (): array => collect(Iso8601Duration::presetOptions())
                            ->mapWithKeys(static fn (array $preset): array => [$preset['iso'] => $preset['label']])
                            ->all())
                        ->placeholder(__('dbflow-filament::dbflow-filament.duration.presets.placeholder'))
                        ->native(false)
                        ->live()
                        ->visible(fn (Get $get): bool => $get('timeout_enabled') === true)
                        ->columnSpanFull()
                        ->afterStateUpdated(static function (?string $state, Set $set): void {
                            if ($state === null || $state === '') {
                                return;
                            }

                            $parsed = Iso8601Duration::parse($state);

                            if ($parsed === null || isset($parsed['custom'])) {
                                return;
                            }

                            $set('timeout_amount', $parsed['amount']);
                            $set('timeout_unit', $parsed['unit']);
                            $set('timeout_custom', '');
                            $set('timeout_due_in', $state);
                            $set('timeout_preset', null);
                        }),
                    TextInput::make('timeout_amount')
                        ->label(__('dbflow-filament::dbflow-filament.duration.amount'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(8760)
                        ->dehydrated(false)
                        ->live(onBlur: true)
                        ->visible(fn (Get $get): bool => $get('timeout_enabled') === true)
                        ->afterStateHydrated(static function (TextInput $component, mixed $state, Get $get): void {
                            $parsed = Iso8601Duration::parse((string) $get('timeout_due_in'));

                            if ($parsed !== null && isset($parsed['amount'])) {
                                $component->state($parsed['amount']);
                            }
                        })
                        ->afterStateUpdated(static function (mixed $state, Set $set, Get $get): void {
                            self::syncTimeoutDueIn($set, $get);
                        }),
                    Select::make('timeout_unit')
                        ->label(__('dbflow-filament::dbflow-filament.duration.amount_unit'))
                        ->dehydrated(false)
                        ->options(static fn (): array => Iso8601Duration::unitOptions())
                        ->default(Iso8601Duration::UNIT_DAYS)
                        ->native(false)
                        ->live()
                        ->visible(fn (Get $get): bool => $get('timeout_enabled') === true)
                        ->afterStateHydrated(static function (Select $component, mixed $state, Get $get): void {
                            $parsed = Iso8601Duration::parse((string) $get('timeout_due_in'));

                            if ($parsed !== null && isset($parsed['unit'])) {
                                $component->state($parsed['unit']);
                            }
                        })
                        ->afterStateUpdated(static function (?string $state, Set $set, Get $get): void {
                            self::syncTimeoutDueIn($set, $get);
                        }),
                    Placeholder::make('timeout_preview')
                        ->label(__('dbflow-filament::dbflow-filament.duration.preview_label'))
                        ->content(static function (Get $get): HtmlString|string {
                            if ($get('timeout_enabled') !== true) {
                                return '';
                            }

                            $custom = trim((string) $get('timeout_custom'));

                            if ($custom !== '') {
                                if (! Iso8601Duration::isValid($custom)) {
                                    return new HtmlString('<span class="text-danger-600">'.e(__('dbflow-filament::dbflow-filament.duration.validation.invalid')).'</span>');
                                }

                                return (string) __('dbflow-filament::dbflow-filament.duration.preview.custom', ['value' => $custom]);
                            }

                            $amount = (int) $get('timeout_amount');
                            $unit = (string) $get('timeout_unit');

                            if ($amount < 1 || $unit === '') {
                                return '';
                            }

                            return Iso8601Duration::preview($amount, $unit);
                        })
                        ->visible(fn (Get $get): bool => $get('timeout_enabled') === true)
                        ->columnSpanFull(),
                    TextInput::make('timeout_custom')
                        ->label(__('dbflow-filament::dbflow-filament.duration.custom_iso'))
                        ->helperText(__('dbflow-filament::dbflow-filament.duration.custom_iso_helper'))
                        ->maxLength(32)
                        ->dehydrated(false)
                        ->live(onBlur: true)
                        ->visible(fn (Get $get): bool => $get('timeout_enabled') === true)
                        ->afterStateHydrated(static function (TextInput $component, mixed $state, Get $get): void {
                            $parsed = Iso8601Duration::parse((string) $get('timeout_due_in'));

                            if ($parsed !== null && isset($parsed['custom'])) {
                                $component->state($parsed['custom']);
                            }
                        })
                        ->afterStateUpdated(static function (?string $state, Set $set): void {
                            $custom = trim((string) $state);

                            if ($custom === '') {
                                return;
                            }

                            $set('timeout_due_in', $custom);
                        }),
                    Select::make('timeout_on_timeout')
                        ->label(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.timeout_on_timeout'))
                        ->options(static fn (): array => StandardWorkflowDefinitionEditor::timeoutOnTimeoutOptions())
                        ->placeholder(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.timeout_on_timeout_audit_only'))
                        ->native(false)
                        ->helperText(__('dbflow-filament::dbflow-filament.forms.workflow_definitions.timeout_on_timeout_helper'))
                        ->visible(fn (Get $get): bool => $get('timeout_enabled') === true)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ];
    }

    private static function syncTimeoutDueIn(Set $set, Get $get): void
    {
        if ($get('timeout_enabled') !== true) {
            return;
        }

        if (trim((string) $get('timeout_custom')) !== '') {
            return;
        }

        $amount = (int) $get('timeout_amount');
        $unit = (string) $get('timeout_unit');
        $iso = Iso8601Duration::format($amount, $unit);

        if ($iso !== null) {
            $set('timeout_due_in', $iso);
        }
    }
}
