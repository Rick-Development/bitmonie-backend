@extends('user.layouts.master')

@section('content')
<div class="transfer-money-area pt-3">
    <div class="row justify-content-center mb-40-none">
        <div class="col-lg-8 mb-40">
            <div class="transfer-preview-area">
              <div class="preview-list-wrapper">
                <div class="transfer-preview-area">
                    <div class="add-money-text pb-20">
                        <h4>{{ __("Add Money instructions") }}</h4>
                    </div>
                    <form class="row g-4 submit-form" method="POST" action="{{ setRoute('user.add.money.manual.submit',$token) }}" enctype="multipart/form-data">
                        @csrf

                        <div class="withdraw-instructions mb-4">
                            {!! $gateway->desc ?? "" !!}
                        </div>

                        <div class="row">
                            @include('user.components.payment-gateway.generate-dy-input',['input_fields' => array_reverse($gateway->input_fields)])
                        </div>

                        <div class="col-12 mt-5">
                            <button type="submit" class="btn--base w-100 text-center btn-loading">{{ __("Submit") }}</button>
                        </div>

                    </form>
                </div>
              </div>
            </div>
        </div>
    </div>
</div>

@endsection
