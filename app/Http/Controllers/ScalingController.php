<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\PriceMatrix;
use App\Models\Category;
use App\Models\TruckLoad;
use App\Models\ScaleItem;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Str;

class ScalingController extends Controller
{
    /**
     * Dashboard / Scale Sheets Listing
     */
    /**
     * Dashboard / Scale Sheets Listing with Unified Filters
     */
    public function index(Request $request)
    {
        $baseQuery = TruckLoad::with('supplier');
        $this->applyScaleSheetFilters($baseQuery, $request);

        // Compute dynamic aggregate metrics for filtered results
        $filteredQuery = clone $baseQuery;
        $totalLoads = $filteredQuery->count();
        $totalLogsAll = (int) $filteredQuery->sum('total_logs');
        $totalVolumeAll = (float) $filteredQuery->sum('total_volume');
        $totalNetPayable = (float) $filteredQuery->sum('net_payable');

        $truckLoads = $baseQuery->latest('date_scaled')->latest('id')->paginate(15)->withQueryString();
        $suppliers = Supplier::orderBy('name')->get();

        return view('scaling.index', compact(
            'truckLoads',
            'suppliers',
            'totalLoads',
            'totalLogsAll',
            'totalVolumeAll',
            'totalNetPayable'
        ));
    }

    /**
     * Helper to apply unified filters across scale sheet queries
     */
    protected function applyScaleSheetFilters($query, Request $request)
    {
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('scale_sheet_no', 'like', "%{$search}%")
                  ->orWhere('truck_plate_no', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $dateScope = $request->input('date_scope');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if ($dateScope === 'today') {
            $query->whereDate('date_scaled', \Carbon\Carbon::today());
        } elseif ($dateScope === 'this_week') {
            $query->whereBetween('date_scaled', [\Carbon\Carbon::now()->startOfWeek()->toDateString(), \Carbon\Carbon::now()->endOfWeek()->toDateString()]);
        } elseif ($dateScope === 'this_month') {
            $query->whereMonth('date_scaled', date('m'))->whereYear('date_scaled', date('Y'));
        } elseif ($dateScope === 'custom' || ($dateFrom || $dateTo)) {
            if ($dateFrom) {
                $query->whereDate('date_scaled', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('date_scaled', '<=', $dateTo);
            }
        }

        return $query;
    }

    /**
     * Generate PDF report for selected filtered scale sheets.
     */
    public function exportPdfReport(Request $request)
    {
        $query = TruckLoad::with(['scaleItems', 'supplier']);
        $this->applyScaleSheetFilters($query, $request);

        $truckLoads = $query->orderBy('date_scaled')->get();
        $reportRows = [];

        foreach ($truckLoads as $load) {
            $dateScaled = \Carbon\Carbon::parse($load->date_scaled);
            $reportRows[] = [
                'sheet_no' => $load->scale_sheet_no ?? $load->invoice_no ?? ('#' . $load->id),
                'supplier_name' => $load->supplier->name ?? 'Unknown',
                'truck_plate' => $load->truck_plate_no,
                'scaled_date' => $dateScaled->format('M d, Y'),
                'total_logs' => (int) $load->total_logs,
                'total_volume' => (float) $load->total_volume,
                'net_payout' => (float) $load->net_payable,
            ];
        }

        $grandTotals = [
            'total_logs' => array_sum(array_column($reportRows, 'total_logs')),
            'total_volume' => array_sum(array_column($reportRows, 'total_volume')),
            'net' => array_sum(array_column($reportRows, 'net_payout')),
        ];

        $reportType = 'Summary';
        if ($request->input('date_scope') === 'this_week') {
            $reportType = 'Weekly';
        } elseif ($request->input('date_scope') === 'this_month') {
            $reportType = 'Monthly';
        } elseif ($request->input('date_scope') === 'custom' || $request->filled('date_from') || $request->filled('date_to')) {
            $reportType = 'Custom';
        }

        $periodLabel = 'Scale Sheet Summary Report';
        if ($request->input('date_scope') === 'today') {
            $periodLabel = 'Today (' . date('M d, Y') . ')';
        } elseif ($request->input('date_scope') === 'this_week') {
            $periodLabel = 'This Week (' . \Carbon\Carbon::now()->startOfWeek()->format('M d') . ' - ' . \Carbon\Carbon::now()->endOfWeek()->format('M d, Y') . ')';
        } elseif ($request->input('date_scope') === 'this_month') {
            $periodLabel = date('F Y');
        } elseif ($request->filled('date_from') || $request->filled('date_to')) {
            $fromStr = $request->date_from ? \Carbon\Carbon::parse($request->date_from)->format('M d, Y') : 'Start';
            $toStr = $request->date_to ? \Carbon\Carbon::parse($request->date_to)->format('M d, Y') : 'Present';
            $periodLabel = "{$fromStr} to {$toStr}";
        }

        $dateGenerated = \Carbon\Carbon::now()->format('M d, Y');
        $generatedBy = auth()->user()?->name ?? auth()->user()?->email ?? 'System';
        $allowedRemoteHosts = array_values(array_filter([
            parse_url(config('app.url'), PHP_URL_HOST),
        ]));

        $pdf = Pdf::loadView('reports.summary-pdf', compact('reportRows', 'grandTotals', 'periodLabel', 'dateGenerated', 'generatedBy', 'reportType'))
            ->setOption('isRemoteEnabled', true)
            ->setOption('allowedRemoteHosts', $allowedRemoteHosts)
            ->setPaper('a4', 'portrait');

        $safeLabel = Str::slug($periodLabel) ?: 'scale-sheet-report';
        $safeLabel = substr($safeLabel, 0, 120);
        $fileName = 'rmd-scale-sheet-report-' . strtolower($safeLabel) . '.pdf';

        return $pdf->download($fileName);
    }

    /**
     * Print-ready Logging & Wood Scaling Summary Report
     */
    public function printSummaryReport(Request $request)
    {
        $query = TruckLoad::with(['scaleItems', 'supplier']);
        $this->applyScaleSheetFilters($query, $request);

        $truckLoads = $query->orderBy('date_scaled')->get();
        $reportRows = [];

        foreach ($truckLoads as $load) {
            $dateScaled = \Carbon\Carbon::parse($load->date_scaled);
            $reportRows[] = [
                'sheet_no' => $load->scale_sheet_no ?? $load->invoice_no ?? ('#' . $load->id),
                'supplier_name' => $load->supplier->name ?? 'Unknown',
                'truck_plate' => $load->truck_plate_no,
                'scaled_date' => $dateScaled->format('M d, Y'),
                'total_logs' => (int) $load->total_logs,
                'total_volume' => (float) $load->total_volume,
                'net_payout' => (float) $load->net_payable,
            ];
        }

        $grandTotals = [
            'total_logs' => array_sum(array_column($reportRows, 'total_logs')),
            'total_volume' => array_sum(array_column($reportRows, 'total_volume')),
            'net' => array_sum(array_column($reportRows, 'net_payout')),
        ];

        $reportType = 'Summary';
        if ($request->input('date_scope') === 'this_week') {
            $reportType = 'Weekly';
        } elseif ($request->input('date_scope') === 'this_month') {
            $reportType = 'Monthly';
        } elseif ($request->input('date_scope') === 'custom' || $request->filled('date_from') || $request->filled('date_to')) {
            $reportType = 'Custom';
        }

        $periodLabel = 'Scale Sheet Summary Report';
        if ($request->input('date_scope') === 'today') {
            $periodLabel = 'Today (' . date('M d, Y') . ')';
        } elseif ($request->input('date_scope') === 'this_week') {
            $periodLabel = 'This Week (' . \Carbon\Carbon::now()->startOfWeek()->format('M d') . ' - ' . \Carbon\Carbon::now()->endOfWeek()->format('M d, Y') . ')';
        } elseif ($request->input('date_scope') === 'this_month') {
            $periodLabel = date('F Y');
        } elseif ($request->filled('date_from') || $request->filled('date_to')) {
            $fromStr = $request->date_from ? \Carbon\Carbon::parse($request->date_from)->format('M d, Y') : 'Start';
            $toStr = $request->date_to ? \Carbon\Carbon::parse($request->date_to)->format('M d, Y') : 'Present';
            $periodLabel = "{$fromStr} to {$toStr}";
        }

        $dateGenerated = \Carbon\Carbon::now()->format('M d, Y');
        $generatedBy = auth()->user()?->name ?? auth()->user()?->email ?? 'System';

        return view('reports.summary-print', compact(
            'reportRows',
            'grandTotals',
            'periodLabel',
            'reportType',
            'dateGenerated',
            'generatedBy'
        ));
    }

    /**
     * Show Scale Sheet Entry Form with Dynamic Matrix
     */
    public function create()
    {
        $suppliers = Supplier::orderBy('name')->get();
        $existingSuppliers = Supplier::orderBy('name')->pluck('name');
        
        // STRICT: Always fetch fresh price matrix from DB (no caching for scalers)
        $priceMatrices = PricingService::getFreshPriceMatrix();

        // Get fresh categories from price matrix
        $categories = PricingService::getAllCategories();

        // Auto-generate next Scale Sheet Number
        $lastRecord = TruckLoad::whereNotNull('scale_sheet_no')
            ->orderByRaw('CAST(scale_sheet_no AS UNSIGNED) DESC')
            ->first();

        if ($lastRecord && is_numeric($lastRecord->scale_sheet_no)) {
            $nextNumber = (int) $lastRecord->scale_sheet_no + 1;
        } else {
            $nextNumber = 89271;
        }

        $defaultSheetNo = null;
        $counter = FacadesDB::table('scale_sheet_counters')->where('name', 'scale_sheet_no')->first();
        if ($counter) {
            $defaultSheetNo = (string) ($counter->last_value + 1);
        }

        return view('scaling.create', compact('suppliers', 'existingSuppliers', 'priceMatrices', 'defaultSheetNo', 'categories'));
    }

    /**
     * Store Scale Sheet and Item Matrix in DB Transaction
     */
    protected function normalizeWholeNumberQuantities(Request $request): void
    {
        $items = $request->input('items');
        if (! is_array($items)) {
            return;
        }

        foreach ($items as &$item) {
            if (! is_array($item) || ! array_key_exists('quantity', $item)) {
                continue;
            }

            $quantity = filter_var($item['quantity'], FILTER_VALIDATE_FLOAT);
            if ($quantity === false || ! is_finite($quantity) || floor($quantity) !== $quantity) {
                continue;
            }

            if ($quantity >= PHP_INT_MIN && $quantity <= PHP_INT_MAX) {
                $item['quantity'] = sprintf('%.0f', $quantity);
            }
        }
        unset($item);

        $request->merge(['items' => $items]);
    }

    public function store(Request $request)
    {
        $this->normalizeWholeNumberQuantities($request);

        $deductionAliases = [
            'drivers_assistance' => ['driver_assistance'],
            'expenses_deduction' => ['expenses'],
            'travel_paper_deduction' => ['travel_paper'],
            'trucking_deduction' => ['trucking'],
            'cash_advance' => [],
            'other_deduction_amount' => ['other_deduction'],
        ];
        $requestInput = $request->all();
        foreach ($deductionAliases as $field => $aliases) {
            if (! array_key_exists($field, $requestInput)) {
                foreach ($aliases as $alias) {
                    if (array_key_exists($alias, $requestInput)) {
                        $requestInput[$field] = $requestInput[$alias];
                        break;
                    }
                }
            }

            $request->merge([$field => $requestInput[$field] ?? 0]);
        }

        $filteredItems = collect($request->input('items', []))
            ->filter(function ($item) {
                return isset($item['quantity']) && (int) $item['quantity'] > 0;
            })
            ->values()
            ->all();

        $request->merge(['items' => $filteredItems]);

        $validated = $request->validate([
            'supplier_name' => 'nullable|string|max:150',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'truck_plate_no' => 'required|string|max:50',
            'date_unload' => 'required|date',
            'date_scaled' => 'required|date',
            'scaled_by' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'drivers_assistance' => 'nullable|numeric|min:0|max:999999999999.99',
            'expenses_deduction' => 'nullable|numeric|min:0|max:999999999999.99',
            'travel_paper_deduction' => 'nullable|numeric|min:0|max:999999999999.99',
            'trucking_deduction' => 'nullable|numeric|min:0|max:999999999999.99',
            'cash_advance' => 'nullable|numeric|min:0|max:999999999999.99',
            'other_deduction_label' => 'nullable|string|max:150',
            'other_deduction_amount' => 'nullable|numeric|min:0|max:999999999999.99',
            'items' => 'required|array|min:1',
            'items.*.category' => 'required|string|max:120',
            'items.*.grade' => 'required|string|max:100',
            'items.*.length' => 'required|numeric|min:0.1',
            'items.*.diameter' => 'required|integer|min:1',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.is_split' => 'sometimes|boolean',
            'items.*.split_group_id' => 'nullable|string|max:100',
            'items.*.parent_log_id' => 'nullable|integer',
            'items.*.split_side' => 'nullable|string|in:A,B',
            'items.*.volume' => 'nullable|numeric|min:0|max:99999999999.9999',
            'items.*.total_volume' => 'nullable|numeric|min:0|max:99999999999.9999',
            'items.*.subtotal' => 'nullable|numeric|min:0|max:999999999999.99',
        ]);

        try {
            DB::beginTransaction();

            $rawSupplierName = trim($request->input('supplier_name') ?? '');
            if (! $rawSupplierName && $request->filled('supplier_id')) {
                $existingSupplierObj = Supplier::find($request->input('supplier_id'));
                $rawSupplierName = $existingSupplierObj ? $existingSupplierObj->name : '';
            }
            if (! $rawSupplierName) {
                $rawSupplierName = 'UNSPECIFIED SUPPLIER';
            }

            $supplier = Supplier::firstOrCreate(['name' => strtoupper($rawSupplierName)]);

            $driversAssistance = (float) ($validated['drivers_assistance'] ?? 0);
            $expensesDeduction = (float) ($validated['expenses_deduction'] ?? 0);
            $travelPaper = (float) ($validated['travel_paper_deduction'] ?? 0);
            $truckingDeduction = (float) ($validated['trucking_deduction'] ?? 0);
            $cashAdvance = (float) ($validated['cash_advance'] ?? 0);
            $otherDeductionLabel = trim($validated['other_deduction_label'] ?? '');
            $otherDeductionAmount = (float) ($validated['other_deduction_amount'] ?? 0);

            $currentYear = date('Y', strtotime($validated['date_scaled']));

            $row = FacadesDB::table('scale_sheet_counters')->where('name', 'scale_sheet_no')->lockForUpdate()->first();
            if (!$row) {
                FacadesDB::table('scale_sheet_counters')->insert(['name' => 'scale_sheet_no', 'last_value' => 89270, 'created_at' => now(), 'updated_at' => now()]);
                $row = FacadesDB::table('scale_sheet_counters')->where('name', 'scale_sheet_no')->first();
            }
            $next = $row->last_value + 1;
            FacadesDB::table('scale_sheet_counters')->where('id', $row->id)->update(['last_value' => $next, 'updated_at' => now()]);
            $sheetNo = (string) $next;

            $invoicePrefix = "RMD-{$currentYear}-";
            $lastInvoiceSequence = TruckLoad::withTrashed()
                ->where('invoice_no', 'like', $invoicePrefix . '%')
                ->pluck('invoice_no')
                ->reduce(function (int $highest, string $invoiceNumber) use ($invoicePrefix): int {
                    $sequence = substr($invoiceNumber, strlen($invoicePrefix));

                    return ctype_digit($sequence) ? max($highest, (int) $sequence) : $highest;
                }, 0);
            $invoiceNo = sprintf('RMD-%s-%04d', $currentYear, $lastInvoiceSequence + 1);

            $truckLoad = TruckLoad::create([
                'supplier_id' => $supplier->id,
                'truck_plate_no' => strtoupper(trim($request->input('truck_plate_no'))),
                'scale_sheet_no' => $sheetNo,
                'invoice_no' => $invoiceNo,
                'status' => 'completed',
                'date_unload' => $validated['date_unload'],
                'date_scaled' => $validated['date_scaled'],
                'scaled_by' => $validated['scaled_by'] ?? (Auth::user()?->name ?? 'Scaler Staff'),
                'notes' => $validated['notes'] ?? null,
                'drivers_assistance' => $driversAssistance,
                'expenses_deduction' => $expensesDeduction,
                'travel_paper_deduction' => $travelPaper,
                'trucking_deduction' => $truckingDeduction,
                'cash_advance' => $cashAdvance,
                'other_deduction_label' => $otherDeductionLabel ?: null,
                'other_deduction_amount' => $otherDeductionAmount,
            ]);

            $totalLogs = 0;
            $totalVolume = 0.0;
            $grossAmount = 0.0;
            $splitChildRows = [];
            $splitParentMap = [];

            $createScaleItem = function (array $item, ?int $parentLogId = null) use (&$truckLoad, &$totalLogs, &$totalVolume, &$grossAmount) {
                $category = strtoupper(trim($item['category'] ?? 'UNKNOWN'));
                $grade = $item['grade'] ?? 'Good';
                $length = (float) ($item['length'] ?? 2.6);
                $diameter = (int) ($item['diameter'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 0);
                $isSplit = isset($item['is_split']) && $item['is_split'];

                $volPerLog = ScaleItem::calculateBreretonVolume($diameter, $length);
                if (isset($item['volume']) && is_numeric($item['volume']) && (float) $item['volume'] > 0) {
                    $volPerLog = round((float) $item['volume'], 3);
                }

                $effectivePieceCount = ScaleItem::resolveEffectivePieceCount($quantity, $isSplit, $parentLogId !== null);
                $calculationQuantity = ScaleItem::resolveVolumeBasisQuantity($quantity, $isSplit, $parentLogId !== null);

                $submittedVolume = isset($item['volume']) && is_numeric($item['volume']) ? round((float) $item['volume'], 3) : null;
                $submittedTotalVolume = isset($item['total_volume']) && is_numeric($item['total_volume']) ? round((float) $item['total_volume'], 3) : null;
                $submittedSubtotal = isset($item['subtotal']) && is_numeric($item['subtotal']) ? round((float) $item['subtotal'], 2) : null;

                if ($submittedVolume !== null && $submittedVolume > 0) {
                    $volPerLog = $submittedVolume;
                }

                $totVol = $submittedTotalVolume;
                if ($totVol === null || $totVol <= 0) {
                    $totVol = round($volPerLog * $calculationQuantity, 3);
                }

                $rate = PriceMatrix::matchRate($category, $length, $diameter, $grade);
                $subtotal = $submittedSubtotal;
                if ($subtotal === null) {
                    $subtotal = round($totVol * $rate, 2);
                }

                if ($totVol > 0 && $subtotal !== null && $subtotal >= 0) {
                    $pricePerCuM = round($subtotal / $totVol, 2);
                } else {
                    $pricePerCuM = $rate;
                }

                $scaleItem = ScaleItem::create([
                    'truck_load_id' => $truckLoad->id,
                    'parent_log_id' => $parentLogId,
                    'wood_category' => $category,
                    'grade' => $grade,
                    'is_split' => $isSplit,
                    'split_group_id' => $item['split_group_id'] ?? null,
                    'length' => $length,
                    'diameter' => $diameter,
                    'quantity' => $quantity,
                    'volume' => $volPerLog,
                    'total_volume' => $totVol,
                    'price_per_cu_m' => $pricePerCuM,
                    'subtotal' => $subtotal,
                ]);

                $totalLogs += $effectivePieceCount;
                $totalVolume += $totVol;
                $grossAmount += $subtotal;

                return $scaleItem;
            };

            foreach ($validated['items'] as $itemKey => $item) {
                $isSplit = isset($item['is_split']) && $item['is_split'];
                $splitGroup = trim((string) ($item['split_group_id'] ?? ''));
                $splitSide = strtoupper(trim((string) ($item['split_side'] ?? 'A')));

                if ($isSplit && $splitGroup !== '' && $splitSide === 'B') {
                    $splitChildRows[$splitGroup][] = $item;
                    continue;
                }

                $parentItem = $createScaleItem($item, null);

                if ($isSplit && $splitGroup !== '') {
                    $splitParentMap[$splitGroup] = $parentItem->id;
                }
            }

            foreach ($splitChildRows as $splitGroup => $childRows) {
                $parentId = $splitParentMap[$splitGroup] ?? null;

                foreach ($childRows as $childItem) {
                    $createScaleItem($childItem, $parentId);
                }
            }

            $totalDeductions = $expensesDeduction + $travelPaper + $truckingDeduction + $cashAdvance + $otherDeductionAmount;
            $netPayable = $grossAmount - $totalDeductions + $driversAssistance;

            $truckLoad->update([
                'total_logs' => $totalLogs,
                'total_volume' => round($totalVolume, 3),
                'gross_amount' => round($grossAmount, 2),
                'total_deductions' => round($totalDeductions, 2),
                'net_payable' => round($netPayable, 2),
            ]);

            DB::commit();

            return redirect()->route('scaling.invoice.print', $truckLoad->id)
                ->with('success', "Invoice #{$truckLoad->invoice_no} (Scale Sheet #{$truckLoad->scale_sheet_no}) generated successfully!");
            } catch (\Throwable $e) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            if ($e instanceof ValidationException) {
                return redirect()->back()->withInput()->withErrors($e->errors());
            }

                Log::error($e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Unable to save the scale sheet. Please try again or contact support.');
        }
    }

    /**
     * Show the form for editing an existing scale sheet.
     * STRICT: Fetches fresh price matrix from DB (no caching for scalers)
     */
    public function edit($id)
    {
        $sheet = TruckLoad::with(['supplier', 'scaleItems'])->findOrFail($id);

        // STRICT: Always fetch fresh prices from DB for scaler edits
        $priceMatrices = PricingService::getFreshPriceMatrix();
        $categories = PricingService::getAllCategories();

        return view('scaling.edit', compact('sheet', 'priceMatrices', 'categories'));
    }

    /**
     * Update the specified scale sheet in storage.
     */
    public function update(Request $request, $id)
    {
        $this->normalizeWholeNumberQuantities($request);

        $sheet = TruckLoad::findOrFail($id);
        $validated = $request->validate([
            'drivers_assistance' => 'nullable|numeric|min:0|max:999999999999.99',
            'expenses_deduction' => 'nullable|numeric|min:0|max:999999999999.99',
            'travel_paper_deduction' => 'nullable|numeric|min:0|max:999999999999.99',
            'trucking_deduction' => 'nullable|numeric|min:0|max:999999999999.99',
            'cash_advance' => 'nullable|numeric|min:0|max:999999999999.99',
            'other_deduction_label' => 'nullable|string|max:150',
            'other_deduction_amount' => 'nullable|numeric|min:0|max:999999999999.99',
            'notes' => 'nullable|string',
            'date_scaled' => 'nullable|date',
            'date_unload' => 'nullable|date',
            'items' => 'sometimes|array',
            'items.*.category' => 'required|string|max:120',
            'items.*.grade' => 'required|string|in:Good,Sawmill',
            'items.*.length' => 'required|numeric|min:0.1',
            'items.*.diameter' => 'required|integer|min:1',
            'items.*.quantity' => 'required|integer|min:0',
            'items.*.is_split' => 'sometimes|boolean',
            'items.*.split_group_id' => 'nullable|string|max:100',
            'items.*.parent_log_id' => 'nullable|integer',
            'items.*.split_side' => 'nullable|string|in:A,B',
            'items.*.volume' => 'nullable|numeric|min:0|max:99999999999.9999',
            'items.*.total_volume' => 'nullable|numeric|min:0|max:99999999999.9999',
            'items.*.subtotal' => 'nullable|numeric|min:0|max:999999999999.99',
        ]);

        $driversAssistance = array_key_exists('drivers_assistance', $validated)
            ? (float) ($validated['drivers_assistance'] ?? 0)
            : (float) $sheet->drivers_assistance;
        $expensesDeduction = array_key_exists('expenses_deduction', $validated)
            ? (float) ($validated['expenses_deduction'] ?? 0)
            : (float) $sheet->expenses_deduction;
        $travelPaper = array_key_exists('travel_paper_deduction', $validated)
            ? (float) ($validated['travel_paper_deduction'] ?? 0)
            : (float) $sheet->travel_paper_deduction;
        $truckingDeduction = array_key_exists('trucking_deduction', $validated)
            ? (float) ($validated['trucking_deduction'] ?? 0)
            : (float) $sheet->trucking_deduction;
        $cashAdvance = array_key_exists('cash_advance', $validated)
            ? (float) ($validated['cash_advance'] ?? 0)
            : (float) $sheet->cash_advance;
        $otherDeductionLabel = array_key_exists('other_deduction_label', $validated)
            ? $validated['other_deduction_label']
            : $sheet->other_deduction_label;
        $otherDeductionAmount = array_key_exists('other_deduction_amount', $validated)
            ? (float) ($validated['other_deduction_amount'] ?? 0)
            : (float) $sheet->other_deduction_amount;

        $totalDeductions = $expensesDeduction + $travelPaper + $truckingDeduction + $cashAdvance + $otherDeductionAmount;
        $netPayable = (float) $sheet->gross_amount - $totalDeductions + $driversAssistance;

        $submittedItems = collect($validated['items'] ?? [])
            ->filter(fn ($item) => (int) $item['quantity'] > 0)
            ->values()
            ->all();

        try {
            DB::transaction(function () use (
                $sheet,
                $request,
                $driversAssistance,
                $expensesDeduction,
                $travelPaper,
                $truckingDeduction,
                $cashAdvance,
                $otherDeductionLabel,
                $otherDeductionAmount,
                $totalDeductions,
                $netPayable,
                $submittedItems
            ) {
                $sheet->update([
                    'drivers_assistance' => $driversAssistance,
                    'expenses_deduction' => $expensesDeduction,
                    'travel_paper_deduction' => $travelPaper,
                    'trucking_deduction' => $truckingDeduction,
                    'cash_advance' => $cashAdvance,
                    'other_deduction_label' => $otherDeductionLabel ?: null,
                    'other_deduction_amount' => $otherDeductionAmount,
                    'total_deductions' => round($totalDeductions, 2),
                    'net_payable' => round($netPayable, 2),
                    'notes' => $request->input('notes', $sheet->notes),
                    'date_scaled' => $request->input('date_scaled', $sheet->date_scaled),
                    'date_unload' => $request->input('date_unload', $sheet->date_unload),
                ]);

                if (empty($submittedItems)) {
                    return;
                }

                // remove existing items for this sheet
                ScaleItem::where('truck_load_id', $sheet->id)->delete();

                $totalLogs = 0;
                $totalVolume = 0.0;
                $grossAmount = 0.0;

                $splitChildRows = [];
                $splitParentMap = [];

                $createScaleItem = function (array $item, ?int $parentLogId = null) use (&$sheet, &$totalLogs, &$totalVolume, &$grossAmount) {
                    $category = strtoupper(trim($item['category'] ?? ($item['wood_category'] ?? 'UNKNOWN')));
                    $grade = $item['grade'] ?? 'Good';
                    $length = (float) ($item['length'] ?? 2.6);
                    $diameter = (int) ($item['diameter'] ?? 0);
                    $quantity = (int) ($item['quantity'] ?? 0);
                    $isSplit = isset($item['is_split']) && $item['is_split'];

                    $volPerLog = ScaleItem::calculateBreretonVolume($diameter, $length);
                    if (isset($item['volume']) && is_numeric($item['volume']) && (float) $item['volume'] > 0) {
                        $volPerLog = round((float) $item['volume'], 3);
                    }

                    $effectivePieceCount = ScaleItem::resolveEffectivePieceCount($quantity, $isSplit, $parentLogId !== null);
                    $calculationQuantity = ScaleItem::resolveVolumeBasisQuantity($quantity, $isSplit, $parentLogId !== null);

                    $submittedVolume = isset($item['volume']) && is_numeric($item['volume']) ? round((float) $item['volume'], 3) : null;
                    $submittedTotalVolume = isset($item['total_volume']) && is_numeric($item['total_volume']) ? round((float) $item['total_volume'], 3) : null;
                    $submittedSubtotal = isset($item['subtotal']) && is_numeric($item['subtotal']) ? round((float) $item['subtotal'], 2) : null;

                    if ($submittedVolume !== null && $submittedVolume > 0) {
                        $volPerLog = $submittedVolume;
                    }

                    $totVol = $submittedTotalVolume;
                    if ($totVol === null || $totVol <= 0) {
                        $totVol = round($volPerLog * $calculationQuantity, 3);
                    }

                    $rate = PriceMatrix::matchRate($category, $length, $diameter, $grade);
                    $subtotal = $submittedSubtotal;
                    if ($subtotal === null) {
                        $subtotal = round($totVol * $rate, 2);
                    }

                    if ($totVol > 0 && $subtotal !== null && $subtotal >= 0) {
                        $pricePerCuM = round($subtotal / $totVol, 2);
                    } else {
                        $pricePerCuM = $rate;
                    }

                    $scaleItem = ScaleItem::create([
                        'truck_load_id' => $sheet->id,
                        'parent_log_id' => $parentLogId,
                        'wood_category' => $category,
                        'grade' => $grade,
                        'is_split' => $isSplit,
                        'split_group_id' => $item['split_group_id'] ?? null,
                        'length' => $length,
                        'diameter' => $diameter,
                        'quantity' => $quantity,
                        'volume' => $volPerLog,
                        'total_volume' => $totVol,
                        'price_per_cu_m' => $pricePerCuM,
                        'subtotal' => $subtotal,
                    ]);

                    $totalLogs += $effectivePieceCount;
                    $totalVolume += $totVol;
                    $grossAmount += $subtotal;

                    return $scaleItem;
                };

                foreach ($submittedItems as $itemKey => $item) {
                    $isSplit = isset($item['is_split']) && $item['is_split'];
                    $splitGroup = trim((string) ($item['split_group_id'] ?? ''));
                    $splitSide = strtoupper(trim((string) ($item['split_side'] ?? 'A')));

                    if ($isSplit && $splitGroup !== '' && $splitSide === 'B') {
                        $splitChildRows[$splitGroup][] = $item;
                        continue;
                    }

                    $parentItem = $createScaleItem($item, null);

                    if ($isSplit && $splitGroup !== '') {
                        $splitParentMap[$splitGroup] = $parentItem->id;
                    }
                }

                foreach ($splitChildRows as $splitGroup => $childRows) {
                    $parentId = $splitParentMap[$splitGroup] ?? null;

                    foreach ($childRows as $childItem) {
                        $createScaleItem($childItem, $parentId);
                    }
                }

                $totalDeductions = $expensesDeduction + $travelPaper + $truckingDeduction + $cashAdvance + $otherDeductionAmount;
                $netPayable = $grossAmount - $totalDeductions + $driversAssistance;

                $sheet->update([
                    'total_logs' => $totalLogs,
                    'total_volume' => round($totalVolume, 3),
                    'gross_amount' => round($grossAmount, 2),
                    'total_deductions' => round($totalDeductions, 2),
                    'net_payable' => round($netPayable, 2),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Scaling update failed', ['error' => $e->getMessage(), 'truck_load_id' => $sheet->id]);
            return redirect()->back()->withInput()->with('error', 'Unable to update the scale sheet. Please try again or contact support.');
        }

        return redirect()->route('scaling.show', ['scaling' => $sheet->id])
            ->with('success', "Scale Sheet #{$sheet->scale_sheet_no} updated successfully.");
    }

    /**
     * Printable Official Scale Sheet & Invoice Screen
     */
    public function show($id)
    {
        $truckLoad = TruckLoad::with(['supplier', 'scaleItems'])->findOrFail($id);

        $invoiceNumber = $truckLoad->invoice_no ?? ('RMD-' . date('Y') . '-' . sprintf('%04d', $truckLoad->id));
        $preparedOn = optional($truckLoad->updated_at ?? $truckLoad->created_at)->format('M d, Y') ?? now()->format('M d, Y');
        $supplierName = $truckLoad->supplier->name ?? ($truckLoad->supplier_name ?? null) ?? 'N/A';
        $truckPlate = $truckLoad->truck_plate_no ?? $truckLoad->truck_plate ?? $truckLoad->plate_number ?? 'Empty';

        $invoiceData = $this->buildInvoiceBreakdown($truckLoad);

        return view('scaling.show', array_merge([
            'scaleSheet' => $truckLoad,
            'truckLoad' => $truckLoad,
            'invoiceNumber' => $invoiceNumber,
            'preparedOn' => $preparedOn,
            'supplierName' => $supplierName,
            'truckPlate' => $truckPlate,
        ], $invoiceData));
    }

    /**
     * Dedicated Print Preview and Official Company Layout Invoice
     */
    public function printInvoice(TruckLoad $truckLoad)
    {
        $truckLoad->loadMissing(['supplier', 'scaleItems']);
        $invoiceData = $this->buildInvoiceBreakdown($truckLoad);

        $invoiceNumber = $truckLoad->invoice_no ?? ('RMD-' . date('Y') . '-' . sprintf('%04d', $truckLoad->id));
        $preparedOn = optional($truckLoad->updated_at ?? $truckLoad->created_at)->format('M d, Y') ?? now()->format('M d, Y');
        $supplierName = $truckLoad->supplier->name ?? ($truckLoad->supplier_name ?? null) ?? 'N/A';
        $truckPlate = $truckLoad->truck_plate_no ?? $truckLoad->truck_plate ?? $truckLoad->plate_number ?? 'Empty';

        return view('scaling.show', array_merge([
            'scaleSheet' => $truckLoad,
            'truckLoad' => $truckLoad,
            'invoiceNumber' => $invoiceNumber,
            'preparedOn' => $preparedOn,
            'supplierName' => $supplierName,
            'truckPlate' => $truckPlate,
        ], $invoiceData));
    }

    public function downloadInvoicePdf(TruckLoad $truckLoad)
    {
        $truckLoad->loadMissing(['supplier', 'scaleItems']);
        $invoiceData = $this->buildInvoiceBreakdown($truckLoad);

        $invoiceNumber = $truckLoad->invoice_no ?? ('RMD-' . date('Y') . '-' . sprintf('%04d', $truckLoad->id));
        $preparedOn = optional($truckLoad->updated_at ?? $truckLoad->created_at)->format('M d, Y') ?? now()->format('M d, Y');
        $supplierName = $truckLoad->supplier->name ?? ($truckLoad->supplier_name ?? null) ?? 'N/A';
        $truckPlate = $truckLoad->truck_plate_no ?? $truckLoad->truck_plate ?? $truckLoad->plate_number ?? 'Empty';

        try {
            \Log::info('downloadInvoicePdf breakdown', [
                'truck_load_id' => $truckLoad->id,
                'scale_sheet_no' => $truckLoad->scale_sheet_no,
                'total_logs' => $truckLoad->total_logs,
                'total_volume' => $truckLoad->total_volume,
                'gross_amount' => $truckLoad->gross_amount,
                'breakdown' => $invoiceData['breakdownBrackets'],
            ]);
        } catch (\Throwable $e) {
        }

        $pdf = Pdf::loadView('scaling.invoice-pdf-template', array_merge([
            'truckLoad' => $truckLoad,
            'invoiceNumber' => $invoiceNumber,
            'preparedOn' => $preparedOn,
            'supplierName' => $supplierName,
            'truckPlate' => $truckPlate,
        ], $invoiceData))
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'chroot' => public_path(),
            'defaultFont' => 'DejaVu Sans',
        ]);

        return $pdf->download('scale-sheet-' . ($truckLoad->scale_sheet_no ?: $truckLoad->id) . '.pdf');
    }

    protected function buildInvoiceBreakdown(TruckLoad $truckLoad): array
    {
        $truckLoad->loadMissing('scaleItems');

        $bracketOrder = ['16-18', '20-24', 'Sawmill (SM)', '26-28', '30-38', '40-48', '50-58', '60-80'];
        $groupedBrackets = array_fill_keys($bracketOrder, [
            'bracket' => null,
            'pieces' => 0,
            'total_volume' => 0.0,
            'rate' => 0.0,
            'subtotal' => 0.0,
        ]);

        foreach ($bracketOrder as $bracket) {
            $groupedBrackets[$bracket]['bracket'] = $bracket;
        }

        foreach ($truckLoad->scaleItems as $item) {
            $grade = trim((string) ($item->grade ?? 'Good'));
            $isSawmill = str_contains(strtoupper($grade), 'SAWMILL');
            $dia = (int) $item->diameter;

            if ($isSawmill) {
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

            $groupedBrackets[$b]['pieces'] += ScaleItem::resolveEffectivePieceCount((float) $item->quantity, (bool) $item->is_split, !is_null($item->parent_log_id));
            $groupedBrackets[$b]['total_volume'] += (float) $item->total_volume;

            $groupedBrackets[$b]['subtotal'] += (float) $item->subtotal;
        }

        foreach ($groupedBrackets as &$bracket) {
            if ($bracket['total_volume'] > 0) {
                $bracket['rate'] = round($bracket['subtotal'] / $bracket['total_volume'], 2);
            }
        }
        unset($bracket);

        // Preserve bracket totals even when child split rows have zero piece counts.
        // The subtotal and volume must remain if any positive total_volume is present.
        $breakdownBrackets = array_values(array_filter($groupedBrackets, fn($row) => ($row['pieces'] > 0 || $row['total_volume'] > 0 || $row['subtotal'] > 0)));
        if (empty($breakdownBrackets)) {
            $breakdownBrackets = array_values($groupedBrackets);
        }

        $calculatedGrossAmount = round(array_sum(array_column($breakdownBrackets, 'subtotal')), 2);
        $calculatedDeductions = round(
            (float) $truckLoad->expenses_deduction
            + (float) $truckLoad->travel_paper_deduction
            + (float) $truckLoad->trucking_deduction
            + (float) $truckLoad->cash_advance
            + (float) $truckLoad->other_deduction_amount,
            2
        );
        $calculatedNetPayable = round($calculatedGrossAmount - $calculatedDeductions + (float) $truckLoad->drivers_assistance, 2);

        return [
            'breakdownBrackets' => $breakdownBrackets,
            'calculatedGrossAmount' => $calculatedGrossAmount,
            'calculatedDeductions' => $calculatedDeductions,
            'calculatedNetPayable' => $calculatedNetPayable,
        ];
    }


    protected function resolvePeriodLabel(?string $scope, ?string $week, ?string $month, ?string $year): string
    {
        $scope = strtolower(trim((string) ($scope ?? '')));
        $year = $year ?: date('Y');

        if ($scope === 'weekly' && $week) {
            // week expected in 'WW' format (ISO week)
            try {
                $weekStart = \Carbon\Carbon::now()->setISODate((int) $year, (int) $week)->startOfWeek(\Carbon\Carbon::MONDAY);
                return 'Week ' . $week . ' (' . $weekStart->format('M d') . ' - ' . $weekStart->copy()->endOfWeek(\Carbon\Carbon::SUNDAY)->format('M d, Y') . ')';
            } catch (\Exception $e) {
                Log::warning('Failed to parse ISO week for period label', ['week' => $week, 'year' => $year, 'error' => $e->getMessage(), 'user_id' => auth()->id()]);
                // fallback label
                return 'Week ' . ($week ?: 'N/A') . ' ' . $year;
            }
        }

        if ($scope === 'monthly' && !empty($month)) {
            try {
                return \Carbon\Carbon::createFromDate((int) $year, (int) $month, 1)->translatedFormat('F Y');
            } catch (\Exception $e) {
                return (string) $year;
            }
        }

        if ($scope === 'yearly') {
            return (string) $year;
        }

        return \Carbon\Carbon::createFromDate((int) $year, 1, 1)->translatedFormat('Y');
    }

    /**
     * Delete a Scale Sheet record
     */
    public function destroy(TruckLoad $truckLoad)
    {
        if (auth()->user()?->role !== 'super_admin') {
            abort(403, 'Unauthorized. Only Super Admin can delete scale sheets.');
        }

        $sheetNo = $truckLoad->scale_sheet_no;
            DB::transaction(function () use ($truckLoad) {
                $truckLoad->delete();
            });

        return redirect()->route('scaling.index')
            ->with('success', "Scale Sheet #{$sheetNo} deleted successfully.");
    }
}
