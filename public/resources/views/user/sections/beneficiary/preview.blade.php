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
                                    <i class="las la-university"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Beneficiary Type') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span>{{ $data->method->name }}</span>
                        </div>
                    </div>

                    @if ($data->method->slug != Str::slug(global_const()::TRX_MOBILE_WALLET_TRANSFER))
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
                                <span>{{ global_const()::TRX_SUB_TYPE[$data->beneficiary_subtype] }}</span>
                            </div>
                        </div>

                        <!-- For Trx Other Bank -->
                        @if ($data->method->slug == Str::slug(global_const()::TRX_OTHER_BANK_TRANSFER))
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
                                    <span>{{ $data->bank->name }}</span>
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
                                    <span>{{ $data->branch->name }}</span>
                                </div>
                            </div>
                        @endif

                        <!-- For Trx Account Number -->
                        @if ($data->beneficiary_subtype == global_const()::TRX_ACCOUNT_NUMBER)
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
                                    <span>{{ $data->account_number }}</span>
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
                                    <span>{{ $data->account_holder_name }}</span>
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
                                    <span>{{ $data->card_number }}</span>
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
                                    <span>{{ $data->card_holder_name }}</span>
                                </div>
                            </div>
                        @endif

                    @else
                        <!-- For Mobile Wallet -->
                        <div class="preview-list-item">
                            <div class="preview-list-left">
                                <div class="preview-list-user-wrapper">
                                    <div class="preview-list-user-icon">
                                        <i class="las la-university"></i>
                                    </div>
                                    <div class="preview-list-user-content">
                                        <span>{{ __('Mobile Bank name') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="preview-list-right">
                                <span>{{ $data->mobile_bank->name }}</span>
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
                                <span>{{ $data->account_number }}</span>
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
                                <span>{{ $data->account_holder_name }}</span>
                            </div>
                        </div>
                    @endif

                   <div class="preview-list-item">
                       <div class="preview-list-left">
                           <div class="preview-list-user-wrapper">
                               <div class="preview-list-user-icon">
                                   <i class="las la-envelope"></i>
                               </div>
                               <div class="preview-list-user-content">
                                   <span>{{ __('Email') }}</span>
                               </div>
                           </div>
                       </div>
                       <div class="preview-list-right">
                           <span>{{ $data->email ?? 'N/A' }}</span>
                       </div>
                   </div>
                </div>
                <div class="conformation-btn pt-4">
                    <form action="{{ setRoute('user.beneficiary.confirm', $type) }}" method="POST">
                        @csrf
                        <input type="hidden" name="temp_token" value="{{ $temp_data->identifier }}">
                        @if ($basic_settings->email_notification == true)
                            <button type="button" class="btn--base w-100 btn-loading verify-otp-btn">{{ __('Confirm') }} <i class="las la-chevron-right"></i></button>
                            @include('user.components.modal.verification-modal')
                        @else
                            <button type="submit" class="btn--base w-100 btn-loading">{{ __('Confirm') }} <i class="las la-chevron-right"></i></button>
                        @endif
                    </form>
                </div>
             </div>
        </div>
    </div>
</div>
@endsection

@push('script')

@endpush
