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
                        <i class="las la-dollar-sign"></i>
                    </div>
                    <div class="dashboard-list-user-content">
                        <h4 class="title">{{ __($transaction->type) ?? '' }} @if ($transaction->attribute == payment_gateway_const()::RECEIVED)
                            {{ __("From") }} {{ $transaction->details->fullname ?? "" }}
                        @else
                        {{ __("By") }} {{ $transaction->admin->fullname ?? "" }}
                        @endif                    
                        </h4>
                        @php
                            $type = ucfirst(strtolower($transaction->attribute));
                        @endphp
                        <span>{{ __($type) ?? "" }}</span>
                        <span class="{{ $transaction->stringStatus->class }} ms-2">{{ __($transaction->stringStatus->value) ?? "" }}</span>
                    </div>
                </div>
            </div>
            <div class="dashboard-list-right">
                <h4 class="main-money text--base">{{ get_amount($transaction->request_amount,$transaction->request_currency) }}</h4>
            </div>
        </div>
        <div class="preview-list-wrapper">
            <div class="preview-list-item">
                <div class="preview-list-left">
                    <div class="preview-list-user-wrapper">
                        <div class="preview-list-user-icon">
                            <i class="las la-exchange-alt"></i>
                        </div>
                        <div class="preview-list-user-content">
                            <span>{{ __("Transaction ID") }}</span>
                        </div>
                    </div>
                </div>
                <div class="preview-list-right">
                    <span>{{ $transaction->trx_id ?? "" }}</span>
                </div>
            </div>
            <div class="preview-list-item">
                <div class="preview-list-left">
                    <div class="preview-list-user-wrapper">
                        <div class="preview-list-user-icon">
                            <i class="las la-wallet"></i>
                        </div>
                        <div class="preview-list-user-content">
                            @if ($transaction->attribute == payment_gateway_const()::SEND)
                            <span>{{ __("Payable Amount") }}</span>
                            @else
                            <span>{{ __("Request Amount") }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="preview-list-right">
                    <span>{{ get_amount($transaction->request_amount,$transaction->request_currency) }}</span>
                </div>
            </div>
            @if ($transaction->attribute == payment_gateway_const()::SEND)
            <div class="preview-list-item">
                <div class="preview-list-left">
                    <div class="preview-list-user-wrapper">
                        <div class="preview-list-user-icon">
                            <i class="las la-wallet"></i>
                        </div>
                        <div class="preview-list-user-content">
                            <span>{{ __("Receiver Data") }}</span>
                        </div>
                    </div>
                </div>
                <div class="preview-list-right">
                    <span><a class="btn--base" href="{{ setRoute('user.transactions.download',['sd_id' => $transaction->salary_disbursement_id]) }}">{{ __("Download") }}</a></span>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endisset