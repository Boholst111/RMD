<?php

namespace Tests\Feature;

use App\Models\TruckLoad;
use App\Models\User;
use App\Models\ScaleItem;
use App\Models\PriceMatrix;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScalingDeductionInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_deductions_are_saved_and_used_in_invoice(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($user)->get(route('scaling.create'))
            ->assertOk()
            ->assertSee('name="drivers_assistance" id="drivers_assistance" form="scaleForm"', false)
            ->assertSee("row.querySelector('.row-qty, .row-qty-input')", false)
            ->assertSee('control.dataset.submitName = control.name', false)
            ->assertSee('restoreRowNames(row)', false)
            ->assertSee("setAttribute('form', 'scaleForm')", false);

        $response = $this->actingAs($user)->post(route('scaling.store'), [
            'supplier_name' => 'Test Supplier',
            'truck_plate_no' => 'ABC-123',
            'date_unload' => '2026-09-24',
            'date_scaled' => '2026-09-24',
            'drivers_assistance' => '100.00',
            'expenses_deduction' => '0.00',
            'travel_paper_deduction' => '500.00',
            'trucking_deduction' => '0.00',
            'cash_advance' => '0.00',
            'other_deduction_label' => 'SNACK',
            'other_deduction_amount' => '100.00',
            'items' => [[
                'category' => 'FALCATA',
                'grade' => 'Good',
                'length' => '2.6',
                'diameter' => '20',
                'quantity' => '1',
                'volume' => '0.474',
                'total_volume' => '0.474',
                'subtotal' => '798.60',
            ]],
        ]);

        $truckLoad = TruckLoad::firstOrFail();

        $response->assertRedirect(route('scaling.invoice.print', $truckLoad->id));
        $this->assertSame(100.0, (float) $truckLoad->drivers_assistance);
        $this->assertSame(0.0, (float) $truckLoad->expenses_deduction);
        $this->assertSame(500.0, (float) $truckLoad->travel_paper_deduction);
        $this->assertSame(0.0, (float) $truckLoad->trucking_deduction);
        $this->assertSame(0.0, (float) $truckLoad->cash_advance);
        $this->assertSame(100.0, (float) $truckLoad->other_deduction_amount);
        $this->assertSame('SNACK', $truckLoad->other_deduction_label);
        $this->assertSame(600.0, (float) $truckLoad->total_deductions);
        $this->assertSame(298.6, (float) $truckLoad->net_payable);

        $this->get(route('scaling.invoice.print', $truckLoad->id))
            ->assertOk()
            ->assertSee('+ ₱ 100.00')
            ->assertSee('₱ 500.00')
            ->assertSee('SNACK')
            ->assertSee('- ₱ 600.00')
            ->assertSee('₱ 298.60');

        $this->get(route('scaling.invoice.pdf', $truckLoad->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_create_defaults_omitted_deduction_fields_to_zero(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post(route('scaling.store'), [
            'supplier_name' => 'Missing Deductions Supplier',
            'truck_plate_no' => 'ABC-000',
            'date_unload' => '2026-09-30',
            'date_scaled' => '2026-09-30',
            'items' => [[
                'category' => 'FALCATA',
                'grade' => 'Good',
                'length' => '2.6',
                'diameter' => '20',
                'quantity' => '1',
            ]],
        ]);

        $truckLoad = TruckLoad::firstOrFail();
        $response->assertRedirect(route('scaling.invoice.print', $truckLoad->id));
        $this->assertSame(0.0, (float) $truckLoad->drivers_assistance);
        $this->assertSame(0.0, (float) $truckLoad->expenses_deduction);
        $this->assertSame(0.0, (float) $truckLoad->travel_paper_deduction);
        $this->assertSame(0.0, (float) $truckLoad->trucking_deduction);
        $this->assertSame(0.0, (float) $truckLoad->cash_advance);
        $this->assertSame(0.0, (float) $truckLoad->other_deduction_amount);
        $this->assertSame(0.0, (float) $truckLoad->total_deductions);
        $this->assertSame(0.0, (float) $truckLoad->net_payable);
    }

    public function test_create_normalizes_legacy_deduction_request_names(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post(route('scaling.store'), [
            'supplier_name' => 'Legacy Deduction Supplier',
            'truck_plate_no' => 'ABC-001',
            'date_unload' => '2026-09-30',
            'date_scaled' => '2026-09-30',
            'driver_assistance' => '500.00',
            'expenses' => '500.00',
            'travel_paper' => '500.00',
            'trucking' => '500.00',
            'cash_advance' => '500.00',
            'other_deduction_label' => 'Snack',
            'other_deduction' => '500.00',
            'items' => [[
                'category' => 'FALCATA',
                'grade' => 'Good',
                'length' => '2.6',
                'diameter' => '20',
                'quantity' => '1',
                'volume' => '0.474',
                'total_volume' => '0.474',
                'subtotal' => '798.60',
            ]],
        ]);

        $truckLoad = TruckLoad::firstOrFail();
        $response->assertRedirect(route('scaling.invoice.print', $truckLoad->id));
        $this->assertSame(500.0, (float) $truckLoad->drivers_assistance);
        $this->assertSame(500.0, (float) $truckLoad->expenses_deduction);
        $this->assertSame(500.0, (float) $truckLoad->travel_paper_deduction);
        $this->assertSame(500.0, (float) $truckLoad->trucking_deduction);
        $this->assertSame(500.0, (float) $truckLoad->cash_advance);
        $this->assertSame('Snack', $truckLoad->other_deduction_label);
        $this->assertSame(500.0, (float) $truckLoad->other_deduction_amount);
        $this->assertSame(2500.0, (float) $truckLoad->total_deductions);

        $this->get(route('scaling.invoice.print', $truckLoad->id))
            ->assertOk()
            ->assertSee('+ ₱ 500.00')
            ->assertSee('Snack')
            ->assertSee('- ₱ 2,500.00');
    }

    public function test_create_persists_high_capacity_amounts_and_log_counts(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post(route('scaling.store'), [
            'supplier_name' => 'High Capacity Supplier',
            'truck_plate_no' => 'BIG-446',
            'date_unload' => '2026-09-30',
            'date_scaled' => '2026-09-30',
            'drivers_assistance' => '500000000.00',
            'expenses_deduction' => '100000000.00',
            'travel_paper_deduction' => '0.00',
            'trucking_deduction' => '0.00',
            'cash_advance' => '0.00',
            'other_deduction_label' => '',
            'other_deduction_amount' => '0.00',
            'items' => [[
                'category' => 'FALCATA',
                'grade' => 'Good',
                'length' => '2.6',
                'diameter' => '20',
                'quantity' => '446',
                'volume' => '0.081',
                'total_volume' => '36.126',
                'subtotal' => '900000000.00',
            ]],
        ]);

        $truckLoad = TruckLoad::with('scaleItems')->firstOrFail();
        $response->assertRedirect(route('scaling.invoice.print', $truckLoad->id));
        $this->assertSame(446, (int) $truckLoad->total_logs);
        $this->assertSame(500000000.0, (float) $truckLoad->drivers_assistance);
        $this->assertSame(100000000.0, (float) $truckLoad->total_deductions);
        $this->assertSame(900000000.0, (float) $truckLoad->gross_amount);
        $this->assertSame(1300000000.0, (float) $truckLoad->net_payable);
        $this->assertSame(446, (int) $truckLoad->scaleItems->first()->quantity);
        $this->assertSame(900000000.0, (float) $truckLoad->scaleItems->first()->subtotal);

        $this->get(route('scaling.invoice.print', $truckLoad->id))
            ->assertOk()
            ->assertSee('+ ₱ 500,000,000.00')
            ->assertSee('- ₱ 100,000,000.00')
            ->assertSee('₱ 1,300,000,000.00');
    }

    public function test_full_standard_and_split_matrices_preserve_deductions(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $items = [];

        for ($index = 0; $index < 33; $index++) {
            $diameter = 16 + ($index * 2);
            $items['standard_' . $index] = [
                'category' => 'FALCATA',
                'grade' => 'Good',
                'length' => '2.6',
                'diameter' => (string) $diameter,
                'quantity' => $index === 20 ? '1.0' : '1',
            ];

            $splitGroup = 'matrix_split_' . $index;
            foreach (['A', 'B'] as $side) {
                $items['split_' . $index . '_' . $side] = [
                    'category' => 'FALCATA',
                    'grade' => $side === 'A' ? 'Good' : 'Sawmill',
                    'length' => '1.3',
                    'diameter' => (string) $diameter,
                    'quantity' => '1',
                    'is_split' => '1',
                    'split_group_id' => $splitGroup,
                    'split_side' => $side,
                ];
            }
        }

        $response = $this->actingAs($user)->post(route('scaling.store'), [
            'supplier_name' => 'Full Matrix Supplier',
            'truck_plate_no' => 'FULL-BOX',
            'date_unload' => '2026-09-30',
            'date_scaled' => '2026-09-30',
            'drivers_assistance' => '50000.00',
            'expenses_deduction' => '5000.00',
            'travel_paper_deduction' => '5000.00',
            'trucking_deduction' => '5000.00',
            'cash_advance' => '5000.00',
            'other_deduction_label' => 'Other',
            'other_deduction_amount' => '5000.00',
            'items' => $items,
        ]);

        $truckLoad = TruckLoad::with('scaleItems')->firstOrFail();
        $response->assertRedirect(route('scaling.invoice.print', $truckLoad->id));
        $this->assertCount(99, $truckLoad->scaleItems);
        $this->assertSame(66, (int) $truckLoad->total_logs);
        $this->assertSame(50000.0, (float) $truckLoad->drivers_assistance);
        $this->assertSame(25000.0, (float) $truckLoad->total_deductions);

        $this->get(route('scaling.invoice.print', $truckLoad->id))
            ->assertOk()
            ->assertSee('+ ₱ 50,000.00')
            ->assertSee('- ₱ 25,000.00');
    }

    public function test_full_edit_matrix_preserves_deductions(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $supplier = Supplier::create(['name' => 'Full Edit Matrix Supplier']);
        $truckLoad = TruckLoad::create([
            'supplier_id' => $supplier->id,
            'truck_plate_no' => 'EDIT-FULL',
            'scale_sheet_no' => '90004',
            'invoice_no' => 'RMD-2026-9004',
            'status' => 'completed',
            'date_unload' => '2026-09-30',
            'date_scaled' => '2026-09-30',
            'gross_amount' => 500.00,
            'net_payable' => 500.00,
        ]);
        ScaleItem::create([
            'truck_load_id' => $truckLoad->id,
            'wood_category' => 'FALCATA',
            'grade' => 'Good',
            'length' => 2.6,
            'diameter' => 20,
            'quantity' => 1,
            'volume' => 0.081,
            'total_volume' => 0.081,
            'price_per_cu_m' => 6172.84,
            'subtotal' => 500.00,
        ]);

        $items = [];
        for ($index = 0; $index < 33; $index++) {
            $diameter = 16 + ($index * 2);
            $items['standard_' . $index] = [
                'category' => 'FALCATA',
                'grade' => 'Good',
                'length' => '2.6',
                'diameter' => (string) $diameter,
                'quantity' => $index === 20 ? '1.0' : '1',
            ];

            $splitGroup = 'edit_split_' . $index;
            foreach (['A', 'B'] as $side) {
                $items['split_' . $index . '_' . $side] = [
                    'category' => 'FALCATA',
                    'grade' => $side === 'A' ? 'Good' : 'Sawmill',
                    'length' => '1.3',
                    'diameter' => (string) $diameter,
                    'quantity' => '1',
                    'is_split' => '1',
                    'split_group_id' => $splitGroup,
                    'split_side' => $side,
                ];
            }
        }

        $response = $this->actingAs($user)->put(route('scaling.update', ['scaling' => $truckLoad->id]), [
            'drivers_assistance' => '50000.00',
            'expenses_deduction' => '5000.00',
            'travel_paper_deduction' => '5000.00',
            'trucking_deduction' => '5000.00',
            'cash_advance' => '5000.00',
            'other_deduction_label' => 'Other',
            'other_deduction_amount' => '5000.00',
            'items' => $items,
        ]);

        $response->assertRedirect(route('scaling.show', ['scaling' => $truckLoad->id]));
        $truckLoad->refresh();

        $this->assertCount(99, $truckLoad->scaleItems()->get());
        $this->assertSame(50000.0, (float) $truckLoad->drivers_assistance);
        $this->assertSame(25000.0, (float) $truckLoad->total_deductions);
        $this->assertSame((float) $truckLoad->gross_amount + 25000.0, (float) $truckLoad->net_payable);

        $this->get(route('scaling.invoice.print', $truckLoad->id))
            ->assertOk()
            ->assertSee('+ ₱ 50,000.00')
            ->assertSee('- ₱ 25,000.00');
    }

    public function test_fractional_log_quantities_are_rejected(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->from(route('scaling.create'))
            ->post(route('scaling.store'), [
                'supplier_name' => 'Fractional Quantity Supplier',
                'truck_plate_no' => 'FRACTIONAL-1',
                'date_unload' => '2026-09-30',
                'date_scaled' => '2026-09-30',
                'items' => [[
                    'category' => 'FALCATA',
                    'grade' => 'Good',
                    'length' => '2.6',
                    'diameter' => '20',
                    'quantity' => '1.5',
                ]],
            ])
            ->assertSessionHasErrors('items.0.quantity');
    }

    public function test_scale_sheet_index_formats_currency_to_two_decimal_places(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $supplier = Supplier::create(['name' => 'Currency Formatting Supplier']);
        TruckLoad::create([
            'supplier_id' => $supplier->id,
            'truck_plate_no' => 'FMT-001',
            'scale_sheet_no' => '90003',
            'invoice_no' => 'RMD-2026-9003',
            'status' => 'completed',
            'date_unload' => '2026-09-30',
            'date_scaled' => '2026-09-30',
            'gross_amount' => 502201775.36,
            'total_deductions' => 0,
            'net_payable' => 502201775.36,
        ]);

        $this->actingAs($user)
            ->get(route('scaling.index'))
            ->assertOk()
            ->assertSee('₱ 502,201,775.36')
            ->assertDontSee('502,201,775.360');
    }

    public function test_editing_a_scale_sheet_updates_deductions_and_invoice_net(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $supplier = Supplier::create(['name' => 'Edit Test Supplier']);
        $truckLoad = TruckLoad::create([
            'supplier_id' => $supplier->id,
            'truck_plate_no' => 'ABC-456',
            'scale_sheet_no' => '90001',
            'invoice_no' => 'RMD-2026-9001',
            'status' => 'completed',
            'date_unload' => '2026-09-24',
            'date_scaled' => '2026-09-24',
            'gross_amount' => 9834.00,
            'total_logs' => 1,
            'total_volume' => 0.474,
            'total_deductions' => 0,
            'net_payable' => 9834.00,
        ]);
        ScaleItem::create([
            'truck_load_id' => $truckLoad->id,
            'wood_category' => 'FALCATA',
            'grade' => 'Good',
            'is_split' => false,
            'length' => 2.6,
            'diameter' => 20,
            'quantity' => 1,
            'volume' => 0.474,
            'total_volume' => 0.474,
            'price_per_cu_m' => 20746.84,
            'subtotal' => 9834.00,
        ]);

        $response = $this->actingAs($user)->put(route('scaling.update', ['scaling' => $truckLoad->id]), [
            'drivers_assistance' => '1000.00',
            'expenses_deduction' => '0.00',
            'travel_paper_deduction' => '5000.00',
            'trucking_deduction' => '0.00',
            'cash_advance' => '0.00',
            'other_deduction_label' => '',
            'other_deduction_amount' => '0.00',
        ]);

        $response->assertRedirect(route('scaling.show', ['scaling' => $truckLoad->id]));
        $truckLoad->refresh();

        $this->assertSame(1000.0, (float) $truckLoad->drivers_assistance);
        $this->assertSame(5000.0, (float) $truckLoad->travel_paper_deduction);
        $this->assertSame(5000.0, (float) $truckLoad->total_deductions);
        $this->assertSame(5834.0, (float) $truckLoad->net_payable);

        $this->get(route('scaling.invoice.print', $truckLoad->id))
            ->assertOk()
            ->assertSee('+ ₱ 1,000.00')
            ->assertSee('₱ 5,000.00')
            ->assertSee('- ₱ 5,000.00')
            ->assertSee('₱ 5,834.00');
    }

    public function test_invoice_breakdown_uses_saved_rate_when_matrix_rate_changes(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $supplier = Supplier::create(['name' => 'Historical Rate Supplier']);
        PriceMatrix::create([
            'category' => 'SAWMILL',
            'length' => 2.6,
            'dia_min' => 0,
            'dia_max' => 0,
            'price_per_cu_m' => 50000,
        ]);
        $truckLoad = TruckLoad::create([
            'supplier_id' => $supplier->id,
            'truck_plate_no' => 'HIST-RATE',
            'scale_sheet_no' => '90005',
            'invoice_no' => 'RMD-2026-9005',
            'status' => 'completed',
            'date_unload' => '2026-09-30',
            'date_scaled' => '2026-09-30',
            'gross_amount' => 500000,
            'net_payable' => 500000,
        ]);
        ScaleItem::create([
            'truck_load_id' => $truckLoad->id,
            'wood_category' => 'SAWMILL',
            'grade' => 'Sawmill',
            'length' => 2.6,
            'diameter' => 20,
            'quantity' => 1,
            'volume' => 0.5,
            'total_volume' => 0.5,
            'price_per_cu_m' => 1000000,
            'subtotal' => 500000,
        ]);

        $this->actingAs($user)
            ->get(route('scaling.invoice.print', $truckLoad->id))
            ->assertOk()
            ->assertSee('₱ 1,000,000.00')
            ->assertSee('₱ 500,000.00')
            ->assertDontSee('₱ 50,000.00');
    }

    public function test_sawmill_grade_uses_global_sawmill_rate_for_falcata_logs(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        PriceMatrix::create([
            'category' => 'FALCATA',
            'length' => 2.6,
            'dia_min' => 20,
            'dia_max' => 24,
            'price_per_cu_m' => 2000,
        ]);
        PriceMatrix::create([
            'category' => 'SAWMILL',
            'length' => 2.6,
            'dia_min' => 0,
            'dia_max' => 0,
            'price_per_cu_m' => 1800,
        ]);

        $response = $this->actingAs($user)->post(route('scaling.store'), [
            'supplier_name' => 'Sawmill Rate Supplier',
            'truck_plate_no' => 'SM-RATE-1',
            'date_unload' => '2026-09-30',
            'date_scaled' => '2026-09-30',
            'items' => [[
                'category' => 'FALCATA',
                'grade' => 'Sawmill',
                'length' => '2.6',
                'diameter' => '20',
                'quantity' => '1',
            ]],
        ]);

        $truckLoad = TruckLoad::with('scaleItems')->firstOrFail();
        $response->assertRedirect(route('scaling.invoice.print', $truckLoad->id));
        $this->assertSame(1800.0, (float) $truckLoad->scaleItems->first()->price_per_cu_m);
        $this->assertSame(145.8, (float) $truckLoad->gross_amount);

        $this->get(route('scaling.invoice.print', $truckLoad->id))
            ->assertOk()
            ->assertSee('₱ 1,800.00')
            ->assertSee('₱ 145.80');
    }

    public function test_partial_scale_sheet_update_preserves_omitted_deductions(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $supplier = Supplier::create(['name' => 'Partial Edit Supplier']);
        $truckLoad = TruckLoad::create([
            'supplier_id' => $supplier->id,
            'truck_plate_no' => 'ABC-789',
            'scale_sheet_no' => '90002',
            'invoice_no' => 'RMD-2026-9002',
            'status' => 'completed',
            'date_unload' => '2026-09-24',
            'date_scaled' => '2026-09-24',
            'gross_amount' => 1000.00,
            'drivers_assistance' => 50.00,
            'expenses_deduction' => 25.00,
            'travel_paper_deduction' => 30.00,
            'trucking_deduction' => 40.00,
            'cash_advance' => 10.00,
            'other_deduction_label' => 'Fuel',
            'other_deduction_amount' => 15.00,
            'total_deductions' => 120.00,
            'net_payable' => 930.00,
        ]);
        ScaleItem::create([
            'truck_load_id' => $truckLoad->id,
            'wood_category' => 'FALCATA',
            'grade' => 'Good',
            'is_split' => false,
            'length' => 2.6,
            'diameter' => 20,
            'quantity' => 1,
            'volume' => 0.474,
            'total_volume' => 0.474,
            'price_per_cu_m' => 2110.0,
            'subtotal' => 1000.00,
        ]);

        $this->actingAs($user)
            ->put(route('scaling.update', ['scaling' => $truckLoad->id]), ['notes' => 'Updated notes'])
            ->assertRedirect(route('scaling.show', ['scaling' => $truckLoad->id]));

        $truckLoad->refresh();
        $this->assertSame(50.0, (float) $truckLoad->drivers_assistance);
        $this->assertSame(25.0, (float) $truckLoad->expenses_deduction);
        $this->assertSame(30.0, (float) $truckLoad->travel_paper_deduction);
        $this->assertSame(40.0, (float) $truckLoad->trucking_deduction);
        $this->assertSame(10.0, (float) $truckLoad->cash_advance);
        $this->assertSame('Fuel', $truckLoad->other_deduction_label);
        $this->assertSame(15.0, (float) $truckLoad->other_deduction_amount);
        $this->assertSame(120.0, (float) $truckLoad->total_deductions);
        $this->assertSame(930.0, (float) $truckLoad->net_payable);

        $this->get(route('scaling.invoice.print', $truckLoad->id))
            ->assertOk()
            ->assertSee('+ ₱ 50.00')
            ->assertSee('Fuel')
            ->assertSee('- ₱ 120.00')
            ->assertSee('₱ 930.00');
    }

    public function test_store_preserves_split_rows_and_deductions_together(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post(route('scaling.store'), [
            'supplier_name' => 'Split Test Supplier',
            'truck_plate_no' => 'ABC-789',
            'date_unload' => '2026-09-24',
            'date_scaled' => '2026-09-24',
            'drivers_assistance' => '1000.00',
            'expenses_deduction' => '0.00',
            'travel_paper_deduction' => '500.00',
            'trucking_deduction' => '0.00',
            'cash_advance' => '0.00',
            'other_deduction_label' => 'TEST',
            'other_deduction_amount' => '100.00',
            'items' => [
                'standard' => [
                    'category' => 'FALCATA',
                    'grade' => 'Good',
                    'length' => '2.6',
                    'diameter' => '16',
                    'quantity' => '2',
                    'volume' => '0.052',
                    'total_volume' => '0.104',
                    'subtotal' => '156.00',
                ],
                'split_a' => [
                    'is_split' => '1',
                    'split_group_id' => 'split_test',
                    'split_side' => 'A',
                    'category' => 'FALCATA',
                    'grade' => 'Good',
                    'length' => '1.3',
                    'diameter' => '60',
                    'quantity' => '1',
                    'volume' => '0.408',
                    'total_volume' => '0.408',
                    'subtotal' => '1400.00',
                ],
                'split_b' => [
                    'is_split' => '1',
                    'split_group_id' => 'split_test',
                    'split_side' => 'B',
                    'category' => 'FALCATA',
                    'grade' => 'SAWMILL',
                    'length' => '1.3',
                    'diameter' => '60',
                    'quantity' => '1',
                    'volume' => '0.408',
                    'total_volume' => '0.408',
                    'subtotal' => '1538.00',
                ],
            ],
        ]);

        $truckLoad = TruckLoad::with('scaleItems')->firstOrFail();

        $response->assertRedirect(route('scaling.invoice.print', $truckLoad->id));
        $this->assertCount(3, $truckLoad->scaleItems);
        $this->assertSame(3, (int) $truckLoad->total_logs);
        $this->assertSame(0.92, (float) $truckLoad->total_volume);
        $this->assertSame(3094.0, (float) $truckLoad->gross_amount);
        $this->assertSame(600.0, (float) $truckLoad->total_deductions);
        $this->assertSame(3494.0, (float) $truckLoad->net_payable);

        $this->actingAs($user)
            ->get(route('scaling.invoice.print', $truckLoad->id))
            ->assertOk()
            ->assertSee('Sawmill (SM)');

        $this->actingAs($user)
            ->get(route('scaling.show', $truckLoad->id))
            ->assertOk()
            ->assertSee('Sawmill (SM)');
    }

    public function test_invoice_sequence_uses_existing_invoice_numbers_not_created_at(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $supplier = Supplier::create(['name' => 'Imported Invoice Supplier']);
        $invoiceYear = now()->format('Y');
        $existingLoad = TruckLoad::create([
            'supplier_id' => $supplier->id,
            'truck_plate_no' => 'OLD-0001',
            'scale_sheet_no' => '89001',
            'invoice_no' => "RMD-{$invoiceYear}-0001",
            'status' => 'completed',
            'date_unload' => now()->toDateString(),
            'date_scaled' => now()->toDateString(),
        ]);
        DB::table('truck_loads')->where('id', $existingLoad->id)->update([
            'created_at' => now()->subYear(),
        ]);
        $existingLoad->delete();

        $response = $this->actingAs($user)->post(route('scaling.store'), [
            'supplier_name' => $supplier->name,
            'truck_plate_no' => 'NEW-0002',
            'date_unload' => now()->toDateString(),
            'date_scaled' => now()->toDateString(),
            'drivers_assistance' => '0.00',
            'expenses_deduction' => '0.00',
            'travel_paper_deduction' => '0.00',
            'trucking_deduction' => '0.00',
            'cash_advance' => '0.00',
            'other_deduction_label' => '',
            'other_deduction_amount' => '0.00',
            'items' => [[
                'category' => 'FALCATA',
                'grade' => 'Good',
                'length' => '2.6',
                'diameter' => '20',
                'quantity' => '1',
                'volume' => '0.081',
                'total_volume' => '0.081',
                'subtotal' => '10.00',
            ]],
        ]);

        $newLoad = TruckLoad::where('truck_plate_no', 'NEW-0002')->firstOrFail();

        $response->assertRedirect(route('scaling.invoice.print', $newLoad->id));
        $this->assertSame("RMD-{$invoiceYear}-0002", $newLoad->invoice_no);
    }

    public function test_edit_rolls_back_deductions_when_scale_item_sync_fails(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $supplier = Supplier::create(['name' => 'Rollback Test Supplier']);
        $truckLoad = TruckLoad::create([
            'supplier_id' => $supplier->id,
            'truck_plate_no' => 'ROLLBACK-1',
            'scale_sheet_no' => '90002',
            'invoice_no' => 'RMD-2026-9002',
            'status' => 'completed',
            'date_unload' => '2026-09-24',
            'date_scaled' => '2026-09-24',
            'drivers_assistance' => 100,
            'travel_paper_deduction' => 50,
            'total_deductions' => 50,
            'gross_amount' => 500,
            'net_payable' => 550,
        ]);
        ScaleItem::create([
            'truck_load_id' => $truckLoad->id,
            'wood_category' => 'FALCATA',
            'grade' => 'Good',
            'is_split' => false,
            'length' => 2.6,
            'diameter' => 20,
            'quantity' => 1,
            'volume' => 0.081,
            'total_volume' => 0.081,
            'price_per_cu_m' => 6172.84,
            'subtotal' => 500,
        ]);

        DB::statement("CREATE TRIGGER fail_scale_item_insert BEFORE INSERT ON scale_items BEGIN SELECT RAISE(ABORT, 'forced test failure'); END");

        $response = $this->actingAs($user)->put(route('scaling.update', ['scaling' => $truckLoad->id]), [
            'drivers_assistance' => '1000.00',
            'expenses_deduction' => '0.00',
            'travel_paper_deduction' => '500.00',
            'trucking_deduction' => '0.00',
            'cash_advance' => '0.00',
            'other_deduction_label' => '',
            'other_deduction_amount' => '0.00',
            'items' => [[
                'category' => 'FALCATA',
                'grade' => 'Good',
                'length' => '2.6',
                'diameter' => '20',
                'quantity' => '1',
                'volume' => '0.081',
                'total_volume' => '0.081',
                'subtotal' => '500.00',
            ]],
        ]);
        $response->assertSessionHas('error', 'Unable to update the scale sheet. Please try again or contact support.');

        $truckLoad->refresh();

        $this->assertSame(100.0, (float) $truckLoad->drivers_assistance);
        $this->assertSame(50.0, (float) $truckLoad->travel_paper_deduction);
        $this->assertSame(50.0, (float) $truckLoad->total_deductions);
        $this->assertSame(550.0, (float) $truckLoad->net_payable);
        $this->assertCount(1, $truckLoad->scaleItems()->get());
    }
}