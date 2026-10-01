<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Statement of Account</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 11px; }
        h1, h2, h3, p { margin: 0; }
        .header { border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 16px; }
        .muted { color: #555; }
        .summary { margin: 12px 0 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; vertical-align: top; }
        th { background: #f4f4f4; text-align: left; }
        .right { text-align: right; }
        .small { font-size: 9px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Statement of Account</h1>
        <p class="muted">Generated: {{ now()->toDateTimeString() }}</p>
        <p><strong>Account Holder:</strong> {{ $user->fullname ?? $user->email }}</p>
        <p><strong>User ID:</strong> {{ $user->id }} | <strong>Email:</strong> {{ $user->email }}</p>
        @if(!empty($filters['from_date']) || !empty($filters['to_date']))
            <p><strong>Period:</strong> {{ $filters['from_date'] ?? 'Start' }} to {{ $filters['to_date'] ?? 'Now' }}</p>
        @endif
    </div>

    <div class="summary">
        <h3>Summary</h3>
        <p><strong>Total Transactions:</strong> {{ $summary['total_transactions'] ?? 0 }}</p>
        <p><strong>Successful Transactions:</strong> {{ $summary['successful_transactions'] ?? 0 }}</p>
        <p><strong>Volume by Currency:</strong>
            @forelse(($summary['volume_by_currency'] ?? []) as $currency => $amount)
                {{ $currency }} {{ $amount }}@if(!$loop->last), @endif
            @empty
                N/A
            @endforelse
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Reference</th>
                <th>Date/Time</th>
                <th>Type</th>
                <th>Direction</th>
                <th class="right">Amount</th>
                <th>Currency</th>
                <th>Status</th>
                <th class="right">Fees</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $transaction)
                <tr>
                    <td>
                        {{ $transaction['transaction_id_reference'] }}
                        <div class="small muted">{{ $transaction['source_table'] }} #{{ $transaction['source_id'] }}</div>
                    </td>
                    <td>{{ $transaction['transaction_date_time'] }}</td>
                    <td>{{ $transaction['transaction_type'] }}</td>
                    <td>{{ $transaction['direction'] }}</td>
                    <td class="right">{{ $transaction['amount'] }}</td>
                    <td>{{ $transaction['currency'] }}</td>
                    <td>{{ $transaction['status'] }}</td>
                    <td class="right">{{ $transaction['fees'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No transactions found for this filter.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
