<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging & Wood Scaling Summary Report - RMD Corporation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
            background: white;
            color: #0f172a;
        }

        .report-shell {
            max-width: 210mm;
            margin: 0 auto;
            padding: 0;
        }

        .report-card {
            border-radius: 28px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.08);
            padding: 32px;
        }

        .brand-logo {
            width: 5rem;
            height: 5rem;
            object-fit: cover;
            border-radius: 9999px;
            display: block;
            margin: 0 auto 1rem;
        }

        .brand-tag {
            display: block;
            text-align: center;
            letter-spacing: 0.45em;
            text-transform: uppercase;
            font-weight: 800;
            color: #f59e0b;
            font-size: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .report-title {
            text-align: center;
            font-size: 2.15rem;
            line-height: 1.05;
            font-weight: 800;
            margin: 0;
            color: #0f172a;
        }

        .report-subtitle {
            text-align: center;
            color: #475569;
            margin: 0.6rem auto 0;
            max-width: 680px;
            font-size: 0.95rem;
        }

        .details-card {
            border: 1px solid #cbd5e1;
            border-radius: 24px;
            background: #ffffff;
            padding: 20px;
            margin: 24px 0;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            font-size: 0.82rem;
            color: #334155;
        }

        .detail-line {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .detail-line:last-child {
            border-bottom: none;
        }

        .detail-label {
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.68rem;
            color: #64748b;
            font-weight: 700;
        }

        .detail-value {
            font-weight: 700;
            color: #0f172a;
            text-align: right;
        }

        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 0.82rem;
        }

        .breakdown-table th,
        .breakdown-table td {
            border: 1px solid #e2e8f0;
            padding: 12px 10px;
            vertical-align: middle;
        }

        .breakdown-table th {
            background: #f8fafc;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.72rem;
            font-weight: 800;
        }

        .breakdown-table tbody tr:nth-child(odd) {
            background: #ffffff;
        }

        .breakdown-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .monospace {
            font-family: 'Space Mono', monospace;
        }

        .text-right {
            text-align: right;
        }

        .summary-panel {
            display: grid;
            grid-template-columns: 1fr minmax(260px, 340px);
            gap: 18px;
            margin-top: 26px;
        }

        .summary-info {
            border: 1px solid #cbd5e1;
            border-radius: 24px;
            background: #ffffff;
            padding: 20px;
            font-size: 0.92rem;
            color: #334155;
        }

        .summary-info h3 {
            margin: 0 0 0.75rem;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #0f172a;
        }

        .summary-info .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }

        .summary-info .summary-label {
            color: #64748b;
            font-weight: 700;
            font-size: 0.75rem;
        }

        .summary-info .summary-value {
            font-weight: 700;
            color: #0f172a;
        }

        .summary-highlight {
            border: 2px solid #16a34a;
            border-radius: 24px;
            background: #ecfdf5;
            padding: 22px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 10px;
        }

        .summary-highlight .title {
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: 0.72rem;
            font-weight: 800;
            color: #16a34a;
        }

        .summary-highlight .amount {
            font-size: 1.9rem;
            font-weight: 900;
            color: #0f172a;
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
            margin-top: 32px;
        }

        .closing-block {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .signature-block {
            text-align: center;
            font-size: 0.78rem;
            color: #334155;
        }

        .signature-line {
            display: block;
            margin: 0 auto 0.75rem;
            width: 80%;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 0.75rem;
        }

        .signature-title {
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.08em;
            color: #0f172a;
            margin-top: 0.5rem;
        }

        .no-print {
            display: none !important;
        }

        @media print {
            body {
                margin: 0;
            }

            .report-card {
                box-shadow: none !important;
                border-radius: 0 !important;
                border: none !important;
                padding: 0 !important;
            }

            .report-shell {
                padding: 0 !important;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="report-shell">
        <div class="report-card">
            <img src="{{ asset('images/logo.png') }}" class="brand-logo" alt="RMD Logo">
            <span class="brand-tag">R M D C O R P O R A T I O N</span>
            <h1 class="report-title">LOGGING & WOOD SCALING SUMMARY REPORT</h1>
            <p class="report-subtitle">Taguibo, Butuan City • Official Summary Payout & Scaling Verification</p>

            <div class="details-card">
                <div>
                    <div class="detail-line">
                        <span class="detail-label">Report Type</span>
                        <span class="detail-value">{{ $reportType }}</span>
                    </div>
                    <div class="detail-line">
                        <span class="detail-label">Total Scale Sheets</span>
                        <span class="detail-value">{{ number_format(count($reportRows)) }}</span>
                    </div>
                    <div class="detail-line">
                        <span class="detail-label">Generated By</span>
                        <span class="detail-value">{{ $generatedBy }}</span>
                    </div>
                </div>
                <div>
                    <div class="detail-line">
                        <span class="detail-label">Period Covered</span>
                        <span class="detail-value">{{ $periodLabel }}</span>
                    </div>
                    <div class="detail-line">
                        <span class="detail-label">Date Generated</span>
                        <span class="detail-value">{{ $dateGenerated }}</span>
                    </div>
                </div>
            </div>

            <table class="breakdown-table">
                <thead>
                    <tr>
                        <th>SHEET NO.</th>
                        <th>SUPPLIER NAME</th>
                        <th>TRUCK PLATE</th>
                        <th>SCALED DATE</th>
                        <th class="text-right">TOTAL LOGS</th>
                        <th class="text-right">TOTAL VOLUME (M³)</th>
                        <th class="text-right">NET PAYOUT (₱)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reportRows as $row)
                        <tr>
                            <td class="monospace">{{ $row['sheet_no'] }}</td>
                            <td>{{ $row['supplier_name'] }}</td>
                            <td class="monospace">{{ $row['truck_plate'] }}</td>
                            <td>{{ $row['scaled_date'] }}</td>
                            <td class="text-right monospace">{{ number_format($row['total_logs']) }}</td>
                            <td class="text-right monospace">{{ number_format($row['total_volume'], 3) }}</td>
                            <td class="text-right monospace">₱ {{ number_format($row['net_payout'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="closing-block">
            <div class="summary-panel">
                <div class="summary-info">
                    <h3>Summary Totals</h3>
                    <div class="summary-row">
                        <span class="summary-label">Total Logs Scaled</span>
                        <span class="summary-value">{{ number_format($grandTotals['total_logs']) }} pcs</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Total Combined Volume</span>
                        <span class="summary-value">{{ number_format($grandTotals['total_volume'], 3) }} m³</span>
                    </div>
                </div>
                <div class="summary-highlight">
                    <span class="title">Net Total Supplier Payout</span>
                    <span class="amount">₱ {{ number_format($grandTotals['net'], 2) }}</span>
                </div>
            </div>

            <div class="signatures">
                <div class="signature-block">
                    <span class="signature-line"></span>
                    <span class="signature-title">Prepared By</span>
                    <div>Scaler Staff</div>
                </div>
                <div class="signature-block">
                    <span class="signature-line"></span>
                    <span class="signature-title">Reviewed By</span>
                    <div>Accountant</div>
                </div>
                <div class="signature-block">
                    <span class="signature-line"></span>
                    <span class="signature-title">Approved By</span>
                    <div>RMD Management</div>
                </div>
            </div>
            </div>
        </div>
    </div>
</body>
</html>
