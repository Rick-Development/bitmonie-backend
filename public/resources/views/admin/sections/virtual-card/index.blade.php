@extends('admin.layouts.master')

@push('css')

@endpush

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __($page_title)])
@endsection

@section('content')
<div class="custom-card">
    <div class="card-header">
        <h6 class="title">{{ __($page_title) }}</h6>
    </div>
    <div class="card-body">
        <form class="card-form" action="{{ setRoute('admin.virtual.card.api.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method("PUT")
            <div class="row mb-10-none">
                <div class="col-xl-12   col-lg-12 form-group">
                    <label>{{ __("Name") }}*</label>
                    <select class="form--control nice-select" name="api_method">
                        <option value="strowallet" @if(@$api->config->name == 'strowallet') selected @endif>@lang('Strowallet Api')</option>
                    </select>
                </div>
                <div class="col-xl-12 col-lg-12 form-group configForm" id="strowallet">
                    <div class="row" >
                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 form-group">
                            <label>{{ __("Public Key") }}*</label>
                            <div class="input-group append">
                                <span class="input-group-text"><i class="las la-key"></i></span>
                                <input type="text" class="form--control" name="strowallet_public_key" value="{{ @$api->config->strowallet_public_key }}">
                            </div>
                        </div>
                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 form-group">
                            <label>{{ __("Secret key") }}*</label>
                            <div class="input-group append">
                                <span class="input-group-text"><i class="las la-key"></i></span>
                                <input type="text" class="form--control" name="strowallet_secret_key" value="{{ @$api->config->strowallet_secret_key }}">
                            </div>
                        </div>
                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 form-group">
                            <label>{{ __("Base URL") }}*</label>
                            <div class="input-group append">
                                <span class="input-group-text"><i class="las la-link"></i></span>
                                <input type="text" class="form--control" name="strowallet_url" value="{{ @$api->config->strowallet_url }}">
                            </div>
                        </div>
                        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 form-group">
                            @include('admin.components.form.switcher', [
                                'label'         => __('Mode').'*',
                                'value'         => old('strowallet_mode',@$api->config->strowallet_mode),
                                'name'          => "strowallet_mode",
                                'options'       => [__('Live') => global_const()::LIVE,__('Sandbox') => global_const()::SANDBOX]
                            ])
                        </div>
                    </div>
                </div>
                <div class="col-xl-12 col-lg-12 form-group">
                    @include('admin.components.form.input',[
                        'label'         => __("Card Limit").'*',
                        'name'          => 'card_limit',
                        'value'         => old('card_limit',@$api->card_limit),
                        'placeholder'   => __("Enter 1-3 Only.")
                    ])
                </div>
                <div class="col-xl-12 col-lg-12 form-group">
                    @include('admin.components.form.input-text-rich',[
                        'label'         => __("Card Details")."*",
                        'name'          => 'card_details',
                        'value'         => old('card_details',@$api->card_details),
                        'placeholder'   => __( "Write Here..."),
                    ])
                </div>
                <div class="col-xl-12 col-lg-12 form-group">
                    <label for="card-image">{{ __("Background Image") }}</label>
                    <div class="col-12 col-sm-6 m-auto">
                        @include('admin.components.form.input-file',[
                            'label'         => false,
                            'class'         => "file-holder m-auto",
                            'old_files_path'    => files_asset_path('card-api'),
                            'name'          => "image",
                            'old_files'         => old('image',@$api->image)
                        ])
                    </div>
                </div>
                <div class="col-xl-12 col-lg-12 form-group">
                    @include('admin.components.button.form-btn',[
                        'class'         => "w-100 btn-loading",
                        'text'          => __("Update"),
                        'permission'    => "admin.virtual.card.api.update"
                    ])
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
@push('script')
<script>
    (function ($) {
        "use strict";
        var method = '{{ @$api->config->name}}';
        if (!method) {
            method = 'strowallet';
        }

        apiMethod(method);
        $('select[name=api_method]').on('change', function() {
            var method = $(this).val();
            apiMethod(method);
        });

        function apiMethod(method){
            $('.configForm').addClass('d-none');
            if(method != 'other') {
                $(`#${method}`).removeClass('d-none');
            }
        }

    })(jQuery);

</script>
@endpush