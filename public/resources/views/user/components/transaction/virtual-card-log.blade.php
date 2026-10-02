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
                        <h4 class="title">{{ str_replace("-"," ",$transaction->remark ?? '') }}</h4>
                        <span class="badge badge--warning ms-2">{{ __($transaction->stringStatus->value) ?? "" }}</span>
                    </div>
                </div>
            </div>
            <div class="dashboard-list-right">
                <h4 class="main-money text--base">{{ get_amount($transaction->total_payable,$transaction->request_currency) }}</h4>
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
                            <span>{{ __("Request Amount") }}</span>
                        </div>
                    </div>
                </div>
                <div class="preview-list-right">
                    <span>{{ get_amount($transaction->request_amount,$transaction->request_currency) }}</span>
                </div>
            </div>
            <div class="preview-list-item">
                <div class="preview-list-left">
                    <div class="preview-list-user-wrapper">
                        <div class="preview-list-user-icon">
                            <i class="lab la-artstation"></i>
                        </div>
                        <div class="preview-list-user-content">
                            <span>{{ __("Total Fees & Charges") }}</span>
                        </div>
                    </div>
                </div>
                <div class="preview-list-right">
                    <span>{{ get_amount($transaction->total_charge,$transaction->request_currency) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endisset