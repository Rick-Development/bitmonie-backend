@extends('user.layouts.master')

@push('css')

@endpush

@section('content')
<div class="transfer-money-area pt-3">
    <div class="row justify-content-center mb-40-none">
        <div class="col-lg-8 mb-40">
            <div class="transfer-preview-area">
              <div class="preview-list-wrapper">
                <div class="transfer-preview-area">
                    <div class="add-money-text pb-20">
                        <h4>{{ __("Withdraw instructions") }}</h4>
                    </div>
                    <form class="row g-4 submit-form" method="POST" action="{{ setRoute('user.money-out.instruction.submit',$token) }}" enctype="multipart/form-data">

                        @csrf

                        <div class="withdraw-instructions mb-4">
                            {!! $gateway->desc ?? "" !!}
                        </div>

                        <div class="row">
                            @include('user.components.payment-gateway.generate-dy-input',['input_fields' => array_reverse($gateway->input_fields)])
                        </div>

                        <div class="col-12 mt-5">
                            <button type="submit" class="btn--base w-100 btn-loading">{{__('Submit')}} <i class="las la-chevron-right"></i></button>
                        </div>
                    </form>
                </div>
              </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')

@endpush
