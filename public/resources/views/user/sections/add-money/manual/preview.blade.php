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
                    $data = $temp_data->data;
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
                            <span class="text--success enter-amount">{{ get_amount($data->amount->requested_amount, $data->amount->default_currency) }}</span>
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
                            <span class="exchange-rate">1 {{ get_default_currency_code() }} = {{ get_amount($data->amount->exchange_rate,$data->amount->sender_cur_code,4) }}</span>
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
                            <span class="fees">{{ get_amount($data->amount->total_charge, $data->amount->default_currency) }}</span>
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
                            <span class="will-get">{{ get_amount($data->amount->will_get, $data->amount->default_currency) }}</span>
                        </div>
                    </div>

                    @foreach ($data->get_values as $item)
                        @if ($item->type == "file")
                            @php
                                $file_link = get_file_link("transaction",$item->value);
                            @endphp
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-money-check-alt"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __($item->label) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="text--info last payable">
                                        @if (its_image($item->value))
                                            <span class="product-sales-thumb">
                                                <a class="img-popup" data-rel="lightcase:myCollection" href="{{ $file_link }}">
                                                    <img src="{{ $file_link }}" alt="{{ $item->label }}">
                                                </a>
                                            </span>
                                        @else
                                            <span class="text--danger">
                                                @php
                                                    $file_info = get_file_basename_ext_from_link($file_link);
                                                @endphp
                                                <a href="{{ setRoute('file.download',["kyc-files",$item->value]) }}" >
                                                    {{ Str::substr($file_info->base_name ?? "", 0 , 20 ) ."..." . $file_info->extension ?? "" }}
                                                </a>
                                            </span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        @else
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-money-check-alt"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __( $item->label ) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="text--info payable">{{ (isset($item->value) && is_string($item->value)) ? $item->value : "" }}</span>
                                </div>
                            </div>
                        @endif
                    @endforeach

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
                            <span class="text--info last payable">{{ get_amount($data->amount->total_amount, $data->amount->default_currency) }}</span>
                        </div>
                    </div>

                </div>

                <div class="conformation-btn pt-4">
                    <form action="{{ setRoute('user.add.money.manual.preview.submit') }}" method="POST">
                        @csrf
                        <input type="hidden" name="temp_token" value="{{ $temp_data->identifier }}">
                        @if ($basic_settings->email_notification == true)
                            <button type="button" class="btn--base w-100 btn-loading verify-otp-btn">{{ __('Confirm') }} <i class="las la-chevron-right"></i></button>
                            @include('user.components.modal.verification-modal')
                        @else
                            <a href="javascript:void(0)" class="btn--base w-100 btn-loading" data-bs-toggle="modal" data-bs-target="#checkPin">{{ __('Confirm') }} <i class="las la-chevron-right"></i></a>
                        @endif
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
