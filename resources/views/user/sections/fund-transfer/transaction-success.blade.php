@extends('user.layouts.master')

@push('css')

@endpush

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
        <div class="payment-conformation">
            <div class="payment-loader-wrapper">
                <div class="payment-loader">
                    <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                        <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none" />
                        <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
                    </svg>
                </div>
                <h4 class="title">{{ __('Transfer Money Successfully') }}.</h4>
                <div class="recive-dwonload-btn">
                    <a href="{{ setRoute('user.fund-transfer.index') }}" class="recive-btn"><i class="las la-angle-double-left"></i> {{ __('Transfer Again') }}</a>
                    <a href="{{ route('user.fund-transfer.pdf.download', $trx_id) }}" class="recive-btn"><i class="las la-download"></i> {{__('Download PDF')}}</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')

@endpush
