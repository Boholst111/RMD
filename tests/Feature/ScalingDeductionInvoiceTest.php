<?php

namespace Tests\Feature;

use App\Models\TruckLoad;
use App\Models\User;
use App\Models\ScaleItem;
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
                    'grade' => 'Sawmill',
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