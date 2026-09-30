<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\TruckLoad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScaleSheetSummaryPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_pdf_route_returns_a_rendered_pdf(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $supplier = Supplier::create(['name' => 'PDF Summary Supplier']);
        TruckLoad::create([
            'supplier_id' => $supplier->id,
            'truck_plate_no' => 'PDF-123',
            'scale_sheet_no' => 'PDF-89290',
            'invoice_no' => 'RMD-PDF-89290',
            'status' => 'completed',
            'date_unload' => '2026-09-30',
            'date_scaled' => '2026-09-30',
            'total_logs' => 446,
            'total_volume' => 36.126,
            'gross_amount' => 900000000,
            'total_deductions' => 100000000,
            'net_payable' => 1300000000,
        ]);

        $response = $this->actingAs($user)->get(route('scaling.reports.pdf', [
            'date_scope' => 'custom',
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_summary_views_use_two_decimal_currency_and_keep_closing_sections_together(): void
    {
        $data = [
            'reportRows' => [[
                'sheet_no' => 'PDF-1',
                'supplier_name' => 'PDF Summary Supplier',
                'truck_plate' => 'PDF-123',
                'scaled_date' => 'Sep 30, 2026',
                'total_logs' => 446,
                'total_volume' => 36.126,
                'net_payout' => 502201775.36,
            ]],
            'grandTotals' => [
                'total_logs' => 446,
                'total_volume' => 36.126,
                'net' => 502201775.36,
            ],
            'periodLabel' => 'September 2026',
            'dateGenerated' => 'Sep 30, 2026',
            'generatedBy' => 'Admin',
            'reportType' => 'Monthly',
        ];

        $pdfHtml = view('reports.summary-pdf', $data)->render();
        $printHtml = view('reports.summary-print', $data)->render();

        $this->assertStringContainsString('PHP 502,201,775.36', $pdfHtml);
        $this->assertStringContainsString('₱ 502,201,775.36', $printHtml);
        $this->assertStringNotContainsString('502,201,775.360', $pdfHtml);
        $this->assertStringNotContainsString('502,201,775.360', $printHtml);
        $this->assertStringContainsString('<div class="closing-block">', $pdfHtml);
        $this->assertStringContainsString('<div class="closing-block">', $printHtml);

        $legacyPdfHtml = view('reports.pdf', [
            'reportRows' => [[
                'sheet_no' => 'PDF-1',
                'date' => 'Sep 30, 2026',
                'supplier' => 'PDF Summary Supplier',
                'truck_plate' => 'PDF-123',
                'total_pieces' => 446,
                'total_volume' => 36.126,
                'gross_amount' => 900000000,
                'total_deductions' => 100000000,
                'net_payout' => 1300000000,
            ]],
            'grandTotals' => [
                'total_volume' => 36.126,
                'gross' => 900000000,
                'deductions' => 100000000,
                'net' => 1300000000,
            ],
            'periodLabel' => 'September 2026',
        ])->render();

        $this->assertStringContainsString('PHP 1,300,000,000.00', $legacyPdfHtml);
        $this->assertStringNotContainsString('1,300,000,000.000', $legacyPdfHtml);
    }
}
