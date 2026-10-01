<?php

namespace Database\Seeders;

use App\Models\PriceMatrix;
use Illuminate\Database\Seeder;

class PriceMatrixSeeder extends Seeder
{
    public function run(): void
    {
        $prices = [
            ['category' => 'FALCATA', 'dia_min' => 16, 'dia_max' => 18, 'price_per_cu_m' => 1000.00],
            ['category' => 'FALCATA', 'dia_min' => 20, 'dia_max' => 24, 'price_per_cu_m' => 1000.00],
            ['category' => 'FALCATA', 'dia_min' => 26, 'dia_max' => 28, 'price_per_cu_m' => 1000.00],
            ['category' => 'FALCATA', 'dia_min' => 30, 'dia_max' => 38, 'price_per_cu_m' => 1000.00],
            ['category' => 'FALCATA', 'dia_min' => 40, 'dia_max' => 48, 'price_per_cu_m' => 1000.00],
            ['category' => 'FALCATA', 'dia_min' => 50, 'dia_max' => 58, 'price_per_cu_m' => 1000.00],
            ['category' => 'FALCATA', 'dia_min' => 60, 'dia_max' => 999, 'price_per_cu_m' => 1000.00],
            ['category' => 'SAWMILL', 'dia_min' => 0, 'dia_max' => 0, 'price_per_cu_m' => 1000.00],
        ];

        foreach ($prices as $item) {
            PriceMatrix::updateOrCreate(
                [
                    'category' => $item['category'],
                    'length' => 2.6,
                    'dia_min' => $item['dia_min'],
                    'dia_max' => $item['dia_max'],
                ],
                [
                    'price_per_cu_m' => $item['price_per_cu_m'],
                ]
            );
        }
    }
}
