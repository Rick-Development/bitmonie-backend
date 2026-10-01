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
                <div class="preview-list-wrapper">
                    @foreach ($preview_data as $key => $item)
                        <div class="preview-list-item">
                            <div class="preview-list-left">
                                <div class="preview-list-user-wrapper">
                                    <div class="preview-list-user-icon">
                                        <i class="{{ $item['icon'] }}"></i>
                                    </div>
                                    <div class="preview-list-user-content">
                                        <span @if (count($preview_data) == ($key))
                                            class="last"
                                        @endif>{{ $item['title'] }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="preview-list-right">
                                <span><span class="text--base @if (count($preview_data) == ($key))
                                    last
                                @endif">{{ $item['value'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="btn--base mt-10 w-100 payment-confirm-btn">{{ __("Confirm Payment") }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
    <script>
        $(".payment-confirm-btn").click(function(){
            var submitBtn = "{{ setRoute('user.recipient.payment.confirm',$token) }}";
            postFormAndSubmit(submitBtn,"{{ $token }}");
        });
    </script>
@endpush
