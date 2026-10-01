@component('mail::message')
# Crypto Withdrawal Failed

A crypto withdrawal failed and needs review.

@component('mail::table')
| Detail | Value |
| --- | --- |
| User ID | {{ $details['user_id'] ?? 'N/A' }} |
| Amount | {{ $details['amount'] ?? 'N/A' }} |
| Coin | {{ strtoupper($details['coin'] ?? 'N/A') }} |
| Network | {{ strtoupper($details['network'] ?? 'N/A') }} |
| Recipient Address | {{ $details['recipient_address'] ?? 'N/A' }} |
| Transaction Hash | {{ $details['transaction_hash'] ?? 'N/A' }} |
| Reference | {{ $details['transaction_reference'] ?? 'N/A' }} |
| Status | {{ ucfirst($details['status'] ?? 'failed') }} |
@if(!empty($details['failure_reason']))
| Reason | {{ $details['failure_reason'] }} |
@endif
| Timestamp | {{ $details['timestamp'] ?? 'N/A' }} |
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
