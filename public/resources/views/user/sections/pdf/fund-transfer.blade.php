<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ __('Fund Transfer Preview') }} | {{ $basic_settings->site_name }}</title>
<style>
    body {
        font-family: 'Lora', serif;
        margin: 0;
        padding: 0;
    }
    .container {
        margin: 60px auto;
    }
    @media only screen and (max-width: 600px) {
        .container {
            width: 95%;
            margin: 60px auto;
       }
    }
    .banking-statement-area{
        border: 1px solid #ddd;
        border-radius: 10px;
        padding: 30px;
    }
    @media only screen and (max-width: 425px) {
        .banking-statement-area{
            padding: 15px;
        }
    }
    .header {
        text-align: center;
        margin-bottom: 20px;
    }
    .header img {
        width: 40%;
        max-width: 140px;
    }
    .statement-info {
        margin-bottom: 20px;
    }
    .transaction-table-container {
        overflow-x: auto;
    }
    .transaction-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    .transaction-table th, .transaction-table td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }
    .transaction-table th {
        background-color: #f2f2f2;
    }
    .summary {
        text-align: right;
    }
    .footer {
        text-align: center;
        margin-top: 50px;
    }
    .footer a{
        text-decoration: none;
    }

    /* Responsive Table Styling */
    @media only screen and (max-width: 600px) {
        .transaction-table {
            font-size: 12px;
        }
        .transaction-table th, .transaction-table td {
            padding: 6px;
        }
    }
    @media only screen and (max-width: 425px) {
        .transaction-table {
            font-size: 10px;
        }
        .transaction-table th, .transaction-table td {
            padding: 4px;
        }
    }
</style>
</head>
<body>
    @php
        $precision= 2;
        $method  = $transaction->details->beneficiary->method;
        $beneficiary = $transaction->details->beneficiary;
    @endphp
    <div class="container">
        <div class="banking-statement-area">
            <div class="header">
                <img src="{{ get_logo() }}" alt="Bank Logo">
                <h1>{{ __('Fund Transfer Preview') }}</h1>
            </div>
            <div class="statement-info">
                <p><strong>{{ __('Transfer Date') }} :</strong> {{ dateFormat('d F Y', @$transaction->created_at) }}</p>
                <p><strong>{{ __('Account Number') }} :</strong> {{ @$transaction->user->account_no }}</p>
                <p><strong>{{ __('Account Holder') }} :</strong> {{ @$transaction->user->fullname }}</p>
            </div>
            <div class="transaction-table-container">
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th>{{ __('Information') }}</th>
                            <th>{{ __('Description') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ __('Transaction Id') }}</td>
                            <td>{{ @$transaction->trx_id }}</td>
                        </tr>
                        <tr>
                            <td>{{ __('Transaction Type') }}</td>
                            <td>{{ @$method->name }}</td>
                        </tr>
                        <tr>
                            <td>{{ @$transaction->fundReceiverInfo->receiver_holder_title }}</td>
                            <td>{{ @$transaction->fundReceiverInfo->receiver_holder_value }}</td>
                        </tr>
                        <tr>
                            <td>{{ @$transaction->fundReceiverInfo->receiver_number_title }}</td>
                            <td>{{ @$transaction->fundReceiverInfo->receiver_number_value }}</td>
                        </tr>
                        @if (@$method->slug != Str::slug(global_const()::TRX_MOBILE_WALLET_TRANSFER))
                        <tr>
                            <td>{{ __('Beneficiary Sub Type') }}</td>
                            <td>{{ global_const()::TRX_SUB_TYPE[@$beneficiary->beneficiary_subtype] }}</td>
                        </tr>
                        @if (@$method->slug == Str::slug(global_const()::TRX_OTHER_BANK_TRANSFER))
                            <tr>
                                <td>{{ __('Bank Name') }}</td>
                                <td>{{ @$beneficiary->bank->name }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('Branch Name') }}</td>
                                <td>{{ @$beneficiary->branch->name }}</td>
                            </tr>
                        @endif
                    @else
                        <tr>
                            <td>{{ __('Mobile Bank Name') }}</td>
                            <td>{{ @$beneficiary->mobile_bank->name }}</td>
                        </tr>
                    @endif
                        <tr>
                            <td>{{ __('Request Amount') }}</td>
                            <td>{{ get_amount(@$transaction->request_amount, @$transaction->request_currency, @$precision) }}</td>
                        </tr>
                        <tr>
                            <td>{{ __('Fess & Charge') }}</td>
                            <td>{{ get_amount(@$transaction->total_charge, @$transaction->request_currency, @$precision) }}</td>
                        </tr>
                        <tr>
                            <td>{{ __('Receiver Will Get') }}</td>
                            <td>{{ get_amount(@$transaction->request_amount, @$transaction->payment_currency, @$precision) }}</td>
                        </tr>

                        <tr>
                            <td>{{ __('Remark') }}</td>
                            <td>{{ !empty(@$transaction->remark) ? @$transaction->remark : 'N/A' }}</td>
                        </tr>

                        <tr>
                            <td>{{ __('Status') }}</td>
                            <td><span class="{{ @$transaction->stringStatus->class }}">{{ @$transaction->stringStatus->value }}</span></td>
                        </tr>

                    </tbody>
                </table>
            </div>
            <div class="summary">
                <p><strong>{{ __('Total') }} :</strong> {{ get_default_currency_symbol() }}{{ get_amount(@$transaction->total_payable) }}</p>
            </div>
            <div class="footer">
                <p>{{ __('For inquiries, contact us at') }} <a href="#0">{{ @$basic_settings->site_name }}.</a></p>
            </div>
        </div>
    </div>
</body>
</html>
