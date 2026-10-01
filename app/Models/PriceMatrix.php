<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceMatrix extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'length',
        'dia_min',
        'dia_max',
        'price_per_cu_m',
    ];

    protected $casts = [
        'length' => 'float',
        'dia_min' => 'integer',
        'dia_max' => 'integer',
        'price_per_cu_m' => 'float',
    ];

    /**
     * Helper to resolve matching rate for a category, length, diameter, and grade.
     * First queries dynamic PriceMatrix DB records set by Super Admin, then falls back to defaults.
     */
    public static function matchRate(string $category, float $length, int $diameter, string $grade = 'Good'): float
    {
        $normalizedGrade = strtoupper(trim($grade));
        $normalizedCategory = strtoupper(trim($category));

        if ($normalizedGrade === 'SAWMILL' || $normalizedGrade === 'SAWMILL (SM)') {
            $exactSawmillMatch = static::where('category', 'SAWMILL')
                ->whereBetween('length', [$length - 0.05, $length + 0.05])
                ->where('dia_min', 0)
                ->where('dia_max', 0)
                ->value('price_per_cu_m');

            if ($exactSawmillMatch !== null) {
                return (float) $exactSawmillMatch;
            }

            $fallbackSawmillMatch = static::where('category', 'SAWMILL')
                ->where('dia_min', 0)
                ->where('dia_max', 0)
                ->value('price_per_cu_m');

            if ($fallbackSawmillMatch !== null) {
                return (float) $fallbackSawmillMatch;
            }

            return 0.00;
        }

        // Query database for matching category/length/diameter range, preferring exact length matches first.
        $exactLengthMatch = static::where(function ($q) use ($normalizedCategory) {
            $q->where('category', $normalizedCategory)
              ->orWhere('category', 'FALCATA');
        })
        ->whereBetween('length', [$length - 0.05, $length + 0.05])
        ->where('dia_min', '<=', $diameter)
        ->where('dia_max', '>=', $diameter)
        ->orderByRaw("category = ? DESC", [$normalizedCategory])
        ->value('price_per_cu_m');

        if ($exactLengthMatch !== null) {
            return (float) $exactLengthMatch;
        }

        $fallbackMatch = static::where(function ($q) use ($normalizedCategory) {
            $q->where('category', $normalizedCategory)
              ->orWhere('category', 'FALCATA');
        })
        ->where('dia_min', '<=', $diameter)
        ->where('dia_max', '>=', $diameter)
        ->orderByRaw("category = ? DESC", [$normalizedCategory])
        ->value('price_per_cu_m');

        if ($fallbackMatch !== null) {
            return (float) $fallbackMatch;
        }

        // No explicit DB match found — return 0.00 so callers treat it as "no rate set".
        return 0.00;
    }
}
