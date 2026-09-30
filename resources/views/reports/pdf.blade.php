<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #0f172a;
            background: white;
            margin: 0;
            padding: 0;
        }

        .report-container {
            width: 210mm;
            margin: 0 auto;
            padding: 0;
        }

        .report-paper {
            width: 100%;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.08);
            padding: 24px;
            box-sizing: border-box;
        }

        .report-header {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 18px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 18px;
            margin-bottom: 20px;
        }

        .brand-tag {
            font-size: 10px;
            letter-spacing: 0.35em;
            text-transform: uppercase;
            font-weight: 800;
            color: #b45309;
            margin: 0 0 8px;
        }

        .report-title {
            font-size: 28px;
            line-height: 1.05;
            font-weight: 800;
            margin: 0;
            color: #0f172a;
        }

        .report-subtitle {
            font-size: 0.95rem;
            margin-top: 8px;
            color: #334155;
        }

        .meta-panel {
            min-width: 220px;
            background: #f8fafc;
            border: 1px solid #d1d5db;
            border-radius: 22px;
            padding: 16px;
            font-size: 0.84rem;
            color: #334155;
            text-align: right;
        }

        .meta-line {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .meta-line:last-child {
            margin-bottom: 0;
        }

        .meta-label {
            font-weight: 700;
            color: #0f172a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
            font-size: 0.86rem;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 12px 10px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 700;
            color: #334155;
            font-size: 0.78rem;
        }

        td {
            color: #0f172a;
        }

        .text-right {
            text-align: right;
        }

        .summary-block {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 2px solid #0f172a;
            display: grid;
            gap: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .summary-label {
            color: #334155;
            font-weight: 700;
        }

        .summary-value {
            text-align: right;
        }

        .report-footer {
            margin-top: 26px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 0.78rem;
            color: #475569;
        }

        @media print {
            .report-paper {
                box-shadow: none !important;
                border-radius: 0 !important;
                border: none !important;
                padding: 0 !important;
            }

            body {
                margin: 0;
                background: white;
            }

            .report-container {
                width: auto;
                margin: 0;
                padding: 0;
            }

            .report-header {
                border: none;
                padding-bottom: 0;
                margin-bottom: 12px;
            }

            .meta-panel {
                border-color: #cbd5e1;
                background: #ffffff;
            }

            th {
                background: #f8fafc;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="report-paper">
            <div class="report-header">
                <div>
                    <p class="brand-tag">RMD CORPORATION</p>
                    <h1 class="report-title">RMD CORP - LOGGING SUMMARY REPORT</h1>
                    <p class="report-subtitle">Report period and totals aligned with the active scale sheet print template.</p>
                </div>
                <div class="meta-panel">
                    <div class="meta-line"><span class="meta-label">Report Period:</span><span>{{ $periodLabel }}</span></div>
                    <div class="meta-line"><span class="meta-label">Generated:</span><span>{{ \Carbon\Carbon::now()->format('M d, Y') }}</span></div>
                    <div class="meta-line"><span class="meta-label">Prepared By:</span><span>RMD Corp Operations</span></div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>SHEET NO.</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Truck Plate</th>
                        <th class="text-right">Total Pieces</th>
                        <th class="text-right">Total Volume (m³)</th>
                        <th class="text-right">Gross</th>
                        <th class="text-right">Deductions</th>
                        <th class="text-right">Net Payable</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reportRows as $row)
                        <tr>
                            <td>{{ $row['sheet_no'] }}</td>
                            <td>{{ $row['date'] }}</td>
                            <td>{{ $row['supplier'] }}</td>
                            <td>{{ $row['truck_plate'] }}</td>
                            <td class="text-right">{{ number_format($row['total_pieces']) }}</td>
                            <td class="text-right">{{ number_format($row['total_volume'], 3) }}</td>
                            <td class="text-right">PHP {{ number_format($row['gross_amount'], 2) }}</td>
                            <td class="text-right">PHP {{ number_format($row['total_deductions'], 2) }}</td>
                            <td class="text-right">PHP {{ number_format($row['net_payout'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="summary-block">
                <div class="summary-row"><span class="summary-label">Grand Total Volume</span><span class="summary-value">{{ number_format($grandTotals['total_volume'], 3) }} m³</span></div>
                <div class="summary-row"><span class="summary-label">Grand Gross Amount</span><span class="summary-value">PHP {{ number_format($grandTotals['gross'], 2) }}</span></div>
                <div class="summary-row"><span class="summary-label">Grand Deductions</span><span class="summary-value">PHP {{ number_format($grandTotals['deductions'], 2) }}</span></div>
                <div class="summary-row"><span class="summary-label">Grand Net Payable</span><span class="summary-value">PHP {{ number_format($grandTotals['net'], 2) }}</span></div>
            </div>

            <div class="report-footer">
                <span>RMD CORP · Official Print Summary</span>
                <span>Prepared for internal operations and supplier verification</span>
            </div>
        </div>
    </div>
</body>
</html>
