<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Unit;

use DbflowLabs\Filament\Support\Duration\Iso8601Duration;
use DbflowLabs\Filament\Tests\TestCase;

final class Iso8601DurationTest extends TestCase
{
    public function test_it_formats_hours_days_and_weeks(): void
    {
        $this->assertSame('PT24H', Iso8601Duration::format(24, Iso8601Duration::UNIT_HOURS));
        $this->assertSame('P3D', Iso8601Duration::format(3, Iso8601Duration::UNIT_DAYS));
        $this->assertSame('P2W', Iso8601Duration::format(2, Iso8601Duration::UNIT_WEEKS));
    }

    public function test_it_round_trips_week_unit(): void
    {
        $iso = Iso8601Duration::format(1, Iso8601Duration::UNIT_WEEKS);

        $this->assertSame('P1W', $iso);
        $this->assertSame(
            ['amount' => 1, 'unit' => Iso8601Duration::UNIT_WEEKS],
            Iso8601Duration::parse((string) $iso),
        );
    }

    public function test_it_parses_common_iso_values(): void
    {
        $this->assertSame(
            ['amount' => 24, 'unit' => Iso8601Duration::UNIT_HOURS],
            Iso8601Duration::parse('PT24H'),
        );
        $this->assertSame(
            ['amount' => 1, 'unit' => Iso8601Duration::UNIT_DAYS],
            Iso8601Duration::parse('P1D'),
        );
        $this->assertSame(
            ['amount' => 2, 'unit' => Iso8601Duration::UNIT_WEEKS],
            Iso8601Duration::parse('P2W'),
        );
    }

    public function test_it_treats_empty_value_as_disabled(): void
    {
        $this->assertNull(Iso8601Duration::parse(''));
        $this->assertTrue(Iso8601Duration::isValid(''));
    }

    public function test_it_preserves_unrecognized_values_as_custom(): void
    {
        $this->assertSame(['custom' => 'P1DT12H'], Iso8601Duration::parse('P1DT12H'));
    }
}
