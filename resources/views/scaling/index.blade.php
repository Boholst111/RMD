@extends('layouts.app')

@section('title', 'Scale Sheets Dashboard - RMD Corp')

@php
    $userRole = auth()->user()?->role ?? null;
    $canDeleteScaleSheets = $userRole === 'super_admin';
@endphp

@section('content')
<div class="space-y-8">
    
    <!-- Top Bar Header & Action Buttons -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-white tracking-tight flex items-center gap-3">
                <span>Scale Sheets Dashboard</span>
                <span class="text-xs bg-amber-500/20 text-amber-300 font-semibold px-3 py-1 rounded-full border border-amber-500/30">
                    Live Data
                </span>
            </h1>
            <p class="text-slate-400 text-sm mt-1">Manage truck loads, Brereton log tallies, and official supplier invoices.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3 no-print">
            <!-- Generate PDF Action -->
            <a href="{{ route('scaling.reports.pdf', request()->all()) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs bg-slate-800 hover:bg-slate-700 text-amber-400 border border-amber-500/30 transition-all shadow-md">
                <i class="fa-solid fa-file-pdf text-amber-400 text-sm"></i>
                <span>Generate PDF</span>
            </a>

            <!-- Print Summary Action -->
            <a href="{{ route('scaling.reports.print', request()->all()) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs bg-slate-800 hover:bg-slate-700 text-emerald-400 border border-emerald-500/30 transition-all shadow-md">
                <i class="fa-solid fa-print text-emerald-400 text-sm"></i>
                <span>Print Summary</span>
            </a>
        </div>
    </div>

    <!-- Dynamic Aggregate Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Total Loads -->
        <div class="glass-panel p-5 rounded-2xl border border-slate-700/50 shadow-xl relative overflow-hidden group hover:border-amber-500/30 transition-all">
            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-amber-500/10 rounded-full blur-xl group-hover:bg-amber-500/20 transition-all"></div>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Total Scale Sheets</p>
                    <h3 class="text-3xl font-extrabold text-white mt-1">{{ number_format($totalLoads) }}</h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center border border-amber-500/30">
                    <i class="fa-solid fa-truck-ramp-box text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3 flex items-center gap-1">
                <i class="fa-solid fa-circle-info text-amber-400"></i> Matching query result
            </p>
        </div>

        <!-- Total Logs -->
        <div class="glass-panel p-5 rounded-2xl border border-slate-700/50 shadow-xl relative overflow-hidden group hover:border-amber-500/30 transition-all">
            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-emerald-500/10 rounded-full blur-xl group-hover:bg-emerald-500/20 transition-all"></div>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Total Logs Scaled</p>
                    <h3 class="text-3xl font-extrabold text-emerald-400 mt-1">{{ number_format($totalLogsAll) }} <span class="text-xs text-slate-400 font-normal">pcs</span></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center border border-emerald-500/30">
                    <i class="fa-solid fa-layer-group text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3 flex items-center gap-1">
                <i class="fa-solid fa-tree text-emerald-400"></i> Total log piece count
            </p>
        </div>

        <!-- Total Volume -->
        <div class="glass-panel p-5 rounded-2xl border border-slate-700/50 shadow-xl relative overflow-hidden group hover:border-amber-500/30 transition-all">
            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-sky-500/10 rounded-full blur-xl group-hover:bg-sky-500/20 transition-all"></div>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Total Volume</p>
                    <h3 class="text-3xl font-extrabold text-sky-400 mt-1">{{ number_format($totalVolumeAll, 3) }} <span class="text-xs text-slate-400 font-normal">m³</span></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-sky-500/20 text-sky-400 flex items-center justify-center border border-sky-500/30">
                    <i class="fa-solid fa-cube text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3 flex items-center gap-1">
                <i class="fa-solid fa-calculator text-sky-400"></i> Brereton volume total
            </p>
        </div>

        <!-- Total Net Payable -->
        <div class="glass-panel p-5 rounded-2xl border border-slate-700/50 shadow-xl relative overflow-hidden group hover:border-amber-500/30 transition-all">
            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-amber-500/20 rounded-full blur-xl"></div>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Total Net Payable</p>
                    <h3 class="text-3xl font-extrabold text-amber-300 mt-1">₱ {{ number_format($totalNetPayable, 2) }}</h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-500/30 text-amber-300 flex items-center justify-center border border-amber-500/40">
                    <i class="fa-solid fa-coins text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3 flex items-center gap-1">
                <i class="fa-solid fa-file-invoice-dollar text-amber-400"></i> Net payout after deductions
            </p>
        </div>
    </div>

    <!-- Enhanced Unified Search & Date Filter Controls -->
    <div class="glass-panel p-5 rounded-2xl border border-slate-800 shadow-xl no-print">
        <form method="GET" action="{{ route('scaling.index') }}" class="space-y-4">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4">
                
                <!-- Quick Date Scope -->
                <div class="lg:col-span-3">
                    <label for="date_scope" class="block text-xs font-semibold uppercase text-slate-400 mb-1">Date Scope</label>
                    <select name="date_scope" id="date_scope" onchange="this.form.submit()" class="w-full bg-slate-900/90 border border-slate-700 text-slate-200 text-sm rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none">
                        <option value="" {{ request('date_scope') == '' ? 'selected' : '' }}>All Time</option>
                        <option value="today" {{ request('date_scope') == 'today' ? 'selected' : '' }}>Today</option>
                        <option value="this_week" {{ request('date_scope') == 'this_week' ? 'selected' : '' }}>This Week</option>
                        <option value="this_month" {{ request('date_scope') == 'this_month' ? 'selected' : '' }}>This Month</option>
                        <option value="custom" {{ request('date_scope') == 'custom' || request('date_from') || request('date_to') ? 'selected' : '' }}>Custom Range</option>
                    </select>
                </div>

                <!-- Date From -->
                <div class="lg:col-span-2">
                    <label for="date_from" class="block text-xs font-semibold uppercase text-slate-400 mb-1">Start Date</label>
                    <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="w-full bg-slate-900/90 border border-slate-700 text-slate-200 text-sm rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none">
                </div>

                <!-- Date To -->
                <div class="lg:col-span-2">
                    <label for="date_to" class="block text-xs font-semibold uppercase text-slate-400 mb-1">End Date</label>
                    <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}" class="w-full bg-slate-900/90 border border-slate-700 text-slate-200 text-sm rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none">
                </div>

                <!-- Supplier Filter -->
                <div class="lg:col-span-3">
                    <label for="supplier_id" class="block text-xs font-semibold uppercase text-slate-400 mb-1">Filter by Supplier</label>
                    <select name="supplier_id" id="supplier_id" onchange="this.form.submit()" class="w-full bg-slate-900/90 border border-slate-700 text-slate-200 text-sm rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none">
                        <option value="">All Suppliers</option>
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}" {{ request('supplier_id') == $sup->id ? 'selected' : '' }}>
                                {{ $sup->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Search Input -->
                <div class="lg:col-span-2">
                    <label for="search" class="block text-xs font-semibold uppercase text-slate-400 mb-1">Search Keyword</label>
                    <div class="relative">
                        <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Sheet # / Plate" class="w-full bg-slate-900/90 border border-slate-700 text-slate-200 text-sm rounded-xl pl-9 pr-3 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-3.5 text-slate-400 text-xs"></i>
                    </div>
                </div>

            </div>

            <!-- Action Buttons Row -->
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="submit" class="bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs py-2 px-5 rounded-xl transition-all shadow-md shadow-amber-950/30 flex items-center gap-2">
                    <i class="fa-solid fa-filter"></i> Apply Filters
                </button>

                @if(request('supplier_id') || request('search') || request('date_scope') || request('date_from') || request('date_to'))
                    <a href="{{ route('scaling.index') }}" class="bg-slate-800 hover:bg-slate-700 text-slate-300 py-2 px-3 rounded-xl text-xs font-bold transition-all flex items-center gap-1" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                @endif
            </div>

        </form>
    </div>

    <!-- Scale Sheets Table -->
    <div class="glass-panel rounded-2xl border border-slate-800 shadow-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-file-invoice text-amber-500"></i> Scale Sheet Records
            </h2>
            <span class="text-xs text-slate-400">Showing {{ $truckLoads->count() }} of {{ $truckLoads->total() }} entries</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-900/80 text-xs uppercase tracking-wider text-slate-400 font-semibold border-b border-slate-800">
                    <tr>
                        <th class="px-6 py-3.5">Sheet No.</th>
                        <th class="px-6 py-3.5">Supplier</th>
                        <th class="px-6 py-3.5">Truck Plate</th>
                        <th class="px-6 py-3.5">Scaled Date</th>
                        <th class="px-6 py-3.5 text-center">Logs</th>
                        <th class="px-6 py-3.5 text-right">Volume (m³)</th>
                        <th class="px-6 py-3.5 text-right">Gross (₱)</th>
                        <th class="px-6 py-3.5 text-right">Deductions (₱)</th>
                        <th class="px-6 py-3.5 text-right">Net Payable (₱)</th>
                        <th class="px-6 py-3.5 text-center no-print">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($truckLoads as $load)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-6 py-4 font-mono font-bold text-amber-400">
                                #{{ $load->scale_sheet_no }}
                            </td>
                            <td class="px-6 py-4 font-semibold text-white">
                                {{ $load->supplier->name ?? 'Unknown' }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="bg-slate-800 text-slate-200 border border-slate-700 font-mono px-2.5 py-1 rounded-lg text-xs font-semibold">
                                    {{ $load->truck_plate_no }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-300">
                                {{ $load->date_scaled ? \Carbon\Carbon::parse($load->date_scaled)->format('M d, Y') : '-' }}
                            </td>
                            <td class="px-6 py-4 text-center font-semibold text-slate-200">
                                {{ number_format($load->total_logs) }}
                            </td>
                            <td class="px-6 py-4 text-right font-mono text-sky-400">
                                {{ number_format($load->total_volume, 3) }}
                            </td>
                            <td class="px-6 py-4 text-right font-mono text-slate-200">
                                {{ number_format($load->gross_amount, 2) }}
                            </td>
                            <td class="px-6 py-4 text-right font-mono text-rose-400">
                                -{{ number_format($load->total_deductions, 2) }}
                            </td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-emerald-400 text-base">
                                ₱ {{ number_format($load->net_payable, 2) }}
                            </td>
                            <td class="px-6 py-4 text-center no-print">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('scaling.edit', $load->id) }}" class="bg-slate-700/10 hover:bg-slate-700/20 text-slate-300 border border-slate-600/50 px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1" title="Edit Scale Sheet">
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </a>
                                    <a href="{{ route('scaling.show', $load->id) }}" class="bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1" title="View Scale Sheet Invoice">
                                        <i class="fa-solid fa-receipt"></i> Invoice
                                    </a>

                                    @if($canDeleteScaleSheets)
                                        <form action="{{ route('scaling.destroy', $load->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete Scale Sheet #{{ $load->scale_sheet_no }}?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 px-2.5 py-1.5 rounded-lg text-xs font-bold transition-all" title="Delete Load">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center gap-3">
                                    <i class="fa-solid fa-folder-open text-4xl text-slate-600"></i>
                                    <p class="font-medium text-base text-slate-400">No scale sheets found matching your filters.</p>
                                    <a href="{{ route('scaling.create') }}" class="text-xs text-amber-400 hover:text-amber-300 underline font-semibold">
                                        Create a new scale sheet now
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($truckLoads->hasPages())
            <div class="px-6 py-4 border-t border-slate-800 no-print">
                {{ $truckLoads->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
