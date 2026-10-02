@component('mail::message')

# Crypto Withdrawal {{ ucfirst($details['status'] ?? 'Updated') }}

Hello {{ $user->firstname ?? $user->username ?? 'there' }},

We are writing to inform you that the status of your crypto withdrawal is now **{{ strtolower($details['status'] ?? 'updated') }}**.

Please find the withdrawal details below:

@component('mail::table')

| Detail                                  | Value                                          |
| --------------------------------------- | ---------------------------------------------- |
| **Amount**                              | {{ $details['amount'] ?? 'N/A' }}              |
| **Coin**                                | {{ strtoupper($details['coin'] ?? 'N/A') }}    |
| **Network**                             | {{ strtoupper($details['network'] ?? 'N/A') }} |
| **Recipient Address**                   | {{ $details['recipient_address'] ?? 'N/A' }}   |
| **Transaction Hash**                    | {{ $details['transaction_hash'] ?? 'N/A' }}    |
| **Status**                              | {{ ucfirst($details['status'] ?? 'N/A') }}     |
| @if(!empty($details['failure_reason'])) |                                                |
| **Reason**                              | {{ $details['failure_reason'] }}               |
| @endif                                  |                                                |
| **Timestamp**                           | {{ $details['timestamp'] ?? 'N/A' }}           |
| @endcomponent                           |                                                |

@if(strtolower($details['status'] ?? '') === 'failed')
Unfortunately, your crypto withdrawal could not be completed. If you need assistance, please contact our support team and provide the transaction details above.
@elseif(strtolower($details['status'] ?? '') === 'completed' || strtolower($details['status'] ?? '') === 'success')
Your crypto withdrawal has been successfully processed. Please retain the transaction details above for your records.
@else
Your withdrawal is currently being processed. You will receive another notification when the status changes.
@endif

Thank you for using {{ config('app.name') }}.

Kind regards,<br>
**{{ config('app.name') }}**
@endcomponent
