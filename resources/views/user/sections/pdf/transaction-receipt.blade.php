<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transaction Receipt</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 12px; }
        .receipt { border: 1px solid #ddd; padding: 24px; }
        .header { border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 16px; }
        .row { clear: both; border-bottom: 1px solid #eee; padding: 8px 0; }
        .label { width: 35%; float: left; font-weight: bold; }
        .value { width: 65%; float: left; }
        .footer { margin-top: 24px; font-size: 10px; color: #555; }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1>Transaction Receipt</h1>
            <p>Generated: {{ now()->toDateTimeString() }}</p>
        </div>

        <div class="row"><div class="label">TXID / Reference</div><div class="value">{{ $transaction['transaction_id_reference'] }}</div></div>
        <div class="row"><div class="label">Date & Time</div><div class="value">{{ $transaction['transaction_date_time'] }}</div></div>
        <div class="row"><div class="label">Amount</div><div class="value">{{ $transaction['amount'] }} {{ $transaction['currency'] }}</div></div>
        <div class="row"><div class="label">Fees</div><div class="value">{{ $transaction['fees'] }} {{ $transaction['currency'] }}</div></div>
        <div class="row"><div class="label">Status</div><div class="value">{{ strtoupper($transaction['status']) }}</div></div>
        <div class="row"><div class="label">Transaction Type</div><div class="value">{{ $transaction['transaction_type'] }}</div></div>
        <div class="row"><div class="label">Direction</div><div class="value">{{ $transaction['direction'] }}</div></div>
        <div class="row"><div class="label">User/Sender ID</div><div class="value">{{ $transaction['user_sender_id'] ?? 'N/A' }}</div></div>
        <div class="row"><div class="label">Recipient ID</div><div class="value">{{ $transaction['recipient_id'] ?? 'N/A' }}</div></div>
        <div class="row"><div class="label">Wallet Address</div><div class="value">{{ $transaction['wallet_address'] ?? 'N/A' }}</div></div>
        <div class="row"><div class="label">Transaction Hash</div><div class="value">{{ $transaction['transaction_hash'] ?? 'N/A' }}</div></div>
        <div class="row"><div class="label">Balance After</div><div class="value">{{ $transaction['balance_after'] ?? 'N/A' }}</div></div>
        <div class="row"><div class="label">Source</div><div class="value">{{ $transaction['source_table'] }} #{{ $transaction['source_id'] }}</div></div>

        <div class="footer">
            <p>This receipt was generated from Bitmonie transaction history records.</p>
            <p>Account: {{ $user->fullname ?? $user->email }} | User ID: {{ $user->id }}</p>
        </div>
    </div>
</body>
</html>
