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
                            
                            <h4 class="title">{{ __("Balance Update") }} ({{ $transaction->details ?? '' }}) {{ __("by") }} {{ $transaction->admin->full_name }}</h4>
                            <span class="{{ $transaction->stringStatus->class }}">{{ __($transaction->stringStatus->value) }} &nbsp; <span class="text-secondary">#{{ $transaction->trx_id }}</span></span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-list-right">
                    <h4 class="main-money text--base">{{ get_amount(@$transaction->request_amount, @$transaction->request_currency, $precesion) }}</h4>
                </div>
            </div>
            
        </div>
    </div>
@endisset
