<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\PriceMatrix;
use App\Models\TruckLoad;
use App\Models\ScaleItem;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class WoodScalingSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('WoodScalingSeeder is for non-production environments only.');
        }

        // 0. Create Default Users (Super Admin & Admin Scaler)
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@rmd.com'],
            [
                'name' => 'Super Admin Master',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'status' => 'active',
            ]
        );

        $scalerAdmin = User::firstOrCreate(
            ['email' => 'scaler@rmd.com'],
            [
                'name' => 'Scaler Staff',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        if ($superAdmin->wasRecentlyCreated || $scalerAdmin->wasRecentlyCreated) {
            AuditLog::create([
                'user_id' => $superAdmin->id,
                'user_name' => $superAdmin->name,
                'action' => 'System Initialized',
                'details' => 'Created initial Super Admin and Scaler Admin accounts.',
                'ip_address' => '127.0.0.1',
            ]);
        }

        // 1. Create Suppliers
        $aldo = Supplier::firstOrCreate([
            'name' => 'ALDO BEHING',
        ], [
            'contact_no' => '0917-555-0192',
            'address' => 'Butuan City, Agusan del Norte',
        ]);

        $juan = Supplier::firstOrCreate([
            'name' => 'JUAN DELA CRUZ',
        ], [
            'contact_no' => '0928-888-2104',
            'address' => 'Prosperidad, Agusan del Sur',
        ]);

        $agusan = Supplier::firstOrCreate([
            'name' => 'AGUSAN TIMBER SUPPLIES',
        ], [
            'contact_no' => '0905-123-4567',
            'address' => 'San Francisco, Agusan del Sur',
        ]);

        // 2. Refresh the demo FALCATA and SAWMILL rows without deleting custom categories.
        $falcataRanges = [
            ['min' => 16, 'max' => 18, 'price' => 1400.00],
            ['min' => 20, 'max' => 24, 'price' => 1800.00],
            ['min' => 26, 'max' => 28, 'price' => 2350.00],
            ['min' => 30, 'max' => 38, 'price' => 2850.00],
            ['min' => 40, 'max' => 48, 'price' => 3150.00],
            ['min' => 50, 'max' => 58, 'price' => 3250.00],
            ['min' => 60, 'max' => 999, 'price' => 3350.00],
        ];

        $lengths = [1.30, 2.60];

        foreach ($lengths as $len) {
            foreach ($falcataRanges as $r) {
                PriceMatrix::updateOrCreate(
                    [
                        'category' => 'FALCATA',
                        'length' => $len,
                        'dia_min' => $r['min'],
                        'dia_max' => $r['max'],
                    ],
                    [
                        'price_per_cu_m' => $r['price'],
                    ]
                );
            }

            // Sawmill Grade spec for Falcata
            PriceMatrix::updateOrCreate(
                [
                    'category' => 'SAWMILL',
                    'length' => $len,
                    'dia_min' => 0,
                    'dia_max' => 0,
                ],
                [
                    'price_per_cu_m' => 1800.00,
                ]
            );
        }

        // 3. Create Sample Scale Sheet (Truck Load)
        $load = TruckLoad::firstOrCreate(
            ['invoice_no' => 'RMD-2026-0001'],
            [
                'supplier_id' => $aldo->id,
                'truck_plate_no' => 'ADH-2525',
                'scale_sheet_no' => '089271',
                'status' => 'completed',
                'date_unload' => Carbon::now()->subDays(1),
                'date_scaled' => Carbon::now(),
                'drivers_assistance' => 500.00,
                'expenses_deduction' => 250.00,
                'travel_paper_deduction' => 300.00,
                'trucking_deduction' => 1200.00,
                'cash_advance' => 0.00,
                'scaled_by' => 'J. Boholst (Scaler)',
                'notes' => 'First batch delivery of Falcata and Lauan logs.',
            ]
        );

        if ($load->wasRecentlyCreated) {
            $itemsData = [
                ['cat' => 'FALCATA', 'len' => 2.50, 'dia' => 24, 'qty' => 10],
                ['cat' => 'FALCATA', 'len' => 2.50, 'dia' => 32, 'qty' => 8],
                ['cat' => 'FALCATA', 'len' => 3.00, 'dia' => 42, 'qty' => 5],
                ['cat' => 'LAUAN',   'len' => 2.50, 'dia' => 28, 'qty' => 6],
            ];

            $totalLogs = 0;
            $totalVol = 0.0;
            $grossVal = 0.0;

            foreach ($itemsData as $item) {
                $volPerLog = ScaleItem::calculateBreretonVolume($item['dia'], $item['len']);
                $totVol = round($volPerLog * $item['qty'], 3);
                $rate = PriceMatrix::matchRate($item['cat'], $item['len'], $item['dia']);
                $subtotal = round($totVol * $rate, 3);

                ScaleItem::create([
                    'truck_load_id' => $load->id,
                    'wood_category' => $item['cat'],
                    'length' => $item['len'],
                    'diameter' => $item['dia'],
                    'quantity' => $item['qty'],
                    'volume' => $volPerLog,
                    'total_volume' => $totVol,
                    'price_per_cu_m' => $rate,
                    'subtotal' => $subtotal,
                ]);

                $totalLogs += $item['qty'];
                $totalVol += $totVol;
                $grossVal += $subtotal;
            }

            $cashAdvance = 0.00;
            $totalDeductions = 500.00 + 250.00 + 300.00 + 1200.00 + $cashAdvance;
            $netPayable = $grossVal - $totalDeductions + 500.00;

            $load->update([
                'total_logs' => $totalLogs,
                'total_volume' => round($totalVol, 3),
                'gross_amount' => round($grossVal, 3),
                'total_deductions' => round($totalDeductions, 3),
                'cash_advance' => $cashAdvance,
                'net_payable' => round($netPayable, 3),
            ]);
        }
    }
}
