<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\TruckLoad;
use App\Models\ScaleItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

$id = $argv[1] ?? null;
if (! $id) {
    echo "Usage: php scripts/generate_invoice_pdf.php <truck_load_id>\n";
    exit(1);
}

$truckLoad = TruckLoad::with(['supplier', 'scaleItems'])->find($id);
if (! $truckLoad) {
    echo "TruckLoad id={$id} not found.\n";
    exit(2);
}

$items = ScaleItem::where('truck_load_id', $truckLoad->id)->get();

$bracketOrder = ['16-18', '20-24', 'Sawmill (SM)', '26-28', '30-38', '40-48', '50-58', '60-80'];
$grouped = [];
foreach ($bracketOrder as $b) {
    $grouped[$b] = ['bracket' => $b, 'pieces' => 0, 'total_volume' => 0.0, 'rate' => 0.0, 'subtotal' => 0.0];
}

foreach ($items as $item) {
    $grade = $item->grade ?? 'Good';
    $dia = (int) $item->diameter;
    if ($grade === 'Sawmill') {
        $b = 'Sawmill (SM)';
    } elseif ($dia >= 16 && $dia <= 18) {
        $b = '16-18';
    } elseif ($dia <= 24) {
        $b = '20-24';
    } elseif ($dia <= 28) {
        $b = '26-28';
    } elseif ($dia <= 38) {
        $b = '30-38';
    } elseif ($dia <= 48) {
        $b = '40-48';
    } elseif ($dia <= 58) {
        $b = '50-58';
    } else {
        $b = '60-80';
    }

    $grouped[$b]['pieces'] += (int) $item->quantity;
    $grouped[$b]['total_volume'] += (float) $item->total_volume;
    $grouped[$b]['rate'] = (float) $item->price_per_cu_m;
    $grouped[$b]['subtotal'] += (float) $item->subtotal;
}

// zero-out subtotals where pieces == 0
foreach ($grouped as $k => $row) {
    if (($row['pieces'] ?? 0) <= 0) {
        $grouped[$k]['subtotal'] = 0.0;
        $grouped[$k]['total_volume'] = 0.0;
    }
}

$breakdown = array_filter($grouped, fn($r) => ($r['pieces'] ?? 0) > 0 || ($r['total_volume'] ?? 0) > 0);
if (empty($breakdown)) {
    $breakdown = $grouped;
}

$calculatedGrossAmount = round(array_sum(array_column($breakdown, 'subtotal')), 2);
$calculatedDeductions = round(
    (float) $truckLoad->expenses_deduction
    + (float) $truckLoad->travel_paper_deduction
    + (float) $truckLoad->trucking_deduction
    + (float) $truckLoad->cash_advance
    + (float) $truckLoad->other_deduction_amount,
    2
);
$calculatedNetPayable = round($calculatedGrossAmount - $calculatedDeductions + (float) $truckLoad->drivers_assistance, 2);

echo "TruckLoad id={$truckLoad->id}\n";
echo "Scale Sheet #: {$truckLoad->scale_sheet_no}\n";
echo "Total Logs (total_logs): {$truckLoad->total_logs}\n";
echo "Total Volume (total_volume): {$truckLoad->total_volume}\n";
echo "Gross Amount (gross_amount): {$truckLoad->gross_amount}\n";

echo "Breakdown:\n";
foreach ($breakdown as $b) {
    echo " - {$b['bracket']}: pieces={$b['pieces']}, volume=" . number_format($b['total_volume'],3) . ", subtotal=" . number_format($b['subtotal'],2) . "\n";
}

// generate PDF
$outDir = __DIR__ . '/../storage/app/tmp';
if (! file_exists($outDir)) {
    @mkdir($outDir, 0777, true);
}
$outPath = $outDir . '/invoice-' . $truckLoad->id . '.pdf';

$pdf = Pdf::loadView('scaling.invoice-pdf-template', [
    'truckLoad' => $truckLoad,
    'invoiceNumber' => $truckLoad->invoice_no ?? ('RMD-' . date('Y') . '-' . sprintf('%04d', $truckLoad->id)),
    'preparedOn' => optional($truckLoad->updated_at ?? $truckLoad->created_at)->format('M d, Y'),
    'breakdownBrackets' => $breakdown,
    'supplierName' => $truckLoad->supplier->name ?? 'N/A',
    'truckPlate' => $truckLoad->truck_plate_no ?? 'N/A',
    'calculatedGrossAmount' => $calculatedGrossAmount,
    'calculatedDeductions' => $calculatedDeductions,
    'calculatedNetPayable' => $calculatedNetPayable,
])
->setPaper('a4', 'portrait')
->setOptions(['isRemoteEnabled' => true, 'chroot' => public_path(), 'defaultFont' => 'DejaVu Sans']);

file_put_contents($outPath, $pdf->output());

$size = filesize($outPath) ?: 0;
echo "Saved PDF: {$outPath} (" . number_format($size/1024,2) . " KB)\n";

exit(0);
