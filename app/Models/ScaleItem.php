<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScaleItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'truck_load_id',
        'parent_log_id',
        'wood_category',
        'grade',
        'is_split',
        'split_group_id',
        'length',
        'diameter',
        'quantity',
        'volume',
        'total_volume',
        'price_per_cu_m',
        'subtotal',
    ];

    protected $casts = [
        'is_split' => 'boolean',
        'length' => 'float',
        'diameter' => 'integer',
        'quantity' => 'integer',
        'volume' => 'float',
        'total_volume' => 'float',
        'price_per_cu_m' => 'float',
        'subtotal' => 'float',
    ];

    public function truckLoad(): BelongsTo
    {
        return $this->belongsTo(TruckLoad::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_log_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_log_id');
    }

    public static function resolveEffectivePieceCount(int|float $quantity, bool $isSplit = false, bool $isChild = false): float
    {
        if (! $isSplit) {
            return (float) $quantity;
        }

        return $isChild ? 0.0 : (float) $quantity;
    }

    public static function resolveVolumeBasisQuantity(int|float $quantity, bool $isSplit = false, bool $isChild = false): float
    {
        if (! $isSplit) {
            return (float) $quantity;
        }

        return max(1.0, (float) $quantity);
    }

    /**
     * Brereton Volume Formula in Cubic Meters:
     * V = ((D1 + D2) / 2)^2 * L * 0.00007854
     * For a single standard log where D1 = D2 = diameter in cm, this becomes:
     * V = diameter^2 * L * 0.00007854
     */
    public static function calculateBreretonVolume(int $diameterCm, float $lengthM): float
    {
        if (abs($lengthM - 1.0) < 0.01) {
            $radiusMeters = ($diameterCm / 100) / 2;
            $thousandths = floor(pi() * ($radiusMeters ** 2) * $lengthM * 1000);

            return $thousandths / 1000;
        }

        // Official lookup table for standard lengths 2.6m, 1.3m, and 1.0m (values in m³)
        $lookup = [
            16 => ['2.6' => 0.052, '1.3' => 0.026, '1.0' => 0.020],
            18 => ['2.6' => 0.066, '1.3' => 0.033, '1.0' => 0.025],
            20 => ['2.6' => 0.081, '1.3' => 0.040, '1.0' => 0.031],
            22 => ['2.6' => 0.098, '1.3' => 0.049, '1.0' => 0.038],
            24 => ['2.6' => 0.117, '1.3' => 0.058, '1.0' => 0.045],
            26 => ['2.6' => 0.138, '1.3' => 0.069, '1.0' => 0.053],
            28 => ['2.6' => 0.160, '1.3' => 0.080, '1.0' => 0.061],
            30 => ['2.6' => 0.183, '1.3' => 0.091, '1.0' => 0.070],
            32 => ['2.6' => 0.209, '1.3' => 0.104, '1.0' => 0.080],
            34 => ['2.6' => 0.236, '1.3' => 0.118, '1.0' => 0.091],
            36 => ['2.6' => 0.264, '1.3' => 0.132, '1.0' => 0.101],
            38 => ['2.6' => 0.294, '1.3' => 0.147, '1.0' => 0.113],
            40 => ['2.6' => 0.326, '1.3' => 0.163, '1.0' => 0.125],
            42 => ['2.6' => 0.360, '1.3' => 0.180, '1.0' => 0.138],
            44 => ['2.6' => 0.395, '1.3' => 0.197, '1.0' => 0.152],
            46 => ['2.6' => 0.432, '1.3' => 0.216, '1.0' => 0.166],
            48 => ['2.6' => 0.470, '1.3' => 0.235, '1.0' => 0.181],
            50 => ['2.6' => 0.510, '1.3' => 0.255, '1.0' => 0.196],
            52 => ['2.6' => 0.552, '1.3' => 0.276, '1.0' => 0.212],
            54 => ['2.6' => 0.595, '1.3' => 0.297, '1.0' => 0.229],
            56 => ['2.6' => 0.640, '1.3' => 0.320, '1.0' => 0.246],
            58 => ['2.6' => 0.686, '1.3' => 0.343, '1.0' => 0.264],
            60 => ['2.6' => 0.735, '1.3' => 0.367, '1.0' => 0.283],
            62 => ['2.6' => 0.784, '1.3' => 0.392, '1.0' => 0.301],
            64 => ['2.6' => 0.836, '1.3' => 0.418, '1.0' => 0.322],
            66 => ['2.6' => 0.889, '1.3' => 0.444, '1.0' => 0.342],
            68 => ['2.6' => 0.944, '1.3' => 0.472, '1.0' => 0.363],
            70 => ['2.6' => 1.000, '1.3' => 0.500, '1.0' => 0.385],
            72 => ['2.6' => 1.058, '1.3' => 0.529, '1.0' => 0.407],
            74 => ['2.6' => 1.118, '1.3' => 0.559, '1.0' => 0.430],
            76 => ['2.6' => 1.179, '1.3' => 0.589, '1.0' => 0.453],
            78 => ['2.6' => 1.242, '1.3' => 0.621, '1.0' => 0.478],
            80 => ['2.6' => 1.306, '1.3' => 0.653, '1.0' => 0.502],
        ];

        $d = (int) $diameterCm;
        $lenKey = (string) round($lengthM, 3);

        // Normalize length key to '2.6', '1.3', or '1.0' when within small epsilon
        if (abs($lengthM - 2.6) < 0.01) $lenKey = '2.6';
        if (abs($lengthM - 1.3) < 0.01) $lenKey = '1.3';
        if (abs($lengthM - 1.0) < 0.01) $lenKey = '1.0';

        if (isset($lookup[$d]) && isset($lookup[$d][$lenKey])) {
            return round($lookup[$d][$lenKey], 3);
        }

        // Fallback to formula for non-standard lengths/diameters
        return round($diameterCm * $diameterCm * $lengthM * 0.00007854, 3);
    }
}
