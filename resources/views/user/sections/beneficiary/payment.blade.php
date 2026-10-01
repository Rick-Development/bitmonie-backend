@extends('user.layouts.master')

@push('css')

@endpush

@section('breadcrumb')
    @include('user.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("user.dashboard"),
        ]
    ], 'active' => __("My Receipant")])
@endsection

@section('content')
    <div class="row mb-20-none">
        <div class="col-xl-12 col-lg-12 mb-20">
            <div class="custom-card mt-10">
                <div class="dashboard-header-wrapper">
                    <h4 class="title">{{ __($page_title) }}</h4>
                </div>
                <div class="card-body">
                    <form action="{{ setRoute('user.recipient.payment.submit',$token) }}" class="card-form onload-from" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-xl-12 col-lg-12 form-group">
                                <label>{{ __("Sending Purpose") }} <span>*</span></label>
                                <select class="form--control select2-basic" name="purpose">
                                    <option selected disabled>Select Purpose</option>
                                    @foreach ($sending_purposes as $item)
                                        <option value="{{ $item->id }}">{{ $item->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-12 col-lg-12 form-group">
                                <label>{{ __("Remarks") }} <span>({{ __("Optional") }})</span></label>
                                <textarea class="form--control" placeholder="Write Here..." name="remark"></textarea>
                            </div>
                            <div class="col-xl-10 col-lg-9 form-group gateway d-none">
                                <label>{{ __("Payment Gateway") }} <span>*</span></label>
                                <select class="form--control select2-basic" name="gateway">
                                    <option selected disabled>Select Gateway</option>
                                    @foreach ($payment_gateways as $item)
                                        <option value="{{ $item->code }}">{{ $item->name }} @if ($item->isManual())
                                            (Manual)
                                        @endif</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-xl-12 col-lg-12">
                            <button class="btn--base w-100">{{ __("Proceed") }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    {{-- Page Script --}}
    <script>
        "use strict";
        (function ($) {
        $('#gateway').on('click',function () {
            $('.gateway').removeClass('d-none');
            $('.wallet').addClass('d-none');
        })
        $('#fund').on('click',function () {
            $('.gateway').addClass('d-none');
            $('.wallet').removeClass('d-none');
        })
        })(jQuery);

    </script>
@endpush
