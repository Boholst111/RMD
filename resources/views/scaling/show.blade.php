@extends('layouts.app')

@section('title', 'Scale Sheet #' . $scaleSheet->scale_sheet_no . ' - RMD Corp')

@section('content')
<div class="space-y-6">

    <style>
        @media print {
            .table-responsive-container {
                overflow: visible !important;
                -ms-overflow-style: none !important; /* IE and Edge */
                scrollbar-width: none !important; /* Firefox */
            }
            .table-responsive-container::-webkit-scrollbar {
                display: none !important; /* Chrome, Safari */
            }
            /* Prevent table cells from truncating in print */
            .table-responsive-container table th,
            .table-responsive-container table td {
                white-space: normal !important;
                word-break: break-word !important;
            }
            /* Avoid page breaks inside rows */
            .table-responsive-container table tr {
                page-break-inside: avoid !important;
            }
        }
    </style>

    <!-- Top Action Toolbar (Hidden when printing) -->
    <div class="no-print flex items-center justify-between bg-slate-800/80 border border-slate-700 p-4 rounded-2xl shadow-xl">
        <div class="flex items-center gap-3">
            <a href="{{ route('scaling.index') }}" class="bg-slate-700 hover:bg-slate-600 text-slate-200 px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="{{ route('scaling.create') }}" class="bg-amber-600 hover:bg-amber-500 text-white px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-plus"></i> New Scale Sheet
            </a>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('scaling.edit', $truckLoad->id) }}" class="bg-slate-700/20 hover:bg-slate-700/30 text-slate-100 font-bold px-6 py-2.5 rounded-xl text-xs transition-all shadow-lg shadow-slate-950/20 flex items-center gap-2 border border-slate-500/20">
                <i class="fa-solid fa-pen-to-square text-sm"></i> Edit Scale Sheet
            </a>
            <button onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-6 py-2.5 rounded-xl text-xs transition-all shadow-lg shadow-emerald-950/50 flex items-center gap-2 border border-emerald-400/30">
                <i class="fa-solid fa-print text-sm"></i> Print Official Invoice
            </button>
            <a href="{{ route('scaling.invoice.pdf', $truckLoad->id) }}" class="bg-sky-600 hover:bg-sky-500 text-white font-bold px-6 py-2.5 rounded-xl text-xs transition-all shadow-lg shadow-sky-950/50 flex items-center gap-2 border border-sky-400/30">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Download PDF
            </a>
        </div>
    </div>

    <!-- Official Printable Invoice Sheet -->
    <div class="bg-slate-900 border border-slate-700 rounded-2xl p-8 text-slate-100 shadow-2xl space-y-8 print:p-0 print:border-none print:shadow-none print:bg-white print:text-black">
        
        <!-- Document Company Header -->
        <div class="border-b-2 border-amber-500/50 print:border-black pb-6 text-center">
            <img src="{{ asset('images/logo.png') }}" class="w-20 h-20 mx-auto mb-2 rounded-full border border-slate-200 shadow-sm object-cover" alt="RMD Logo">
            <p class="text-xs uppercase tracking-[0.4em] font-semibold text-amber-400">R M D C O R P O R A T I O N</p>
            <h1 class="text-4xl font-black text-white print:text-black mt-2">Wood Scaling Invoice</h1>
            <p class="text-sm text-slate-400 print:text-gray-600 mt-1">Taguibo, Butuan City</p>
            <p class="text-xs text-slate-400 print:text-gray-600 mt-1">Official invoice for wood scaling, supplier payout and verification.</p>
        </div>

        <!-- SINGLE CONSOLIDATED DETAILS CARD -->
        <div class="border border-slate-300 print:border-black rounded-2xl p-4 mb-4 bg-slate-800/60 print:bg-white">
            <div class="grid grid-cols-2 gap-4 text-xs text-slate-100 print:text-black">

                <!-- LEFT COLUMN: SUPPLIER & VEHICLE DETAILS -->
                <div class="space-y-2 border-r border-slate-200 print:border-gray-300 pr-4">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400 uppercase font-semibold text-[10px]">Supplier Name:</span>
                        <span class="font-bold text-slate-100 print:text-slate-900 text-sm">{{ $truckLoad->supplier->name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400 uppercase font-semibold text-[10px]">Truck Plate No:</span>
                        <span class="font-bold text-slate-100 print:text-slate-900">{{ $truckLoad->truck_plate_no }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400 uppercase font-semibold text-[10px]">Scaled By:</span>
                        <span class="font-bold text-slate-100 print:text-slate-900">{{ $truckLoad->scaled_by ?? 'Scaler Staff' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400 uppercase font-semibold text-[10px]">Prepared On:</span>
                        <span class="font-bold text-slate-100 print:text-slate-900">{{ \Carbon\Carbon::parse($truckLoad->created_at)->format('M d, Y') }}</span>
                    </div>
                </div>

                <!-- RIGHT COLUMN: INVOICE METADATA -->
                <div class="space-y-2 pl-2">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400 uppercase font-semibold text-[10px]">Invoice No:</span>
                        <span class="font-bold text-slate-100 print:text-slate-900 font-mono">{{ $truckLoad->invoice_no }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400 uppercase font-semibold text-[10px]">Scale Sheet No:</span>
                        <span class="font-bold text-slate-100 print:text-slate-900 font-mono">{{ $truckLoad->scale_sheet_no }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400 uppercase font-semibold text-[10px]">Date Unloaded:</span>
                        <span class="font-bold text-slate-100 print:text-slate-900">{{ \Carbon\Carbon::parse($truckLoad->date_unload)->format('M d, Y') }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400 uppercase font-semibold text-[10px]">Date Scaled:</span>
                        <span class="font-bold text-slate-100 print:text-slate-900">{{ \Carbon\Carbon::parse($truckLoad->date_scaled)->format('M d, Y') }}</span>
                    </div>
                </div>

            </div>
        </div>

        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-300 print:text-black">Wood Scaling Breakdown</h2>
            </div>
            <div class="overflow-x-auto table-responsive-container print:overflow-visible border border-slate-700 print:border-black rounded-xl bg-slate-900/50 print:bg-white">
                <table class="w-full text-left text-xs text-slate-200 print:text-black">
                    <thead class="bg-slate-800 print:bg-gray-200 uppercase font-semibold text-slate-400 print:text-black border-b border-slate-700 print:border-black">
                        <tr>
                            <th class="px-4 py-3">SIZE / GRADE BRACKET</th>
                            <th class="px-4 py-3 text-center">PIECES</th>
                            <th class="px-4 py-3 text-right">TOTAL VOLUME (M³)</th>
                            <th class="px-4 py-3 text-right">RATE (₱/M³)</th>
                            <th class="px-4 py-3 text-right">SUBTOTAL (₱)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 print:divide-gray-300">
                        @foreach($breakdownBrackets as $bracket)
                            <tr class="hover:bg-slate-800/30 print:hover:bg-transparent">
                                <td class="px-4 py-3 font-bold text-white print:text-black uppercase">{{ $bracket['bracket'] }}</td>
                                <td class="px-4 py-3 text-center font-mono">{{ number_format($bracket['pieces']) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-sky-400 print:text-black">{{ number_format($bracket['total_volume'], 3) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-amber-300 print:text-black">{{ $bracket['rate'] > 0 ? '₱ ' . number_format($bracket['rate'], 2) : '-' }}</td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-amber-400 print:text-black">₱ {{ number_format($bracket['subtotal'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="border border-slate-700 print:border-black rounded-xl p-4 space-y-3 bg-slate-800/40 print:bg-transparent">
                <h3 class="text-xs font-bold uppercase tracking-wider text-rose-400 print:text-black">Deductions Breakdown</h3>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between text-slate-300 print:text-black"><span>Add Driver's Assistance:</span><span class="font-mono text-emerald-400">+ ₱ {{ number_format($scaleSheet->drivers_assistance, 2) }}</span></div>
                    <div class="flex justify-between text-slate-300 print:text-black"><span>Expenses Deduction:</span><span class="font-mono">₱ {{ number_format($scaleSheet->expenses_deduction, 2) }}</span></div>
                    <div class="flex justify-between text-slate-300 print:text-black"><span>Travel Paper / Permit:</span><span class="font-mono">₱ {{ number_format($scaleSheet->travel_paper_deduction, 2) }}</span></div>
                    <div class="flex justify-between text-slate-300 print:text-black"><span>Trucking Deduction:</span><span class="font-mono">₱ {{ number_format($scaleSheet->trucking_deduction, 2) }}</span></div>
                    <div class="flex justify-between text-slate-300 print:text-black"><span>Cash Advance:</span><span class="font-mono">₱ {{ number_format($scaleSheet->cash_advance, 2) }}</span></div>
                    <div class="flex justify-between text-slate-300 print:text-black"><span>{{ $scaleSheet->other_deduction_label ?: 'Other Deduction' }}:</span><span class="font-mono">₱ {{ number_format($scaleSheet->other_deduction_amount, 2) }}</span></div>
                    <div class="flex justify-between font-bold text-rose-400 print:text-black border-t border-slate-700 print:border-black pt-2 text-sm"><span>Total Deductions:</span><span class="font-mono">- ₱ {{ number_format($calculatedDeductions, 2) }}</span></div>
                </div>
            </div>

            <div class="border-2 border-amber-500/60 print:border-black rounded-xl p-6 bg-slate-800/80 print:bg-gray-100 flex flex-col justify-between space-y-4">
                <div>
                    <div class="flex justify-between items-center text-slate-300 print:text-black text-xs font-semibold uppercase"><span>Gross Wood Amount:</span><span class="font-mono font-bold text-sm">₱ {{ number_format($calculatedGrossAmount, 2) }}</span></div>
                    <div class="flex justify-between items-center text-emerald-400 print:text-black text-xs font-semibold uppercase mt-1"><span>Add Driver's Assistance:</span><span class="font-mono font-bold text-sm">+ ₱ {{ number_format($scaleSheet->drivers_assistance, 2) }}</span></div>
                    <div class="flex justify-between items-center text-rose-400 print:text-black text-xs font-semibold uppercase mt-1"><span>Less Total Deductions:</span><span class="font-mono font-bold text-sm">- ₱ {{ number_format($calculatedDeductions, 2) }}</span></div>
                </div>
                <div class="border-t-2 border-amber-500/50 print:border-black pt-4">
                    <span class="text-xs uppercase font-extrabold text-amber-400 print:text-black tracking-wider block">Net Amount Payable to Supplier</span>
                    <span class="text-3xl font-extrabold font-mono text-emerald-400 print:text-black">₱ {{ number_format($calculatedNetPayable, 2) }}</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-6 pt-12 text-center text-xs print:pt-16">
            <div class="space-y-12">
                <div class="border-b border-slate-600 print:border-black w-4/5 mx-auto"></div>
                <div><strong class="block text-slate-200 print:text-black uppercase font-bold">{{ $scaleSheet->scaled_by }}</strong><span class="text-slate-400 print:text-gray-600">Scaled & Calculated By</span></div>
            </div>
            <div class="space-y-12">
                <div class="border-b border-slate-600 print:border-black w-4/5 mx-auto"></div>
                <div><strong class="block text-slate-200 print:text-black uppercase font-bold">{{ optional($scaleSheet->supplier)->name ?? 'Supplier Representative' }}</strong><span class="text-slate-400 print:text-gray-600">Supplier / Driver Signature</span></div>
            </div>
            <div class="space-y-12">
                <div class="border-b border-slate-600 print:border-black w-4/5 mx-auto"></div>
                <div><strong class="block text-slate-200 print:text-black uppercase font-bold">RMD Management</strong><span class="text-slate-400 print:text-gray-600">Approved By</span></div>
            </div>
        </div>

    </div>
</div>
@endsection
