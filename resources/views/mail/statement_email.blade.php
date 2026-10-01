@component('mail::message')
# Your BitMonie Statement of Account

Hello {{ $user->firstname ?? 'User' }},

Your requested Statement of Account for the period ({{ $dateRange }}) has been generated and is attached to this email.

If you have any questions regarding your statement, please contact our support team.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
