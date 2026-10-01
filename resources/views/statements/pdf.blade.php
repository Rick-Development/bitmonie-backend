<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Statement of Account</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; color: #333333; background-color: #FFFFFF; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #f9b707; padding-bottom: 15px; }
        .logo { font-size: 24px; font-weight: bold; color: #333333; }
        .logo img { max-height: 65px; margin-bottom: 5px; }
        .title { font-size: 16px; font-weight: bold; color: #555555; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 5px;}
        .info-table { width: 100%; margin-bottom: 20px; color: #333333; font-size: 11px; }
        .info-table td { padding: 6px; border-bottom: 1px solid #f9f9f9; }
        .info-label { font-weight: bold; color: #000000; width: 130px; }
        .stats-table { width: 100%; margin-bottom: 25px; background-color: #fafafa; border: 1px solid #eeeeee; border-radius: 4px; }
        .stats-table td { padding: 12px; text-align: center; border-right: 1px solid #eeeeee; width: 50%; }
        .stats-table td:last-child { border-right: none; }
        .stat-value { font-size: 18px; font-weight: bold; color: #e6a800; display: block; margin-top: 6px; }
        .tx-table { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; }
        .tx-table th, .tx-table td { border-bottom: 1px solid #dddddd; padding: 6px 4px; text-align: left; word-wrap: break-word; }
        .tx-table th { background-color: #fcfcfc; color: #555555; font-weight: bold; text-transform: uppercase; font-size: 9px; border-top: 1px solid #dddddd; }
        .tx-table th:nth-child(1) { width: 14%; } /* Date */
        .tx-table th:nth-child(2) { width: 14%; } /* Type */
        .tx-table th:nth-child(3) { width: 22%; } /* Narration */
        .tx-table th:nth-child(4) { width: 26%; } /* TXID */
        .tx-table th:nth-child(5) { width: 10%; } /* Status */
        .tx-table th:nth-child(6) { width: 14%; } /* Amount */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .status-success { color: #28a745; font-weight: bold; }
        .status-pending { color: #e0a800; font-weight: bold; }
        .status-failed { color: #dc3545; font-weight: bold; }
        .footer { text-align: center; margin-top: 50px; font-size: 10px; color: #666; border-top: 1px solid #dddddd; padding-top: 20px; line-height: 1.6; }
        .footer p { margin: 4px 0; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            @php
                $logoPath = public_path('assets/images/statement-logo-transparent.png');
                $logoBase64 = '';
                if(file_exists($logoPath)) {
                    $logoData = file_get_contents($logoPath);
                    $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
                }
            @endphp
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" alt="BitMonie Logo">
            @else
                BitMonie
            @endif
        </div>
        <div class="title">Official Statement of Account</div>
    </div>

    <table class="info-table">
        <tr>
            <td class="info-label">Customer Name:</td>
            <td>{{ $user->firstname }} {{ $user->lastname }}</td>
            <td class="info-label">Date Generated:</td>
            <td>{{ now()->format('Y-m-d H:i:s') }}</td>
        </tr>
        <tr>
            <td class="info-label">Email:</td>
            <td>{{ $user->email }}</td>
            <td class="info-label">Statement Period:</td>
            <td>{{ $filters['start_date'] ?? 'N/A' }} to {{ $filters['end_date'] ?? 'N/A' }}</td>
        </tr>
    </table>

    @if(isset($stats))
    <table class="stats-table">
        <tr>
            <td>
                <span class="info-label" style="color: #666; font-size: 11px; text-transform: uppercase;">Total Transactions</span>
                <span class="stat-value">{{ number_format($stats['total_trx_count']) }}</span>
            </td>
            <td>
                <span class="info-label" style="color: #666; font-size: 11px; text-transform: uppercase;">Total Volume</span>
                <span class="stat-value">NGN {{ number_format($stats['total_volume'], 2) }}</span>
            </td>
        </tr>
    </table>
    @endif

    <table class="tx-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Narration</th>
                <th>TXID</th>
                <th>Status</th>
                <th class="text-right">Amount (NGN)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $tx)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($tx['date'])->format('Y-m-d H:i') }}</td>
                    <td>{{ ucfirst($tx['type']) }}</td>
                    <td>{{ $tx['narration'] }}</td>
                    <td>{{ $tx['txid'] }}</td>
                    <td class="status-{{ strtolower($tx['status']) }}">{{ ucfirst($tx['status']) }}</td>
                    <td class="text-right">{{ number_format($tx['amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 30px;">No transactions found for this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>This statement is an electronically generated record of your BitMonie account activity. It is deemed accurate and requires no physical signature for validation.</p>
        <p>If you suspect any unauthorized transactions or discrepancies, please contact <strong>support@bitmonie.com</strong> immediately.</p>
        <p><strong>&copy; {{ date('Y') }} BitMonie. All Rights Reserved.</strong> | BitMonie is a crypto-powered neobank committed to your financial security.</p>
    </div>
</body>
</html>
