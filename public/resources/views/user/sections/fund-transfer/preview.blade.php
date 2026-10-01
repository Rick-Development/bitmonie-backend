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
                    $method  = $temp_data->data->beneficiary->method;
                    $data    = $temp_data->data;
                    $charges = $temp_data->data->charges;
                    $precision = 2;
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
                            <span class="text--success enter-amount">{{ get_amount($charges->request_amount, $charges->sender_currency, $precision) }}</span>
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
                            <span class="text--warning fees">{{ get_amount($charges->total_charge, $charges->sender_currency, $precision) }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="lab la-get-pocket"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Receiver Will Get') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--danger will-get">{{ get_amount($charges->request_amount, $charges->sender_currency, $precision) }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-receipt"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Transaction Type') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--success enter-amount">{{ $method->name }}</span>
                        </div>
                    </div>

                    @if ($method->slug != Str::slug(global_const()::TRX_MOBILE_WALLET_TRANSFER))
                        <div class="preview-list-item">
                            <div class="preview-list-left">
                                <div class="preview-list-user-wrapper">
                                    <div class="preview-list-user-icon">
                                        <i class="las la-file-invoice"></i>
                                    </div>
                                    <div class="preview-list-user-content">
                                        <span>{{ __('Beneficiary Sub Type') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="preview-list-right">
                                <span>{{ global_const()::TRX_SUB_TYPE[$data->beneficiary->beneficiary_subtype] }}</span>
                            </div>
                        </div>
                        @if ($method->slug == Str::slug(global_const()::TRX_OTHER_BANK_TRANSFER))
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-university"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __('Bank name') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ $data->beneficiary->bank->name }}</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-building"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __('Branch Name') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ $data->beneficiary->branch->name }}</span>
                                </div>
                            </div>
                        @endif
                        @if ($data->beneficiary->beneficiary_subtype == global_const()::TRX_ACCOUNT_NUMBER)
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-address-card"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __('Account Number') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="text--success">{{ $data->beneficiary->account_number }}</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-id-card-alt"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __('Account Holder Name') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="text--base">{{ $data->beneficiary->account_holder_name }}</span>
                                </div>
                            </div>
                        @else
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-address-card"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __('Card Number') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ $data->beneficiary->card_number }}</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-id-card-alt"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __('Card Holder Name') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ $data->beneficiary->card_holder_name }}</span>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="preview-list-item">
                            <div class="preview-list-left">
                                <div class="preview-list-user-wrapper">
                                    <div class="preview-list-user-icon">
                                        <i class="las la-university"></i>
                                    </div>
                                    <div class="preview-list-user-content">
                                        <span>{{ __('Mobile Bank Name') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="preview-list-right">
                                <span>{{ $data->beneficiary->mobile_bank->name }}</span>
                            </div>
                        </div>
                        <div class="preview-list-item">
                            <div class="preview-list-left">
                                <div class="preview-list-user-wrapper">
                                    <div class="preview-list-user-icon">
                                        <i class="las la-address-card"></i>
                                    </div>
                                    <div class="preview-list-user-content">
                                        <span>{{ __('Account Number') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="preview-list-right">
                                <span class="text--success">{{ $data->beneficiary->account_number }}</span>
                            </div>
                        </div>
                        <div class="preview-list-item">
                            <div class="preview-list-left">
                                <div class="preview-list-user-wrapper">
                                    <div class="preview-list-user-icon">
                                        <i class="las la-id-card-alt"></i>
                                    </div>
                                    <div class="preview-list-user-content">
                                        <span>{{ __('Account Holder Name') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="preview-list-right">
                                <span class="text--base">{{ $data->beneficiary->account_holder_name }}</span>
                            </div>
                        </div>
                    @endif

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
                            <span class="text--info payable">{{ $data->remark ?? 'N/A' }}</span>
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
                            <span class="text--info last payable">{{ get_amount($charges->payable, $charges->sender_currency, $precision) }}</span>
                        </div>
                    </div>
                </div>

                <div class="conformation-btn pt-4">
                    <form action="{{ setRoute('user.fund-transfer.preview.submit') }}" method="POST">
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
