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
    ], 'active' => __("Web Settings")])
@endsection

@section('content')
    <div class="custom-card">
        <div class="card-header">
            <h6 class="title">{{ __("Basic Settings") }}</h6>
        </div>
        <div class="card-body">
            <form class="card-form" method="POST" action="{{ setRoute('admin.web.settings.basic.settings.update') }}">
                @csrf
                @method("PUT")
                <div class="row">
                    <div class="col-xl-6 col-lg-6 form-group">
                        <label>{{ __("Site Base Color") }}*</label>
                        <div class="picker">
                            <input type="color" value="{{ old('base_color',$basic_settings->base_color) }}" class="color color-picker">
                            <input type="text" autocomplete="off" spellcheck="false" class="color-input" value="{{ old('base_color',$basic_settings->base_color) }}" name="base_color">
                        </div>
                    </div>
                    <div class="col-xl-6 col-lg-6 form-group">
                        @include('admin.components.form.input',[
                            'label'         => __("Site Name")."*",
                            'type'          => "text",
                            'class'         => "form--control",
                            'placeholder'   => __("Write Here")."...",
                            'name'          => "site_name",
                            'value'         => old('site_name',$basic_settings->site_name),
                        ])
                    </div>

                    <div class="col-xl-6 col-lg-6 form-group">
                        @include('admin.components.form.input',[
                            'label'         => __("Web Version")."*",
                            'type'          => "text",
                            'class'         => "form--control",
                            'placeholder'   => __("Write Here")."...",
                            'name'          => "web_version",
                            'value'         => old('web_version',$basic_settings->web_version),
                        ])
                    </div>
                    <div class="col-xl-6 col-lg-6 form-group">
                        @include('admin.components.form.input',[
                            'label'         => __("Site Title")."*",
                            'type'          => "text",
                            'class'         => "form--control",
                            'placeholder'   => __("Write Here")."...",
                            'name'          => "site_title",
                            'value'         => old('site_title',$basic_settings->site_title),
                        ])
                    </div>
                    <div class="col-xl-6 col-lg-6 form-group">
                        <label>{{ __("OTP Expiration") }}*</label>
                        <div class="input-group">
                            <input type="number" class="form--control" value="{{ old('otp_exp_seconds',$basic_settings->otp_exp_seconds) }}" name="otp_exp_seconds">
                            <span class="input-group-text">{{ __("Seconds") }}</span>
                        </div>
                    </div>
                    <div class="col-xl-6 col-lg-6 form-group">
                        <label>{{ __("Referral Bonus") }}*</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form--control" value="{{ old('referral_bonus',$basic_settings->referral_bonus ?? '500') }}" name="referral_bonus">
                            <span class="input-group-text">{{ __("NGN") }}</span>
                        </div>
                    </div>
                    <div class="col-xl-6 col-lg-6 form-group">
                        <label>{{ __("Referral Min Deposit") }}*</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form--control" value="{{ old('referral_min_deposit',$basic_settings->referral_min_deposit ?? '10000') }}" name="referral_min_deposit">
                            <span class="input-group-text">{{ __("NGN") }}</span>
                        </div>
                    </div>
                    <div class="col-xl-6 col-lg-6 form-group">
                        <label>{{ __("TimeZone") }}*</label>
                        <select name="timezone" class="form--control select2-auto-tokenize timezone-select" data-old="{{ old('timezone',$basic_settings->timezone) }}">
                            <option selected disabled>{{ __("Select Timezone") }}</option>
                        </select>
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        <label>{{ __("KYC Provider") }}*</label>
                        <select name="kyc_provider" class="form--control select2-basic" id="kyc_provider_select">
                            <option value="youverify" {{ old('kyc_provider', $basic_settings->kyc_provider) == 'youverify' ? 'selected' : '' }}>{{ __("YouVerify") }}</option>
                            <option value="safehaven" {{ old('kyc_provider', $basic_settings->kyc_provider) == 'safehaven' ? 'selected' : '' }}>{{ __("SafeHaven") }}</option>
                        </select>
                    </div>

                    <div class="row kyc-provider-fields" id="youverify_fields" style="{{ old('kyc_provider', $basic_settings->kyc_provider) == 'youverify' ? '' : 'display:none;' }}">
                        <div class="col-xl-4 col-lg-4 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("YouVerify API Key")."*",
                                'type'          => "text",
                                'class'         => "form--control",
                                'placeholder'   => __("Write Here")."...",
                                'name'          => "youverify_key",
                                'value'         => old('youverify_key',$basic_settings->youverify_key),
                            ])
                        </div>
                        <div class="col-xl-4 col-lg-4 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("YouVerify Public Key")."*",
                                'type'          => "text",
                                'class'         => "form--control",
                                'placeholder'   => __("Write Here")."...",
                                'name'          => "youverify_public_key",
                                'value'         => old('youverify_public_key',$basic_settings->youverify_public_key),
                            ])
                        </div>
                        <div class="col-xl-4 col-lg-4 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("YouVerify Webhook Secret")."*",
                                'type'          => "text",
                                'class'         => "form--control",
                                'placeholder'   => __("Write Here")."...",
                                'name'          => "youverify_webhook_key",
                                'value'         => old('youverify_webhook_key',$basic_settings->youverify_webhook_key),
                            ])
                        </div>
                    </div>

                    <div class="row kyc-provider-fields" id="safehaven_fields" style="{{ old('kyc_provider', $basic_settings->kyc_provider) == 'safehaven' ? '' : 'display:none;' }}">
                        <div class="col-xl-3 col-lg-3 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("SafeHaven Client ID")."*",
                                'type'          => "text",
                                'class'         => "form--control",
                                'placeholder'   => __("Write Here")."...",
                                'name'          => "safehaven_client_id",
                                'value'         => old('safehaven_client_id',$basic_settings->safehaven_client_id),
                            ])
                        </div>
                        <div class="col-xl-3 col-lg-3 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("SafeHaven API URL")."*",
                                'type'          => "text",
                                'class'         => "form--control",
                                'placeholder'   => __("https://api.safehavenmfb.com")."...",
                                'name'          => "safehaven_api_url",
                                'value'         => old('safehaven_api_url',$basic_settings->safehaven_api_url),
                            ])
                        </div>
                        <div class="col-xl-3 col-lg-3 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("SafeHaven Debit Account Number")."*",
                                'type'          => "text",
                                'class'         => "form--control",
                                'placeholder'   => __("Write Here")."...",
                                'name'          => "safehaven_debit_account",
                                'value'         => old('safehaven_debit_account',$basic_settings->safehaven_debit_account),
                            ])
                        </div>
                        <div class="col-xl-3 col-lg-3 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("SafeHaven Bank Code")."*",
                                'type'          => "text",
                                'class'         => "form--control",
                                'placeholder'   => __("090286")."...",
                                'name'          => "safehaven_bank_code",
                                'value'         => old('safehaven_bank_code',$basic_settings->safehaven_bank_code),
                            ])
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group">
                            <label>{{ __("SafeHaven Client Assertion") }}*</label>
                            <textarea name="safehaven_client_assertion" class="form--control" placeholder="{{ __("Write Here") }}...">{{ old('safehaven_client_assertion',$basic_settings->safehaven_client_assertion) }}</textarea>
                        </div>
                    </div>

                    <div class="col-xl-12 col-lg-12 mt-4 mb-2">
                        <h5 class="title border-bottom pb-2">{{ __("Sudo Account Configuration") }}</h5>
                        <p class="text-muted fs-6">{{ __("Configure Sudo card issuance and settlement account information. If left blank, values fallback to environment variables.") }}</p>
                    </div>

                    <div class="row">
                        <div class="col-xl-6 col-lg-6 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("Sudo Base URL"),
                                'type'          => "text",
                                'class'         => "form--control",
                                'placeholder'   => __("https://api.sudo.africa"),
                                'name'          => "sudo_base_url",
                                'value'         => old('sudo_base_url',$basic_settings->sudo_base_url),
                            ])
                        </div>
                        <div class="col-xl-6 col-lg-6 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("Sudo API Key"),
                                'type'          => "password",
                                'class'         => "form--control",
                                'placeholder'   => __("Enter Sudo API Key"),
                                'name'          => "sudo_api_key",
                                'value'         => old('sudo_api_key',$basic_settings->sudo_api_key),
                            ])
                        </div>
                        <div class="col-xl-4 col-lg-4 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("Sudo Settlement Bank Code"),
                                'type'          => "text",
                                'class'         => "form--control",
                                'placeholder'   => __("e.g. 058 or 090286"),
                                'name'          => "sudo_settlement_bank_code",
                                'value'         => old('sudo_settlement_bank_code',$basic_settings->sudo_settlement_bank_code),
                            ])
                        </div>
                        <div class="col-xl-4 col-lg-4 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("Sudo Settlement Account Number"),
                                'type'          => "text",
                                'class'         => "form--control",
                                'placeholder'   => __("e.g. 0123456789"),
                                'name'          => "sudo_settlement_account_number",
                                'value'         => old('sudo_settlement_account_number',$basic_settings->sudo_settlement_account_number),
                            ])
                        </div>
                        <div class="col-xl-4 col-lg-4 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("Sudo Settlement Account Name"),
                                'type'          => "text",
                                'class'         => "form--control",
                                'placeholder'   => __("Account Name"),
                                'name'          => "sudo_settlement_account_name",
                                'value'         => old('sudo_settlement_account_name',$basic_settings->sudo_settlement_account_name),
                            ])
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("Sudo Settlement Debit Account ID"),
                                'type'          => "text",
                                'class'         => "form--control",
                                'placeholder'   => __("e.g. 64a1..."),
                                'name'          => "sudo_settlement_debit_account_id",
                                'value'         => old('sudo_settlement_debit_account_id',$basic_settings->sudo_settlement_debit_account_id),
                            ])
                        </div>
                    </div>

                    <div class="col-xl-12 col-lg-12 mt-4 mb-2">
                        <h5 class="title border-bottom pb-2">{{ __("Fincra Configuration") }}</h5>
                        <p class="text-muted fs-6">{{ __("Configure Fincra endpoint and credentials. If left blank, values fallback to environment variables.") }}</p>
                    </div>

                    <div class="row">
                        <div class="col-xl-6 col-lg-6 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("Fincra Base URL"),
                                'type'          => "text",
                                'class'         => "form--control",
                                'placeholder'   => __("https://sandboxapi.fincra.com"),
                                'name'          => "fincra_base_url",
                                'value'         => old('fincra_base_url',$basic_settings->fincra_base_url),
                            ])
                        </div>
                        <div class="col-xl-6 col-lg-6 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("Fincra Business ID"),
                                'type'          => "text",
                                'class'         => "form--control",
                                'placeholder'   => __("Fincra Business ID"),
                                'name'          => "fincra_business_id",
                                'value'         => old('fincra_business_id',$basic_settings->fincra_business_id),
                            ])
                        </div>
                        <div class="col-xl-6 col-lg-6 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("Fincra Secret Key"),
                                'type'          => "password",
                                'class'         => "form--control",
                                'placeholder'   => __("Enter Fincra Secret Key"),
                                'name'          => "fincra_secret_key",
                                'value'         => old('fincra_secret_key',$basic_settings->fincra_secret_key),
                            ])
                        </div>
                        <div class="col-xl-6 col-lg-6 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("Fincra Public Key"),
                                'type'          => "text",
                                'class'         => "form--control",
                                'placeholder'   => __("Enter Fincra Public Key"),
                                'name'          => "fincra_public_key",
                                'value'         => old('fincra_public_key',$basic_settings->fincra_public_key),
                            ])
                        </div>
                        <div class="col-xl-6 col-lg-6 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("Fincra Webhook Encryption Key"),
                                'type'          => "password",
                                'class'         => "form--control",
                                'placeholder'   => __("Enter Webhook Encryption Key"),
                                'name'          => "fincra_webhook_encryption_key",
                                'value'         => old('fincra_webhook_encryption_key',$basic_settings->fincra_webhook_encryption_key),
                            ])
                        </div>
                        <div class="col-xl-6 col-lg-6 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("Fincra Settlement Destination"),
                                'type'          => "text",
                                'class'         => "form--control",
                                'placeholder'   => __("Optional settlement destination"),
                                'name'          => "fincra_settlement_destination",
                                'value'         => old('fincra_settlement_destination',$basic_settings->fincra_settlement_destination),
                            ])
                        </div>
                    </div>
                </div>
                <div class="col-xl-12 col-lg-12">
                    @include('admin.components.button.form-btn',[
                        'class'         => "w-100 btn-loading",
                        'text'          => __("Update"),
                        'permission'    => "admin.web.settings.basic.settings.update",
                    ])
                </div>
            </form>
        </div>
    </div>
    <div class="custom-card mt-15">
        <div class="card-header">
            <h6 class="title">{{ __("Activation Settings") }}</h6>
        </div>
        <div class="card-body">
            <div class="custom-inner-card mt-10 mb-10">
                <div class="card-inner-body">
                    <div class="row mb-10-none">
                        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 form-group">
                            @include('admin.components.form.switcher',[
                                'label'         => __('User Registration'),
                                'name'          => 'user_registration',
                                'value'         => old('user_registration',$basic_settings->user_registration),
                                'options'       => [__('Activated') => 1,__('Deactivated') => 0],
                                'onload'        => true,
                                'permission'    => "admin.web.settings.basic.settings.activation.update",
                            ])
                        </div>
                        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 form-group">
                            @include('admin.components.form.switcher',[
                                'label'         => __('Secure Password'),
                                'name'          => 'secure_password',
                                'value'         => old('secure_password',$basic_settings->secure_password),
                                'options'       => [__('Activated') => 1,__('Deactivated') => 0],
                                'onload'        => true,
                                'permission'    => "admin.web.settings.basic.settings.activation.update",
                            ])
                        </div>
                        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 form-group">
                            @include('admin.components.form.switcher',[
                                'label'         => __('Agree Policy'),
                                'name'          => 'agree_policy',
                                'value'         => old('agree_policy',$basic_settings->agree_policy),
                                'options'       => [__('Activated') => 1,__('Deactivated') => 0],
                                'onload'        => true,
                                'permission'    => "admin.web.settings.basic.settings.activation.update",
                            ])
                        </div>
                        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 form-group">
                            @include('admin.components.form.switcher',[
                                'label'         => __('Force SSL'),
                                'name'          => 'force_ssl',
                                'value'         => old('force_ssl',$basic_settings->force_ssl),
                                'options'       => [__('Activated') => 1,__('Deactivated') => 0],
                                'onload'        => true,
                                'permission'    => "admin.web.settings.basic.settings.activation.update",
                            ])
                        </div>
                        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 form-group">
                            @include('admin.components.form.switcher',[
                                'label'         => __('Email Verification'),
                                'name'          => 'email_verification',
                                'value'         => old('email_verification',$basic_settings->email_verification),
                                'options'       => [__('Activated') => 1,__('Deactivated') => 0],
                                'onload'        => true,
                                'permission'    => "admin.web.settings.basic.settings.activation.update",
                            ])
                        </div>
                        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 form-group">
                            @include('admin.components.form.switcher',[
                                'label'         => __('Email Notification'),
                                'name'          => 'email_notification',
                                'value'         => old('email_notification',$basic_settings->email_notification),
                                'options'       => [__('Activated') => 1,__('Deactivated') => 0],
                                'onload'        => true,
                                'permission'    => "admin.web.settings.basic.settings.activation.update",
                            ])
                        </div>
                        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 form-group">
                            @include('admin.components.form.switcher',[
                                'label'         => __('Push Notification'),
                                'name'          => 'push_notification',
                                'value'         => old('push_notification',$basic_settings->push_notification),
                                'options'       => [__('Activated') => 1,__('Deactivated') => 0],
                                'onload'        => true,
                                'permission'    => "admin.web.settings.basic.settings.activation.update",
                            ])
                        </div>
                        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 form-group">
                            @include('admin.components.form.switcher',[
                                'label'         => __('KYC Verification'),
                                'name'          => 'kyc_verification',
                                'value'         => old('kyc_verification',$basic_settings->kyc_verification),
                                'options'       => [__('Activated') => 1,__('Deactivated') => 0],
                                'onload'        => true,
                                'permission'    => "admin.web.settings.basic.settings.activation.update",
                            ])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(document).ready(function() {
            $(".color-picker").on("input",function() {
                $(this).siblings("input").val($(this).val());
            });

            // Get Timezone
            getTimeZones("{{ setRoute('global.timezones') }}");

            switcherAjax("{{ setRoute('admin.web.settings.basic.settings.activation.update') }}");

            $("#kyc_provider_select").on("change", function() {
                var provider = $(this).val();
                $(".kyc-provider-fields").hide();
                if (provider == 'youverify') {
                    $("#youverify_fields").show();
                } else if (provider == 'safehaven') {
                    $("#safehaven_fields").show();
                }
            });
        });
    </script>
@endpush