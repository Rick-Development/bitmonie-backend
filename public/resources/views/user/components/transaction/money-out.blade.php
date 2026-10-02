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
                            <i class="las la-arrow-up"></i>
                        </div>
                        <div class="dashboard-list-user-content">
                            <h4 class="title">{{ __("Money Out From") }} <span class="text--warning">{{ $item->details->charges->sender_currency_code }}</span></h4>
                            <span class="{{ $transaction->stringStatus->class }}">{{ __($transaction->stringStatus->value) }} &nbsp; <span class="text-secondary">#{{ $transaction->trx_id }}</span></span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-list-right">
                    <h4 class="main-money text--danger">{{ get_amount($item->request_amount,$item->user_wallet->currency->code,$precesion) }}</h4>
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
                        <span class="text--danger">{{ get_amount($item->request_amount,$item->user_wallet->currency->code,$precesion) }}</span>
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
                        <span>1 {{ $item->details->charges->sender_currency_code  }} = {{ get_amount($item->details->charges->exchange_rate,$item->gateway_currency->currency_code,$precesion) }}</span>
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
                        <span>{{ get_amount($item->details->charges->total_charge,$item->details->charges->sender_currency_code,$precesion) }}</span>
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
                        <span>{{ get_amount($item->details->charges->total_payable,$item->details->charges->sender_currency_code,$precesion) }}</span>
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
                        <span>{{ get_amount($item->details->charges->will_get,$item->details->charges->gateway_currency_code,$precesion) }}</span>
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
                @if ($transaction->status == payment_gateway_const()::STATUSREJECTED)
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-times-circle"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __("Reject Reason") }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--danger">{{ $transaction->reject_reason }}</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endisset
