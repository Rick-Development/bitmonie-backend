@extends('admin.layouts.master')

@push('css')
@endpush

@section('page-title')
    @include('admin.components.page-title', ['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb', [
        'breadcrumbs' => [
            [
                'name' => __('Dashboard'),
                'url' => setRoute('admin.dashboard'),
            ],
        ],
        'active' => __('Fund Transfer Logs'),
    ])
@endsection

@section('content')
    <div class="custom-card">
        <div class="card-header">
            <h6 class="title">{{ __('Sender') }} {{ __($page_title) }}</h6>
        </div>
        <div class="card-body">
            <form class="card-form">
                <div class="row align-items-center mb-10-none">
                    <div class="col-xl-4 col-lg-4 form-group">
                        <ul class="user-profile-list-two">
                            <li class="one">{{ __("Date") }} : <span>{{ $transaction->created_at->format("Y-m-d h:i A") }}</span></li>
                            <li class="two">{{ __("Transaction ID") }} : <span>{{ $transaction->trx_id }}</span></li>
                            <li class="three">{{ __("Email") }} : <span>{{ $transaction->creator->email }}</span></li>
                            <li class="four">{{ __("Type") }} : <span>{{ $transaction->type }}</span></li>
                            <li class="five">{{ __("Amount") }} : <span>{{ get_amount($transaction->request_amount,$transaction->creator_wallet->currency->code) }}</span></li>
                        </ul>
                    </div>
                    <div class="col-xl-4 col-lg-4 form-group">
                        <div class="user-profile-thumb">
                            <img src="{{ get_image($transaction->creator->image,'user-profile') }}" alt="payment">
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-4 form-group">
                        <ul class="user-profile-list two">
                            <li class="one">{{ __("Charge") }} : <span>{{ get_amount($transaction->total_charge,$transaction->request_currency) }}</span></li>
                            <li class="two">{{ __("After Charge") }} : <span>{{ get_amount(($transaction->request_amount + $transaction->total_charge),$transaction->request_currency) }}</span></li>
                            <li class="three">{{ __("Rate") }} : <span>1 {{ $transaction->request_currency }} = {{ get_amount($transaction->exchange_rate,$transaction->payment_currency,'double') }}</span></li>
                            <li class="four">{{ __("Payable Amount") }} : <span>{{ get_amount($transaction->total_payable,$transaction->payment_currency,'double') }}</span></li>
                            <li class="five">{{ __("Status") }} : <span class="{{ $transaction->StringStatus->class }}">{{ $transaction->StringStatus->value }}</span></li>
                        </ul>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="custom-card mt-3">
        <div class="card-header">
            <h6 class="title">{{ __('Receiver') }} {{ __($page_title) }}</h6>
        </div>
        <div class="card-body">
            <form class="card-form">
                <div class="row align-items-center mb-10-none">
                    <div class="col-xl-4 col-lg-4 form-group">
                        <ul class="user-profile-list-two">
                            <li class="two">{{ __("Method Name") }} : <span>{{ $transaction->otherBankName }}</span></li>
                            <li class="three">{{ __($transaction->fundReceiverInfo->receiver_holder_title) }}: <span>{{ __($transaction->fundReceiverInfo->receiver_holder_value) }}</span></li>
                            <li class="four">{{ __($transaction->fundReceiverInfo->receiver_number_title) }}: <span>{{ $transaction->fundReceiverInfo->receiver_number_value }}</span></li>
                        </ul>
                    </div>
                    <div class="col-xl-4 col-lg-4 form-group">
                        <div class="user-profile-thumb">
                            <img src="{{ get_image('default') }}" alt="payment">
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-4 form-group">
                        <ul class="user-profile-list two">
                            <li class="two">{{ __("Received Amount") }} : <span>{{ get_amount(($transaction->request_amount),$transaction->request_currency) }}</span></li>
                            <li class="three">{{ __("Rate") }} : <span>1 {{ $transaction->request_currency }} = {{ get_amount($transaction->exchange_rate,$transaction->payment_currency,'double') }}</span></li>
                            <li class="four">{{ __("Status") }} : <span class="{{ $transaction->StringStatus->class }}">{{ $transaction->StringStatus->value }}</span></li>
                        </ul>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('admin.components.modals.fund-transfer-reject',compact("transaction"))
@endsection



@push('script')
    <script>
        openModalWhenError("reject-modal","#reject-modal");
        $(".approve-btn").click(function(){
            var actionRoute = "{{ setRoute('admin.fund.transfer.log.approve') }}";
            var target      = "{{ $transaction->trx_id }}";
            var message     = `{{ __("Are you sure to approve this") }} ({{ $transaction->trx_id }}) {{ __("transaction") }}.`;
            openDeleteModal(actionRoute,target,message,"Approve","POST");
        });
    </script>
@endpush
