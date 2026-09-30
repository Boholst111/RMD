<?php

namespace Tests\Unit;

use App\Models\ScaleItem;
use PHPUnit\Framework\TestCase;

class ScaleItemSplitLogicTest extends TestCase
{
    public function test_split_child_rows_count_as_zero_for_totals_but_still_use_one_log_for_volume(): void
    {
        $this->assertEquals(1.0, ScaleItem::resolveEffectivePieceCount(1, true, false));
        $this->assertEquals(0.0, ScaleItem::resolveEffectivePieceCount(0, true, true));
        $this->assertEquals(1.0, ScaleItem::resolveVolumeBasisQuantity(0, true, true));
        $this->assertEquals(5.0, ScaleItem::resolveVolumeBasisQuantity(5, false, false));
    }

    public function test_one_meter_log_volumes_use_cylinder_formula_and_truncate_to_three_decimals(): void
    {
        foreach (range(16, 80) as $diameterCm) {
            $radiusMeters = ($diameterCm / 100) / 2;
            $expectedVolume = floor(pi() * ($radiusMeters ** 2) * 1000) / 1000;

            $this->assertSame(
                $expectedVolume,
                ScaleItem::calculateBreretonVolume($diameterCm, 1.0),
                "Unexpected truncated 1.0m volume for {$diameterCm}cm diameter."
            );
        }

        $this->assertSame(0.020, ScaleItem::calculateBreretonVolume(16, 1.0));
        $this->assertSame(0.477, ScaleItem::calculateBreretonVolume(78, 1.0));
    }
}
