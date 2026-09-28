@extends('layouts.app')

@section('title', 'Edit Scale Sheet - RMD Corp')

@section('content')
<div class="space-y-8">

    <!-- Top Navigation Header -->
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('scaling.index') }}" class="text-xs text-amber-400 hover:text-amber-300 font-semibold flex items-center gap-1.5 mb-2">
                <i class="fa-solid fa-arrow-left"></i> Back to Scale Sheets
            </a>
            <h1 class="text-3xl font-extrabold text-white tracking-tight flex items-center gap-3">
                <span>Edit Scale Sheet</span>
                <span class="text-xs bg-emerald-500/20 text-emerald-300 font-semibold px-3 py-1 rounded-full border border-emerald-500/30">
                    #{{ $sheet->scale_sheet_no }}
                </span>
            </h1>
        </div>
        <div class="text-right">
            <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Invoice No.</span>
            <p class="font-mono text-xl font-bold text-amber-400">{{ $sheet->invoice_no }}</p>
        </div>
    </div>

    <!-- Main Scale Sheet Entry Form (based on create.blade.php) -->
    <form action="{{ route('scaling.update', ['scaling' => $sheet->id]) }}" method="POST" id="scaleForm" class="space-y-8">
        @csrf
        @method('PUT')

        @if(session('error'))
            <div class="rounded-2xl border border-rose-500 bg-rose-950/20 p-4 text-rose-200">
                <p class="font-semibold">Submission failed:</p>
                <p class="text-sm">{{ session('error') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-500 bg-rose-950/20 p-4 text-rose-200">
                <p class="font-semibold">Please fix the following errors:</p>
                <ul class="list-disc list-inside text-sm mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- 1. Header Information Section -->
        <div class="glass-panel p-6 rounded-2xl border border-slate-800 shadow-xl space-y-6">
            <h2 class="text-lg font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
                <i class="fa-solid fa-truck-ramp-box text-amber-500"></i> Load & Delivery Specifications
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                <!-- Supplier Name (locked) -->
                <div>
                    <label for="supplier_name" class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-slate-400">
                        Supplier Name
                    </label>
                    <input 
                        type="text" 
                        name="supplier_name_display" 
                        id="supplier_name"
                        value="{{ $sheet->supplier->name ?? $sheet->supplier_name ?? 'N/A' }}"
                        class="w-full bg-slate-900 border border-slate-700 text-amber-400 font-bold text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-amber-500 outline-none uppercase cursor-not-allowed"
                        disabled
                    />
                    <input type="hidden" name="supplier_id" value="{{ $sheet->supplier_id ?? '' }}">
                </div>

                <!-- Truck Plate No (locked) -->
                <div>
                    <label for="truck_plate_no" class="block text-xs font-semibold uppercase text-slate-400 mb-1.5">Truck Plate No.</label>
                    <input type="text" name="truck_plate_no_display" id="truck_plate_no" value="{{ $sheet->truck_plate_no }}" disabled class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-100 font-mono text-sm focus:outline-none cursor-not-allowed">
                    <input type="hidden" name="truck_plate_no" value="{{ $sheet->truck_plate_no }}">
                </div>

                <!-- Scale Sheet No (readonly) -->
                <div>
                    <label for="scale_sheet_no" class="block text-xs font-semibold uppercase text-slate-400 mb-1.5">Scale Sheet No.</label>
                    <input type="text" name="scale_sheet_no" id="scale_sheet_no" value="{{ $sheet->scale_sheet_no }}" readonly class="w-full bg-slate-900/80 border border-slate-700 rounded-xl px-4 py-3 text-amber-400 font-bold font-mono text-sm focus:outline-none cursor-not-allowed">
                </div>

                <!-- Scaled By (locked) -->
                <div>
                    <label for="scaled_by" class="block text-xs font-semibold uppercase text-slate-400 mb-1.5">Scaled By (Staff)</label>
                    <input type="text" name="scaled_by_display" id="scaled_by" value="{{ $sheet->scaled_by }}" disabled class="w-full bg-slate-900 border border-slate-700 text-slate-100 text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-amber-500 outline-none cursor-not-allowed">
                    <input type="hidden" name="scaled_by" value="{{ $sheet->scaled_by }}">
                </div>

                <!-- Date Unloaded -->
                <div>
                    <label for="date_unload" class="block text-xs font-semibold uppercase text-slate-400 mb-1.5">Date Unloaded <span class="text-rose-400">*</span></label>
                    <input type="date" name="date_unload" id="date_unload" value="{{ old('date_unload', $sheet->date_unload ? \Carbon\Carbon::parse($sheet->date_unload)->format('Y-m-d') : '') }}" required class="w-full bg-slate-900 border border-slate-700 text-slate-100 text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-amber-500 outline-none">
                </div>

                <!-- Date Scaled -->
                <div>
                    <label for="date_scaled" class="block text-xs font-semibold uppercase text-slate-400 mb-1.5">Date Scaled <span class="text-rose-400">*</span></label>
                    <input type="date" name="date_scaled" id="date_scaled" value="{{ old('date_scaled', $sheet->date_scaled ? \Carbon\Carbon::parse($sheet->date_scaled)->format('Y-m-d') : '') }}" required class="w-full bg-slate-900 border border-slate-700 text-slate-100 text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-amber-500 outline-none">
                </div>
            </div>
        </div>

        <!-- 2. Dynamic Log Matrix Tally Tables (identical to create) -->
        <div class="space-y-8">
            <!-- BOX 1: STANDARD LOGS TALLY MATRIX (Top Card) -->
            <div class="glass-panel rounded-2xl border border-slate-800 shadow-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/40">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center border border-amber-500/30">
                            <i class="fa-solid fa-table-cells"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-white uppercase tracking-wide">Box 1: Standard Logs Tally Matrix</h2>
                            <p class="text-xs text-slate-400">Purely for standard, non-split logs (Default length 2.6m)</p>
                        </div>
                    </div>

                    <button type="button" id="addRowBtn" class="bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold px-4 py-2 rounded-xl transition-all shadow-md flex items-center gap-1.5">
                        <i class="fa-solid fa-plus"></i> Add Standard Log Row
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-200 min-w-[900px]" id="matrixTable">
                        <thead class="bg-slate-900 text-xs uppercase tracking-wider text-slate-400 font-semibold border-b border-slate-800">
                            <tr>
                                <th class="px-3 py-3.5 w-10 text-center">#</th>
                                <th class="px-3 py-3.5 w-32">Category</th>
                                <th class="px-3 py-3.5 w-36">Grade</th>
                                <th class="px-3 py-3.5 w-24">Length</th>
                                <th class="px-3 py-3.5 w-28">Diameter</th>
                                <th class="px-3 py-3.5 w-32 text-center">Quantity (pcs)</th>
                                <th class="px-3 py-3.5 w-28 text-right">Vol/Log (m³)</th>
                                <th class="px-3 py-3.5 w-28 text-right">Tot Vol (m³)</th>
                                <th class="px-3 py-3.5 w-32 text-right">Rate (₱/m³)</th>
                                <th class="px-3 py-3.5 w-36 text-right">Subtotal (₱)</th>
                                <th class="px-3 py-3.5 w-16 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60" id="standardMatrixBody">
                            <!-- Rows injected by JS -->
                        </tbody>
                        <tfoot class="bg-slate-900/90 font-bold border-t border-slate-700">
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-right uppercase text-xs text-slate-400">Standard Matrix Subtotals:</td>
                                <td class="px-4 py-3 text-center text-emerald-400 text-base font-mono" id="tfootTotalLogs">0</td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-right text-sky-400 font-mono text-base" id="tfootTotalVol">0.0000</td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-right text-amber-400 font-mono text-lg" id="tfootGrossSubtotal">₱ 0.00</td>
                                <td class="px-4 py-3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-slate-800 bg-slate-900/30 flex items-center justify-end gap-3">
                    <button type="button" id="refreshPricesBtn" class="text-xs bg-slate-800/60 hover:bg-slate-700 text-amber-300 rounded-xl px-3 py-2">Refresh Prices</button>
                    <span class="text-xs text-slate-400">Last prices refresh: <span id="pricesRefreshedAt">-</span></span>
                </div>
            </div>

            <!-- BOX 2: SPLIT LOGS TALLY MATRIX (Bottom Card) -->
            <div class="glass-panel rounded-2xl border border-slate-800 shadow-xl overflow-hidden border-l-4 border-l-amber-500">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-amber-950/20">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center border border-amber-500/30">
                            <i class="fa-solid fa-code-branch"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-white uppercase tracking-wide flex items-center gap-2">
                                <span>Box 2: Split Logs Tally Matrix</span>
                                <span class="text-xs bg-amber-500/20 text-amber-300 font-mono px-2 py-0.5 rounded border border-amber-500/30">1 Pair = 1 PC</span>
                            </h2>
                            <p class="text-xs text-amber-300/80 font-medium">Dual independent diameter inputs for Part A & Part B (Handles trunk taper math; 1 Pair = 1 PC)</p>
                        </div>
                    </div>

                    <button type="button" id="addSplitRowBtn" class="bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold px-4 py-2 rounded-xl transition-all shadow-md flex items-center gap-1.5">
                        <i class="fa-solid fa-plus"></i> Add Split Log Row
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-200 min-w-[1150px]" id="splitMatrixTable">
                        <thead class="bg-slate-900 text-xs uppercase tracking-wider text-slate-400 font-semibold border-b border-slate-800">
                            <tr>
                                <th class="px-3 py-3.5 w-10 text-center">#</th>
                                <th class="px-3 py-3.5 w-28">Category</th>
                                <th class="px-3 py-3.5 w-64">Part A Specs (Grade / Len / Dia)</th>
                                <th class="px-3 py-3.5 w-64">Part B Specs (Grade / Len / Dia)</th>
                                <th class="px-3 py-3.5 w-28 text-center">Quantity (pcs)</th>
                                <th class="px-3 py-3.5 w-28 text-right">Vol/Pair (m³)</th>
                                <th class="px-3 py-3.5 w-28 text-right">Tot Vol (m³)</th>
                                <th class="px-3 py-3.5 w-44 text-right">Rates (Part A / B)</th>
                                <th class="px-3 py-3.5 w-36 text-right">Subtotal (₱)</th>
                                <th class="px-3 py-3.5 w-16 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60" id="splitMatrixBody">
                            <!-- Split rows injected by JS -->
                        </tbody>
                        <tfoot class="bg-slate-900/90 font-bold border-t border-slate-700">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-right uppercase text-xs text-amber-400">Split Matrix Subtotals (1 Pair = 1 PC):</td>
                                <td class="px-4 py-3 text-center text-amber-400 text-base font-mono" id="tfootSplitTotalLogs">0</td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-right text-sky-400 font-mono text-base" id="tfootSplitTotalVol">0.0000</td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-right text-amber-400 font-mono text-lg" id="tfootSplitGrossSubtotal">₱ 0.00</td>
                                <td class="px-4 py-3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- 3. Deductions Breakdown & Financial Summary Section -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Deductions Input Panel -->
            <div class="lg:col-span-7 glass-panel p-6 rounded-2xl border border-slate-800 shadow-xl space-y-4">
                <h2 class="text-lg font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
                    <i class="fa-solid fa-calculator text-rose-400"></i> Deductions Breakdown
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Driver's Assistance -->
                    <div>
                        <label for="drivers_assistance" class="block text-xs font-semibold text-slate-400 mb-1">Driver's Assistance (₱)</label>
                        <input type="number" step="0.01" min="0" name="drivers_assistance" id="drivers_assistance" form="scaleForm" value="{{ old('drivers_assistance', $sheet->drivers_assistance ?? '0.00') }}" class="deduction-input w-full bg-slate-900 border border-slate-700 text-slate-100 font-mono text-sm rounded-xl px-4 py-2 focus:ring-2 focus:ring-amber-500 outline-none">
                    </div>

                    <!-- Expenses Deduction -->
                    <div>
                        <label for="expenses_deduction" class="block text-xs font-semibold text-slate-400 mb-1">Expenses Deduction (₱)</label>
                        <input type="number" step="0.01" min="0" name="expenses_deduction" id="expenses_deduction" form="scaleForm" value="{{ old('expenses_deduction', $sheet->expenses_deduction ?? '0.00') }}" class="deduction-input w-full bg-slate-900 border border-slate-700 text-slate-100 font-mono text-sm rounded-xl px-4 py-2 focus:ring-2 focus:ring-amber-500 outline-none">
                    </div>

                    <!-- Travel Paper Deduction -->
                    <div>
                        <label for="travel_paper_deduction" class="block text-xs font-semibold text-slate-400 mb-1">Travel Paper / Permit (₱)</label>
                        <input type="number" step="0.01" min="0" name="travel_paper_deduction" id="travel_paper_deduction" form="scaleForm" value="{{ old('travel_paper_deduction', $sheet->travel_paper_deduction ?? '0.00') }}" class="deduction-input w-full bg-slate-900 border border-slate-700 text-slate-100 font-mono text-sm rounded-xl px-4 py-2 focus:ring-2 focus:ring-amber-500 outline-none">
                    </div>

                    <!-- Trucking Deduction -->
                    <div>
                        <label for="trucking_deduction" class="block text-xs font-semibold text-slate-400 mb-1">Trucking Deduction (₱)</label>
                        <input type="number" step="0.01" min="0" name="trucking_deduction" id="trucking_deduction" form="scaleForm" value="{{ old('trucking_deduction', $sheet->trucking_deduction ?? '0.00') }}" class="deduction-input w-full bg-slate-900 border border-slate-700 text-slate-100 font-mono text-sm rounded-xl px-4 py-2 focus:ring-2 focus:ring-amber-500 outline-none">
                    </div>

                    <!-- Cash Advance -->
                    <div>
                        <label for="cash_advance" class="block text-xs font-semibold text-slate-400 mb-1">Cash Advance (₱)</label>
                        <input type="number" step="0.01" min="0" name="cash_advance" id="cash_advance" form="scaleForm" value="{{ old('cash_advance', $sheet->cash_advance ?? '0.00') }}" class="deduction-input w-full bg-slate-900 border border-slate-700 text-slate-100 font-mono text-sm rounded-xl px-4 py-2 focus:ring-2 focus:ring-amber-500 outline-none">
                    </div>

                    <!-- Other Deduction -->
                    <div>
                        <label for="other_deduction_label" class="block text-xs font-semibold text-slate-400 mb-1">Other Deduction Label</label>
                        <input type="text" name="other_deduction_label" id="other_deduction_label" form="scaleForm" value="{{ old('other_deduction_label', $sheet->other_deduction_label ?? '') }}" placeholder="e.g. Fuel / Inspection" class="w-full bg-slate-900 border border-slate-700 text-slate-100 font-mono text-sm rounded-xl px-4 py-2 focus:ring-2 focus:ring-amber-500 outline-none">
                    </div>
                    <div>
                        <label for="other_deduction_amount" class="block text-xs font-semibold text-slate-400 mb-1">Other Deduction Amount (₱)</label>
                        <input type="number" step="0.01" min="0" name="other_deduction_amount" id="other_deduction_amount" form="scaleForm" value="{{ old('other_deduction_amount', $sheet->other_deduction_amount ?? '0.00') }}" class="deduction-input w-full bg-slate-900 border border-slate-700 text-slate-100 font-mono text-sm rounded-xl px-4 py-2 focus:ring-2 focus:ring-amber-500 outline-none">
                    </div>
                </div>

                <!-- Notes / Remarks -->
                <div class="pt-2">
                    <label for="notes" class="block text-xs font-semibold text-slate-400 mb-1">Notes / Operational Remarks</label>
                    <textarea name="notes" id="notes" rows="2" placeholder="Optional notes regarding wood quality, delivery conditions..." class="w-full bg-slate-900 border border-slate-700 text-slate-200 text-sm rounded-xl p-3 focus:ring-2 focus:ring-amber-500 outline-none">{{ old('notes', $sheet->notes ?? '') }}</textarea>
                </div>
            </div>

            <!-- Financial Summary & Submit Button -->
            <div class="lg:col-span-5 glass-panel p-6 rounded-2xl border border-slate-800 shadow-xl flex flex-col justify-between space-y-6">
                <div>
                    <h2 class="text-lg font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
                        <i class="fa-solid fa-coins text-amber-400"></i> Financial Summary
                    </h2>

                    <div class="space-y-3 mt-4 text-sm">
                        <div class="flex justify-between items-center text-slate-300">
                            <span>Grand Total Log Count:</span>
                            <span class="font-mono font-bold text-white text-base" id="summaryTotalLogs">0 pcs</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-300">
                            <span>Grand Total Volume:</span>
                            <span class="font-mono font-bold text-sky-400 text-base" id="summaryTotalVol">0.0000 m³</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-300 border-t border-slate-800 pt-3">
                            <span>Gross Wood Value:</span>
                            <span class="font-mono font-bold text-white text-lg" id="summaryGrossVal">₱ {{ number_format($sheet->gross_amount, 2) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-rose-400">
                            <span>Less: Total Deductions:</span>
                            <span class="font-mono font-bold text-lg" id="summaryDeductions">- ₱ {{ number_format($sheet->total_deductions, 2) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-emerald-400">
                            <span>Add Driver's Assistance:</span>
                            <span class="font-mono font-bold text-lg" id="summaryDriverAssistance">+ ₱ {{ number_format($sheet->drivers_assistance, 2) }}</span>
                        </div>

                        <div class="bg-slate-900/90 p-4 rounded-xl border border-amber-500/40 mt-4">
                            <div class="text-xs uppercase tracking-wider text-amber-300 font-semibold mb-1">Net Amount Payable to Supplier</div>
                            <div class="text-3xl font-extrabold font-mono text-emerald-400" id="summaryNetPayable">₱ {{ number_format($sheet->net_payable, 2) }}</div>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-800 flex gap-3">
                    <a href="{{ route('scaling.index') }}" class="w-1/3 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold py-3 px-4 rounded-xl text-center text-sm transition-all">
                        Cancel
                    </a>
                    <button type="submit" class="w-2/3 wood-gradient hover:brightness-110 text-white font-extrabold py-3 px-4 rounded-xl text-sm transition-all shadow-lg shadow-amber-950/60 border border-amber-500/40 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-floppy-disk text-amber-200"></i> Save Changes
                    </button>
                </div>
            </div>

        </div>
    </form>
</div>

<input type="hidden" id="items_json" value='@json($sheet->scaleItems)'>

@push('scripts')
    @include('scaling._matrix_js')

    <script>
    (function(){
        const sheetId = '{{ $sheet->id }}';
        const draftKey = `scaling_edit_draft_${sheetId}`;

        function exportCurrentItems() {
            const out = [];
            // standard rows
            document.querySelectorAll('#standardMatrixBody tr.row-item').forEach(r => {
                const item = {
                    is_split: false,
                    category: r.querySelector('.row-cat')?.value || r.querySelector('.row-cat-select')?.value || null,
                    grade: r.querySelector('.row-grade')?.value || null,
                    length: r.querySelector('.row-len')?.value || null,
                    diameter: parseInt(r.querySelector('.row-dia')?.value || r.querySelector('.row-dia-a')?.value || 0, 10),
                    quantity: parseInt(r.querySelector('.row-qty')?.value || r.querySelector('.row-qty-input')?.value || 0, 10),
                    volume: parseFloat(r.querySelector('.row-vol-hidden')?.value || 0),
                    total_volume: parseFloat(r.querySelector('.row-total-vol-hidden')?.value || 0),
                    subtotal: parseFloat(r.querySelector('.row-subtotal-hidden')?.value || 0),
                };
                out.push(item);
            });

            // split rows (each produces two entries A and B)
            document.querySelectorAll('#splitMatrixBody tr.row-item').forEach(r => {
                const splitId = r.querySelector('input[name^="items"][name*="split_group_id"]')?.value || `split_temp_${Math.random().toString(36).slice(2,8)}`;
                const qty = parseInt(r.querySelector('.row-qty-input')?.value || 0, 10);
                const cat = r.querySelector('.row-cat-select')?.value || null;

                const a = {
                    is_split: true,
                    split_group_id: splitId,
                    split_side: 'A',
                    parent_log_id: null,
                    category: cat,
                    grade: r.querySelector('.row-grade-a')?.value || null,
                    length: r.querySelector('.row-len-a')?.value || null,
                    diameter: parseInt(r.querySelector('.row-dia-a')?.value || 0, 10),
                    quantity: qty,
                    volume: parseFloat(r.querySelector('.row-volume-hidden-a')?.value || 0),
                    total_volume: parseFloat(r.querySelector('.row-total-volume-hidden-a')?.value || 0),
                    subtotal: parseFloat(r.querySelector('.row-subtotal-hidden-a')?.value || 0),
                };
                const b = {
                    is_split: true,
                    split_group_id: splitId,
                    split_side: 'B',
                    parent_log_id: null,
                    category: cat,
                    grade: r.querySelector('.row-grade-b')?.value || null,
                    length: r.querySelector('.row-len-b')?.value || null,
                    diameter: parseInt(r.querySelector('.row-dia-b')?.value || 0, 10),
                    quantity: qty,
                    volume: parseFloat(r.querySelector('.row-volume-hidden-b')?.value || 0),
                    total_volume: parseFloat(r.querySelector('.row-total-volume-hidden-b')?.value || 0),
                    subtotal: parseFloat(r.querySelector('.row-subtotal-hidden-b')?.value || 0),
                };
                out.push(a, b);
            });

            return out;
        }

        function saveDraftDebounced() {
            if (window._saveDraftTimer) clearTimeout(window._saveDraftTimer);
            window._saveDraftTimer = setTimeout(() => {
                try {
                    const payload = {
                        items: exportCurrentItems(),
                        deductions: {
                            drivers_assistance: document.getElementById('drivers_assistance')?.value || 0,
                            expenses_deduction: document.getElementById('expenses_deduction')?.value || 0,
                            travel_paper_deduction: document.getElementById('travel_paper_deduction')?.value || 0,
                            trucking_deduction: document.getElementById('trucking_deduction')?.value || 0,
                            cash_advance: document.getElementById('cash_advance')?.value || 0,
                            other_deduction_label: document.getElementById('other_deduction_label')?.value || '',
                            other_deduction_amount: document.getElementById('other_deduction_amount')?.value || 0,
                        }
                    };
                    localStorage.setItem(draftKey, JSON.stringify(payload));
                } catch (e) { console.error('Draft save failed', e); }
            }, 700);
        }

        function bindAutosaveListeners() {
            document.querySelectorAll('#standardMatrixBody, #splitMatrixBody').forEach(el => {
                el.addEventListener('input', saveDraftDebounced);
                el.addEventListener('change', saveDraftDebounced);
            });
            document.querySelectorAll('.deduction-input, #other_deduction_label, #notes').forEach(i => {
                i.addEventListener('input', saveDraftDebounced);
            });
        }

        function clearDraft() { localStorage.removeItem(draftKey); }

        function loadDraft() {
            const raw = localStorage.getItem(draftKey);
            if (!raw) return false;
            try {
                const parsed = JSON.parse(raw);
                const items = parsed.items || [];

                // Remove any existing matrix rows
                document.querySelectorAll('#standardMatrixBody tr.row-item, #splitMatrixBody tr.row-item').forEach(r => r.remove());

                // Create rows from draft items. Group split pairs by split_group_id
                const splitGroups = {};
                items.forEach(it => {
                    if (it.is_split) {
                        const key = it.split_group_id || (`sg_${Math.random().toString(36).slice(2,8)}`);
                        splitGroups[key] = splitGroups[key] || { A: null, B: null };
                        if ((it.split_side || '').toUpperCase() === 'B') splitGroups[key].B = it; else splitGroups[key].A = it;
                    } else {
                        // standard
                        addRow({ category: it.category || defaultCategory, grade: it.grade || 'Good', is_split: false, length: it.length || '2.6', diameter: it.diameter || 20, quantity: it.quantity || 0, isPreset: false });
                        const rid = rowIndex;
                        const r = document.getElementById(`row-${rid}`);
                        if (r) {
                            if (it.volume) r.querySelector('.row-vol-hidden') && (r.querySelector('.row-vol-hidden').value = Number(it.volume).toFixed(3));
                            if (it.total_volume) r.querySelector('.row-total-vol-hidden') && (r.querySelector('.row-total-vol-hidden').value = Number(it.total_volume).toFixed(3));
                            if (it.subtotal) r.querySelector('.row-subtotal-hidden') && (r.querySelector('.row-subtotal-hidden').value = Number(it.subtotal).toFixed(2));
                        }
                    }
                });

                Object.keys(splitGroups).forEach(k => {
                    const pair = splitGroups[k];
                    if (pair.A || pair.B) {
                        const a = pair.A || { category: defaultCategory, grade: 'Good', length: '1.3', diameter: 20, quantity: 0 };
                        const b = pair.B || { category: defaultCategory, grade: 'Sawmill', length: '1.3', diameter: 20, quantity: 0 };
                        addRow({ category: a.category || defaultCategory, gradeA: a.grade || 'Good', lengthA: a.length || '1.3', diameterA: a.diameter || 20, gradeB: b.grade || 'Sawmill', lengthB: b.length || '1.3', diameterB: b.diameter || 20, is_split: true, quantity: a.quantity || b.quantity || 0, isPreset: false });
                        const rid = rowIndex;
                        const r = document.getElementById(`row-${rid}`);
                        if (r) {
                            if (a.volume) r.querySelector('.row-volume-hidden-a') && (r.querySelector('.row-volume-hidden-a').value = Number(a.volume).toFixed(3));
                            if (a.total_volume) r.querySelector('.row-total-volume-hidden-a') && (r.querySelector('.row-total-volume-hidden-a').value = Number(a.total_volume).toFixed(3));
                            if (a.subtotal) r.querySelector('.row-subtotal-hidden-a') && (r.querySelector('.row-subtotal-hidden-a').value = Number(a.subtotal).toFixed(2));
                            if (b.volume) r.querySelector('.row-volume-hidden-b') && (r.querySelector('.row-volume-hidden-b').value = Number(b.volume).toFixed(3));
                            if (b.total_volume) r.querySelector('.row-total-volume-hidden-b') && (r.querySelector('.row-total-volume-hidden-b').value = Number(b.total_volume).toFixed(3));
                            if (b.subtotal) r.querySelector('.row-subtotal-hidden-b') && (r.querySelector('.row-subtotal-hidden-b').value = Number(b.subtotal).toFixed(2));
                        }
                    }
                });

                // restore deductions
                if (parsed.deductions) {
                    Object.keys(parsed.deductions).forEach(k => {
                        const el = document.getElementById(k);
                        if (el) el.value = parsed.deductions[k];
                    });
                }

                recalculateAll();
                return true;
            } catch (e) { console.error('Failed to restore draft', e); return false; }
        }

        // On page load, if server provided items_json, prefill matrix from server data
        document.addEventListener('DOMContentLoaded', () => {
            const itemsJsonEl = document.getElementById('items_json');
            if (itemsJsonEl && itemsJsonEl.value) {
                try {
                    const serverItems = JSON.parse(itemsJsonEl.value);
                    // clear initial rows inserted by the matrix partial
                    document.querySelectorAll('#standardMatrixBody tr.row-item, #splitMatrixBody tr.row-item').forEach(r => r.remove());

                    // Map items by parent/child relationship for splits
                    const byId = {};
                    serverItems.forEach(it => { byId[it.id] = it; });

                    // First handle non-split items
                    serverItems.filter(it => !it.is_split).forEach(it => {
                        addRow({ category: it.wood_category || it.category || defaultCategory, grade: it.grade || 'Good', is_split: false, length: String(it.length || '2.6'), diameter: it.diameter || 20, quantity: it.quantity || 0, isPreset: false });
                        const rid = rowIndex;
                        const r = document.getElementById(`row-${rid}`);
                        if (r) {
                            if (it.volume) r.querySelector('.row-vol-hidden') && (r.querySelector('.row-vol-hidden').value = Number(it.volume).toFixed(3));
                            if (it.total_volume) r.querySelector('.row-total-vol-hidden') && (r.querySelector('.row-total-vol-hidden').value = Number(it.total_volume).toFixed(3));
                            if (it.subtotal) r.querySelector('.row-subtotal-hidden') && (r.querySelector('.row-subtotal-hidden').value = Number(it.subtotal).toFixed(2));
                        }
                    });

                    // Handle split parents and their children
                    const parents = serverItems.filter(it => it.is_split && (!it.parent_log_id || it.parent_log_id == 0));
                    parents.forEach(p => {
                        const child = serverItems.find(c => c.parent_log_id == p.id) || null;
                        const a = p;
                        const b = child;
                        addRow({ category: a.wood_category || a.category || defaultCategory, gradeA: a.grade || 'Good', lengthA: String(a.length || '1.3'), diameterA: a.diameter || 20, gradeB: b?.grade || 'Sawmill', lengthB: String(b?.length || '1.3'), diameterB: b?.diameter || a.diameter || 20, is_split: true, quantity: a.quantity || (b?.quantity || 0), isPreset: false });
                        const rid = rowIndex;
                        const r = document.getElementById(`row-${rid}`);
                        if (r) {
                            if (a.volume) r.querySelector('.row-volume-hidden-a') && (r.querySelector('.row-volume-hidden-a').value = Number(a.volume).toFixed(3));
                            if (a.total_volume) r.querySelector('.row-total-volume-hidden-a') && (r.querySelector('.row-total-volume-hidden-a').value = Number(a.total_volume).toFixed(3));
                            if (a.subtotal) r.querySelector('.row-subtotal-hidden-a') && (r.querySelector('.row-subtotal-hidden-a').value = Number(a.subtotal).toFixed(2));
                            if (b) {
                                if (b.volume) r.querySelector('.row-volume-hidden-b') && (r.querySelector('.row-volume-hidden-b').value = Number(b.volume).toFixed(3));
                                if (b.total_volume) r.querySelector('.row-total-volume-hidden-b') && (r.querySelector('.row-total-volume-hidden-b').value = Number(b.total_volume).toFixed(3));
                                if (b.subtotal) r.querySelector('.row-subtotal-hidden-b') && (r.querySelector('.row-subtotal-hidden-b').value = Number(b.subtotal).toFixed(2));
                            }
                        }
                    });

                    recalculateAll();
                } catch (e) { console.error('Failed to parse items_json', e); }
            }

            // If a draft exists, offer restore
            const draft = localStorage.getItem(draftKey);
            if (draft) {
                try {
                    const confirmed = confirm('A saved draft for this Scale Sheet exists. Restore draft?');
                    if (confirmed) {
                        const restored = loadDraft();
                        if (!restored) console.warn('Draft restore failed');
                    }
                } catch (e) { console.error(e); }
            }

            // Bind autosave listeners after initial load
            bindAutosaveListeners();

            // Clear draft on successful submit
            const form = document.getElementById('scaleForm');
            if (form) {
                form.addEventListener('submit', () => { try { clearDraft(); } catch(e){} });
            }
        });
    })();
    </script>
@endpush
