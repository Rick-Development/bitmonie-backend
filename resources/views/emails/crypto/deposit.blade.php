@component('mail::message')

# Crypto Deposit {{ ($details['status'] ?? null) === 'failed' ? 'Failed' : 'Confirmed' }}

Hello {{ $user->firstname ?? $user->username ?? 'there' }},

We are writing to inform you that your crypto deposit has been **{{ ($details['status'] ?? null) === 'failed' ? 'marked as failed' : 'successfully confirmed' }}**.

Please find the transaction details below:

@component('mail::table')

| Detail                                  | Value                                            |
| --------------------------------------- | ------------------------------------------------ |
| **Amount**                              | {{ $details['amount'] ?? 'N/A' }}                |
| **Coin**                                | {{ strtoupper($details['coin'] ?? 'N/A') }}      |
| **Reference**                           | {{ $details['transaction_reference'] ?? 'N/A' }} |
| **Wallet Address**                      | {{ $details['wallet_address'] ?? 'N/A' }}        |
| **Status**                              | {{ ucfirst($details['status'] ?? 'confirmed') }} |
| @if(!empty($details['failure_reason'])) |                                                  |
| **Reason**                              | {{ $details['failure_reason'] }}                 |
| @endif                                  |                                                  |
| **Timestamp**                           | {{ $details['timestamp'] ?? 'N/A' }}             |
| @endcomponent                           |                                                  |

@if(($details['status'] ?? null) === 'failed')
If you believe this deposit should not have been marked as failed, please contact our support team and provide the transaction reference above so we can assist you.
@else
Your deposit has been successfully processed and the transaction has been recorded on your account.
@endif

Thank you for using {{ config('app.name') }}.

Kind regards,<br>
**{{ config('app.name') }}**
@endcomponent
