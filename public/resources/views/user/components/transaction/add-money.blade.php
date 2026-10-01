@isset($transaction)
@php
    $precesion = 2;
@endphp
<div class="dashboard-list-wrapper">
    <div class="dashboard-list-item-wrapper">
        <div class="dashboard-list-item sent">
            <div class="dashboard-list-left">
                <div class="dashboard-list-user-wrapper">
                    <div class="dashboard-list-user-icon">
                        <i class="las la-arrow-down"></i>
                    </div>
                    <div class="dashboard-list-user-content">
                        <h4 class="title">{{ __("Add Money via") }} {{ $transaction->gateway_currency->gateway->name }} @if (@$transaction->gateway_currency->gateway->isManual())
                            ({{ __("Manual") }})
                        @endif</h4>
                        <span class="{{ $transaction->stringStatus->class }}">{{ __($transaction->stringStatus->value) }} &nbsp; <span class="text-secondary">#{{ $transaction->trx_id }}</span></span>
                    </div>
                </div>
            </div>
            <div class="dashboard-list-right">
                <h4 class="main-money text--base">{{ get_amount(@$transaction->request_amount, @$transaction->user_wallet->currency->code, $precesion) }}</h4>
                <h6 class="exchange-money text--success">{{ get_amount(@$transaction->available_balance,@$transaction->user_wallet->currency->code, $precesion) }}</h6>
            </div>
        </div>
        <div class="preview-list-wrapper">
            <div class="preview-list-item">
                <div class="preview-list-left">
                    <div class="preview-list-user-wrapper">
                        <div class="preview-list-user-icon">
                            <i class="lab la-tumblr"></i>
                        </div>
                        <div class="preview-list-user-content">
                            <span>{{ __("Transaction ID") }}</span>
                        </div>
                    </div>
                </div>
                <div class="preview-list-right">
                    <span>{{ $transaction->trx_id }}</span>
                </div>
            </div>
            <div class="preview-list-item">
                <div class="preview-list-left">
                    <div class="preview-list-user-wrapper">
                        <div class="preview-list-user-icon">
                            <i class="las la-wallet"></i>
                        </div>
                        <div class="preview-list-user-content">
                            <span>{{ __("Request Amount") }}</span>
                        </div>
                    </div>
                </div>
                <div class="preview-list-right">
                    <span class="text--base">{{ get_amount(@$transaction->request_amount,@$transaction->user_wallet->currency->code, $precesion) }}</span>
                </div>
            </div>
            <div class="preview-list-item">
                <div class="preview-list-left">
                    <div class="preview-list-user-wrapper">
                        <div class="preview-list-user-icon">
                            <i class="las la-exchange-alt"></i>
                        </div>
                        <div class="preview-list-user-content">
                            <span>{{ __("Exchange Rate") }}</span>
                        </div>
                    </div>
                </div>
                <div class="preview-list-right">
                    <span>1 {{ get_default_currency_code() }} = {{ get_amount(@$transaction->gateway_currency->rate,@$transaction->gateway_currency->currency_code,$precesion) }}</span>
                </div>
            </div>
            <div class="preview-list-item">
                <div class="preview-list-left">
                    <div class="preview-list-user-wrapper">
                        <div class="preview-list-user-icon">
                            <i class="las la-file-invoice-dollar"></i>
                        </div>
                        <div class="preview-list-user-content">
                            <span>{{ __("Total Fees & Charges") }}</span>
                        </div>
                    </div>
                </div>
                <div class="preview-list-right">
                    <span>{{ get_amount(@$transaction->total_charge,@$transaction->gateway_currency->currency_code, $precesion) }}</span>
                </div>
            </div>
            <div class="preview-list-item">
                <div class="preview-list-left">
                    <div class="preview-list-user-wrapper">
                        <div class="preview-list-user-icon">
                            <i class="las la-coins"></i>
                        </div>
                        <div class="preview-list-user-content">
                            <span>{{ __("Payable Amount") }}</span>
                        </div>
                    </div>
                </div>
                <div class="preview-list-right">
                    <span>{{ get_amount(@$transaction->total_payable,@$transaction->user_wallet->currency->code, $precesion) }}</span>
                </div>
            </div>
            <div class="preview-list-item">
                <div class="preview-list-left">
                    <div class="preview-list-user-wrapper">
                        <div class="preview-list-user-icon">
                            <i class="las la-hand-holding-usd"></i>
                        </div>
                        <div class="preview-list-user-content">
                            <span>{{ __("Will Get") }}</span>
                        </div>
                    </div>
                </div>
                <div class="preview-list-right">
                    <span>{{ get_amount(@$transaction->request_amount,@$transaction->gateway_currency->currency_code, $precesion) }}</span>
                </div>
            </div>
            <div class="preview-list-item">
                <div class="preview-list-left">
                    <div class="preview-list-user-wrapper">
                        <div class="preview-list-user-icon">
                            <i class="las la-torah"></i>
                        </div>
                        <div class="preview-list-user-content">
                            <span>{{ __("Gateway Name") }}</span>
                        </div>
                    </div>
                </div>
                <div class="preview-list-right">
                    <span>{{ $transaction->gateway_currency->gateway->name }} @if (@$transaction->gateway_currency->gateway->isManual())
                        (Manual)
                    @endif</span>
                </div>
            </div>
            <div class="preview-list-item">
                <div class="preview-list-left">
                    <div class="preview-list-user-wrapper">
                        <div class="preview-list-user-icon">
                            <i class="las la-smoking"></i>
                        </div>
                        <div class="preview-list-user-content">
                            <span>{{ __("Status") }}</span>
                        </div>
                    </div>
                </div>
                <div class="preview-list-right">
                    <span class="{{ $transaction->stringStatus->class }}">{{ $transaction->stringStatus->value }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endisset
