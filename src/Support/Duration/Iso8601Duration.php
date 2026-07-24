<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Support\Duration;

final class Iso8601Duration
{
    public const UNIT_HOURS = 'hours';

    public const UNIT_DAYS = 'days';

    public const UNIT_WEEKS = 'weeks';

    /**
     * @return array<string, string>
     */
    public static function unitOptions(): array
    {
        return [
            self::UNIT_HOURS => (string) __('dbflow-filament::dbflow-filament.duration.units.hours'),
            self::UNIT_DAYS => (string) __('dbflow-filament::dbflow-filament.duration.units.days'),
            self::UNIT_WEEKS => (string) __('dbflow-filament::dbflow-filament.duration.units.weeks'),
        ];
    }

    /**
     * @return list<array{iso: string, label: string}>
     */
    public static function presetOptions(): array
    {
        return [
            ['iso' => 'PT4H', 'label' => (string) __('dbflow-filament::dbflow-filament.duration.presets.hours_4')],
            ['iso' => 'PT12H', 'label' => (string) __('dbflow-filament::dbflow-filament.duration.presets.hours_12')],
            ['iso' => 'P1D', 'label' => (string) __('dbflow-filament::dbflow-filament.duration.presets.days_1')],
            ['iso' => 'P2D', 'label' => (string) __('dbflow-filament::dbflow-filament.duration.presets.days_2')],
            ['iso' => 'P3D', 'label' => (string) __('dbflow-filament::dbflow-filament.duration.presets.days_3')],
            ['iso' => 'P7D', 'label' => (string) __('dbflow-filament::dbflow-filament.duration.presets.days_7')],
        ];
    }

    public static function isValid(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return true;
        }

        try {
            new \DateInterval($value);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @return array{amount: int, unit: string}|array{custom: string}|null
     */
    public static function parse(string $value): ?array
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (! self::isValid($value)) {
            return ['custom' => $value];
        }

        if (preg_match('/^P(\d+)W$/', $value, $matches) === 1) {
            return [
                'amount' => (int) $matches[1],
                'unit' => self::UNIT_WEEKS,
            ];
        }

        if (preg_match('/^P(\d+)D$/', $value, $matches) === 1) {
            return [
                'amount' => (int) $matches[1],
                'unit' => self::UNIT_DAYS,
            ];
        }

        if (preg_match('/^PT(\d+)H$/', $value, $matches) === 1) {
            return [
                'amount' => (int) $matches[1],
                'unit' => self::UNIT_HOURS,
            ];
        }

        return ['custom' => $value];
    }

    public static function format(int $amount, string $unit): ?string
    {
        if ($amount < 1) {
            return null;
        }

        return match ($unit) {
            self::UNIT_HOURS => 'PT'.$amount.'H',
            self::UNIT_DAYS => 'P'.$amount.'D',
            self::UNIT_WEEKS => 'P'.$amount.'W',
            default => null,
        };
    }

    public static function preview(int $amount, string $unit): string
    {
        $translationKey = match ($unit) {
            self::UNIT_HOURS => 'dbflow-filament::dbflow-filament.duration.preview.hours',
            self::UNIT_WEEKS => 'dbflow-filament::dbflow-filament.duration.preview.weeks',
            default => 'dbflow-filament::dbflow-filament.duration.preview.days',
        };

        return (string) trans_choice($translationKey, $amount, ['count' => $amount]);
    }

    public static function seconds(string $value): ?int
    {
        if (! self::isValid($value)) {
            return null;
        }

        try {
            $interval = new \DateInterval(trim($value));
            $base = new \DateTimeImmutable('@0');

            return $base->add($interval)->getTimestamp() - $base->getTimestamp();
        } catch (\Exception) {
            return null;
        }
    }
}
