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
                        @if ($transaction->userTrxType == global_const()::SEND)
                            <i class="las la-arrow-up"></i>
                        @else
                            <i class="las la-arrow-down"></i>
                        @endif
                    </div>
                    <div class="dashboard-list-user-content">
                        @if ($transaction->userTrxType == global_const()::SEND)
                            <h4 class="title">{{ __("Transfer money to") }} {{ " @" . @$transaction->fundReceiverInfo->receiver_holder_value }} </h4>
                        @else
                            <h4 class="title">{{ __("Received Money from")  . " @"  . $transaction->user->fullname }} </h4>
                        @endif
                        <span class="{{ $transaction->stringStatus->class }}">{{ __($transaction->stringStatus->value) }} &nbsp; <span class="text-secondary">#{{ $transaction->trx_id }}</span></span>
                    </div>
                </div>
            </div>
            <div class="dashboard-list-right">
                @if ($transaction->userTrxType == global_const()::SEND)
                    <h4 class="main-money text--base">{{ get_amount(@$transaction->request_amount, @$transaction->request_currency, $precesion) }}</h4>
                @else
                    <h4 class="main-money text--base">{{ get_amount(@$transaction->request_amount, @$transaction->payment_currency, $precesion) }}</h4>
                @endif
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

            @if ($transaction->userTrxType == global_const()::SEND)
                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="las la-exchange-alt"></i>

                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __("Transfer Type") }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span>{{ $transaction->type }}</span>
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
                        <span class="text--base">{{ get_amount(@$transaction->request_amount,@$transaction->request_currency,$precesion) }}</span>
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
                        <span>{{ get_amount(@$transaction->total_charge,@$transaction->request_currency,$precesion) }}</span>
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
                        <span>{{ get_amount(@$transaction->total_payable,@$transaction->request_currency,$precesion) }}</span>
                    </div>
                </div>
                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="las la-hand-holding-usd"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __("Receiver Will Get") }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span>{{ get_amount(@$transaction->request_amount,@$transaction->request_currency,$precesion) }}</span>
                    </div>
                </div>
                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="las la-address-card"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __(@$transaction->fundReceiverInfo->receiver_number_title) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span>{{ @$transaction->fundReceiverInfo->receiver_number_value }}</span>
                    </div>
                </div>
                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="las la-id-card-alt"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __(@$transaction->fundReceiverInfo->receiver_holder_title) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span >{{ @$transaction->fundReceiverInfo->receiver_holder_value }}</span>
                    </div>
                </div>
                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="la la-camera-retro"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __('Remark') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span>{{ $transaction->remark ?? 'N/A' }}</span>
                    </div>
                </div>
                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="las la-balance-scale"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __('Available Balance') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span class="text--info">{{ get_amount($transaction->available_balance, @$transaction->request_currency,$precesion) }}</span>
                    </div>
                </div>
            @else
                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="las la-wallet"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __("Received Amount") }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span class="text--base">{{ get_amount(@$transaction->request_amount,@$transaction->user_wallet->currency->code) }}</span>
                    </div>
                </div>

                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="las la-balance-scale"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __('Available Balance') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span class="text--info">{{ get_amount($transaction->receiver_wallet->balance, @$transaction->payment_currency,$precesion) }}</span>
                    </div>
                </div>

                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="la la-camera-retro"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __('Remark') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span>{{ !empty($transaction->remark) ? $transaction->remark : 'N/A' }}</span>
                    </div>
                </div>
            @endif

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
                    <span class="{{ $transaction->stringStatus->class }}">{{ __($transaction->stringStatus->value) }}</span>
                </div>
            </div>

            <div class="preview-list-item">
                <div class="preview-list-left">
                    <div class="preview-list-user-wrapper">
                        <div class="preview-list-user-icon">
                            <i class="las la-download"></i>
                        </div>
                        <div class="preview-list-user-content">
                            <span>{{ __("Downlaod Statement") }}</span>
                        </div>
                    </div>
                </div>
                <div class="preview-list-right">
                    <span class="text--base"><a href="{{ route('user.fund-transfer.pdf.download', $transaction->trx_id) }}">{{ __('Downlaod') }}</a></span>
                </div>
            </div>
        </div>
    </div>
</div>
@endisset
