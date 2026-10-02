<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ __('Salary Disbursement') }} | {{ $basic_settings->site_name }}</title>
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
                <h1>{{ __('Salary Disbursement') }}</h1>
            </div>
            <div class="statement-info">
                <p><strong>{{__('Date')}}:</strong> {{ dateFormat('d F Y', now()) }}</p>
            </div>
            <div class="transaction-table-container">
                <table class="transaction-table custom-table">
                    <thead>
                        <tr>
                            <th>{{__('Trx ID')}}</th>
                            <th>{{__('Trx Type')}}</th>
                            <th>{{ __('Salary') }}</th>
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
                            <p><strong>{{ __('Total Amount') }}:</strong>{{ get_amount($total_amount,$default_symbol) }}</p>
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
