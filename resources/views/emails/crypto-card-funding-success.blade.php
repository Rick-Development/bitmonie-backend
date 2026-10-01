<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8">
    <title>Crypto Card Funding Failed</title>
</head>
<body>
    <h2>Crypto Card Funding Failed</h2>


<p>Hello {{ $user->name ?? 'Customer' }},</p>

<p>
    We regret to inform you that we were unable to complete your crypto card
    funding transaction.
</p>

<p>
    Please find the transaction details below:
</p>

<p>
    <strong>Amount:</strong>
    {{ $details['amount'] ?? '0' }}
    {{ strtoupper($details['coin'] ?? '') }}
</p>

@if(!empty($details['failure_reason']))
    <p>
        <strong>Reason:</strong>
        {{ $details['failure_reason'] }}
    </p>
@endif

@if(!empty($details['merchant_reference']))
    <p>
        <strong>Reference:</strong>
        {{ $details['merchant_reference'] }}
    </p>
@endif

<p>
    <strong>Status:</strong> Failed
</p>

<p>
    Your card was not credited as a result of this failed transaction,
    and no successful card funding was completed.
</p>

<p>
    If you believe this transaction was incorrectly marked as failed or
    require further assistance, please contact our support team and provide
    the reference above where available.
</p>

<p>
    Thank you for using {{ config('app.name') }}.
</p>

<p>
    Kind regards,<br>
    <strong>{{ config('app.name') }}</strong>
</p>


</body>
</html>
