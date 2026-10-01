<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ __('Statement') }} | {{ $basic_settings->site_name }}</title>
<!-- favicon -->
<link rel="shortcut icon" href="assets/images/logo/fav_icon.webp" type="image/x-icon">
<style>
    body {
        font-family: 'Lora', serif;
        margin: 0;
        padding: 0;
    }
    .container {
        width: 100%;
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
        font-size: 18px;
    }
    .transaction-table-container {
        overflow-x: auto;
    }
    .transaction-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .transaction-table th, .transaction-table td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }
    .transaction-table th {
        background-color: #f2f2f2;
        text-transform: uppercase;
    }
    .summary {
        text-align: left;
        font-size: 14px
    }
    .signature{
        text-align: right;
        margin-top: 80px;
    }
    .signature p{
        text-align: center;
        border-top: 1px dashed #828282;
        padding-top: 5px;
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
    <div class="container">
        <div class="banking-statement-area">
            <div class="header">
                <img src="{{ get_logo() }}" alt="Bank Logo">
                <h1>{{ __('Banking Statement') }}</h1>
            </div>
            <div class="statement-info">
                <p><strong>{{__('Statement Date')}}:</strong> {{ dateFormat('d F Y', now()) }}</p>
                @if ((isset($date['from_date']) && !empty($date['from_date'])) && (isset($date['to_date']) && !empty($date['to_date'])))
                    <p><strong>{{__('Time Period')}}:</strong> {{ $date['from_date'] }} {{ __("To") }} {{ $date['to_date'] }}</p>
                @else
                    <p><strong>{{__('Time Period')}}:</strong>{{ __("N/A") }}</p>   
                @endif
                <p><strong>{{ __('Account Holder') }}:</strong> {{ Auth::user()->fullname }}</p>
                <p><strong>{{ __('Account Number') }}:</strong> {{ Auth::user()->account_no }}</p>
            </div>



            <div class="transaction-table-container">
                <table class="transaction-table custom-table">
                    <thead>
                        <tr>
                            <th>{{__('Trx ID')}}</th>
                            <th>{{__('Trx Type')}}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Fees & Charge') }}</th>
                            <th>{{ __('Payable') }}</th>
                            <th>{{ __('Balance') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Time') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions  as $key => $item)
                            <tr>
                                <td>{{ $item->trx_id }}</td>
                                <td>{{ $item->type }}</td>
                                <td>{{ get_amount($item->request_amount, $item->request_currency) }}</td>
                                <td>
                                    @if ($item->userTrxType == payment_gateway_const()::SEND)
                                        {{ get_amount($item->total_charge, $item->request_currency) }}
                                    @else
                                    N/A
                                    @endif
                                </td>
                                <td>
                                    @if ($item->userTrxType == payment_gateway_const()::SEND)
                                        {{ get_amount($item->total_payable, $item->request_currency) }}
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td>{{ get_amount($item->available_balance, $item->request_currency) }}</td>
                                <td>{{ $item->userTrxType }}</td>
                                <td>
                                    <span>{{ $item->string_status->value }}</span>
                                </td>
                                <td>{{ $item->created_at->format('d-m-y h:i:s A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <table style="width: 100%;">
                <tr>
                    <td>
                        <div class="summary">
                            @php
                                $default_symbol = get_default_currency_symbol();
                            @endphp
                            <p><strong>{{ __('Total Transactions') }}:</strong>{{ $total_transaction }}</p>
                            <p><strong>{{ __('Total Withdrawals') }}:</strong> {{ @$default_symbol }}{{ get_amount($total_money_out) }}</p>
                            <p><strong>{{ __('Total Fund Transfer') }}:</strong> {{ @$default_symbol }}{{ get_amount($fund_transfer) }}</p>
                            <p><strong>{{ __('Total Fund Received') }}:</strong> {{ @$default_symbol }}{{ get_amount($fund_received) }}</p>
                            <p><strong>{{ __('Total Deposits') }}:</strong> {{ @$default_symbol }}{{ get_amount($total_add_money) }}</p>
                            <p><strong>{{ __('Available Balance') }}:</strong> {{ @$default_symbol }}{{ get_amount($user_wallet->balance) }}</p>
                        </div>
                    </td>
                    <td>
                        <div class="signature">
                            <span class="signicher-line"></span>
                            <p>Signature</p>
                        </div>
                    </td>
                </tr>
            </table>
            
            <div class="footer">
                <p>{{ __('For inquiries, contact us at') }} <a href="#0">{{ @$basic_settings->site_name }}.</a></p>
            </div>
        </div>
    </div>
</body>
</html>
