@extends('user.layouts.master')

@push('css')

@endpush

@section('content')
<div class="row justify-content-center mt-30">
    <div class="col-lg-8 col-md-10">
        <div class="transfer-preview">
            <div class="transfer-preview-area">
                <div class="preview-area-title pb-10">
                    <h3 class="title">{{ __($page_title) }}</h3>
                </div>
                @php
                    $instance = $temp_data->data->instance;
                @endphp

                <div class="preview-list-wrapper">
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-receipt"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Request Amount') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--success enter-amount">{{ get_amount($instance->amount->requested_amount, $instance->amount->default_currency) }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-sync-alt"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Exchange Rate') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="exchange-rate">1 {{ get_default_currency_code() }} = {{ get_amount($instance->amount->exchange_rate,$instance->amount->sender_cur_code,2) }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-battery-half"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Total Fees & Charges') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="fees">{{ get_amount($instance->amount->total_charge, $instance->amount->default_currency) }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="lab la-get-pocket"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Will Get') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="will-get">{{ get_amount($instance->amount->will_get, $instance->amount->default_currency) }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-money-check-alt"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span class="last">{{ __('Total Payable Amount') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--info last payable">{{ get_amount($instance->amount->total_amount, $instance->amount->sender_cur_code) }}</span>
                        </div>
                    </div>
                </div>
                <div class="conformation-btn pt-4">
                    <form action="{{ route('user.add.money.preview.submit') }}" method="POST">
                        @csrf
                        <input type="hidden" name="temp_token" value="{{ $temp_data->identifier }}">
                    
                        <a href="javascript:void(0)" class="btn--base w-100 btn-loading" data-bs-toggle="modal" data-bs-target="#checkPin">{{ __('Confirm') }} <i class="las la-chevron-right"></i></a>
                        @include('user.components.modal.pin-check')
                    </form>
                </div>
                
             </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
    $('.finalConfirmed').hide();
    $('.pinCheck').on('keyup',function(e){
        var url = "{{ route('user.check.pin') }}";
        var pin = $(this).val();
        var token = '{{ csrf_token() }}';
        if ($(this).attr('name') == 'pin') {
            var data = {pin:pin,_token:token}
        }
        $.post(url,data,function(response) {
            if(response == 1){
                if($('.exist_pi').hasClass('text--danger')){
                    $('.exist_pi').removeClass('text--danger');
                }
                $('.exist_pi').text(`{{ __('Pin matched successfully.') }}`).addClass('text--success');
                $('.finalConfirmed').show();
                $('.finalConfirmed').attr('disabled',false)
            } else {

                if($('.exist_pi').hasClass('text--success')){
                    $('.exist_pi').removeClass('text--success');
                }
                $('.exist_pi').text('{{ __("Your entered pin does not matched.") }}').addClass('text--danger');
                $('.finalConfirmed').attr('disabled',true)
                $('.finalConfirmed').hide();
                return false
            }

        });
    });
</script>
@endpush
