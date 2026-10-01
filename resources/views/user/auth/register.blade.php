@extends('frontend.layouts.master')
@php
    $data           = App\Models\Admin\UsefulLink::where('type',global_const()::USEFUL_LINK_PRIVACY_POLICY)->first();
    $app_local      = get_default_language_code();
    $default        = App\Constants\LanguageConst::NOT_REMOVABLE;
    $slug           = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::REGISTER_SECTION);
    $register       = App\Models\Admin\SiteSections::getData($slug)->first();
@endphp


@section('content')
    <!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Start Banner Section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<section class="login-section bg-overlay  bg_img" data-background="{{ get_image($register->value->image ?? '', 'site-section') }}">
    <div class="container">
        <div class="user-login-area">
            <div class="row justify-content-center">
                <div class="col-xl-6 col-lg-6 col-md-10">
                    <div class="account-form-area account-form">
                        <h3 class="title">{{ $register->value->language->$app_local->title ?? $register->value->language->$default->title ?? '' }}</h3>
                        <p>{{ $register->value->language->$app_local->heading ?? $register->value->language->$default->heading ?? '' }}</p>
                        <div class="account-select-item">
                            <label>{{ __("Account Type") }} <span>*</span></label>
                            <select class="select2-basic select-area py-0 w-100 account-type">
                                <option selected disabled>{{ __("Select One") }}</option>
                                <option value="{{ global_const()::PERSONAL_ACCOUNT }}">{{ __("Personal Account") }}</option>
                                <option value="{{ global_const()::BUSINESS_ACCOUNT }}">{{ __("Business Account") }}</option>
                            </select>
                        </div>
                        <div class="personal-account ptb-30 select-account" data-select-target="{{ global_const()::PERSONAL_ACCOUNT }}" style="display: none;">
                            <form action="{{ setRoute('user.register.submit') }}" class="account-form" method="POST">
                                @csrf
                                <input type="hidden" name="account_type" value="{{ global_const()::PERSONAL_ACCOUNT }}">
                                <div class="row">
                                    <div class="col-lg-6 col-md-6 form-group">
                                        <input type="text" class="form-control form--control" name="firstname" placeholder="{{ __('First Name') }}">
                                    </div>
                                    <div class="col-lg-6 col-md-6 form-group">
                                        <input type="text" class="form-control form--control" name="lastname" placeholder="{{ __('Last Name') }}">
                                    </div>
                                    <div class="col-lg-12 form-group">
                                        <input type="email" class="form-control form--control" name="email" placeholder="{{ __('Email') }}">
                                    </div>
                                    <div class="col-lg-12 form-group">
                                        <label for="">{{ __('Country') }} <span>*</span></label>
                                        <select name="country" class="form-control select2-auto-tokenize country-select" data-placeholder="{{ __('Select Country') }}" data-old="{{ old('country') }}"></select>
                                    </div>
                                    <div class="col-lg-12 form-group show_hide_password">
                                        <input type="password" class="form-control form--control" name="password" placeholder="{{ __('Password') }}">
                                        <a href="#0" class="show-pass"><i class="fa fa-eye-slash" aria-hidden="true"></i></a>
                                    </div>
                                    <div class="col-lg-12 form-group show_hide_password">
                                        <input type="password" class="form-control form--control" name="password_confirmation" placeholder="{{ __('Password Confirm') }}">
                                        <a href="#0" class="show-pass"><i class="fa fa-eye-slash" aria-hidden="true"></i></a>
                                    </div>
                                    @if ($basic_settings->agree_policy)
                                    <div class="col-lg-12 form-group">
                                        <div class="custom-check-group">
                                            <input type="checkbox" name="agree" id="level-1">
                                            @php
                                                $data = App\Models\Admin\UsefulLink::where('type',global_const()::USEFUL_LINK_PRIVACY_POLICY)->first();
                                            @endphp
                                            <label for="level-1">{{ __('I have agreed with') }} <a href="{{ setRoute('frontend.useful.links',$data->slug) }}">{{ __('Terms Of Use & Privacy Policy') }}</a></label>
                                        </div>
                                    </div>
                                    @endif
                                    <div class="col-lg-12 form-group text-center">
                                        <button type="submit" class="btn--base w-100">{{ __('Register Now') }}</button>
                                    </div>
                                    <div class="col-lg-12 text-center">
                                        <div class="account-item">
                                            <label>{{ __('Already Have An Account') }}? <a href="{{ setroute('user.login') }}">{{ __('Login Now') }}</a></label>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="business-account ptb-30 select-account" data-select-target="{{ global_const()::BUSINESS_ACCOUNT }}" style="display: none;">
                            <form action="{{ setRoute('user.register.submit') }}" class="account-form" method="POST">
                                @csrf
                                <input type="hidden" name="account_type" value="{{ global_const()::BUSINESS_ACCOUNT }}">
                                <div class="row">
                                    <div class="col-lg-6 form-group">
                                        <input type="text" class="form-control form--control" name="firstname" placeholder="{{ __('First Name') }}">
                                    </div>
                                    <div class="col-lg-6 form-group">
                                        <input type="text" class="form-control form--control" name="lastname" placeholder="{{ __('Last Name') }}">
                                    </div>
                                    <div class="col-lg-12 form-group">
                                        <input type="text" class="form-control form--control" name="company_name" placeholder="{{ __('Company Name') }}">
                                    </div>
                                    <div class="col-lg-12 form-group">
                                        <input type="email" class="form-control form--control" name="email" placeholder="{{ __('Email') }}">
                                    </div>
                                    <div class="col-lg-12 form-group">
                                        <select name="country" class="form-control select2-auto-tokenize country-select" data-placeholder="{{ __('Select Country') }}" data-old="{{ old('country') }}"></select>
                                    </div>
                                    <div class="col-lg-12 form-group show_hide_password">
                                        <input type="password" class="form-control form--control" name="password" placeholder="{{ __('Password') }}">
                                        <a href="#0" class="show-pass"><i class="fa fa-eye-slash" aria-hidden="true"></i></a>
                                    </div>
                                    <div class="col-lg-12 form-group show_hide_password">
                                        <input type="password" class="form-control form--control" name="password_confirmation" placeholder="{{ __('Password Confirm') }}">
                                        <a href="#0" class="show-pass"><i class="fa fa-eye-slash" aria-hidden="true"></i></a>
                                    </div>
                                    @if ($basic_settings->agree_policy)
                                    <div class="col-lg-12 form-group">
                                        <div class="custom-check-group">
                                            <input type="checkbox" name="agree" id="level-2">
                                            @php
                                                $data = App\Models\Admin\UsefulLink::where('type',global_const()::USEFUL_LINK_PRIVACY_POLICY)->first();
                                            @endphp
                                            <label for="level-2">{{ __('I have agreed with') }} <a href="{{ setRoute('frontend.useful.links',$data->slug) }}">{{ __('Terms Of Use & Privacy Policy') }}</a></label>
                                        </div>
                                    </div>
                                    @endif
                                    <div class="col-lg-12 form-group text-center">
                                        <button type="submit" class="btn--base btn-loading w-100">{{ __('Register Now') }}</button>
                                    </div>
                                    <div class="col-lg-12 text-center">
                                        <div class="account-item">
                                            <label>{{ __('Already Have An Account?') }} <a href="{{ setroute('user.login') }}">{{ __('Login Now') }}</a></label>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    End Banner Section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
@endsection

@push('script')
<script>
        $('.account-type').on('change', function() {
            let accountType = $(this).val();
            if (accountType === "{{ global_const()::PERSONAL_ACCOUNT }}") {
                $('.personal-account').show();
                $('.business-account').hide();
            } else if (accountType === "{{ global_const()::BUSINESS_ACCOUNT }}") {
                $('.personal-account').hide();
                $('.business-account').show();
            }
        });
</script>
    <script>
        getAllCountries("{{ setRoute('global.countries') }}",$(".country-select"));
        $(document).ready(function(){
            $("select[name=country]").change(function(){
                var phoneCode = $("select[name=country] :selected").attr("data-mobile-code");
                placePhoneCode(phoneCode);
            });
            setTimeout(() => {
                var phoneCodeOnload = $("select[name=country] :selected").attr("data-mobile-code");
                placePhoneCode(phoneCodeOnload);
            }, 400);
        });
    </script>
    <script>
        $(".account-type").change(function(){
            var targetItem = $(this).val();
            selectContainItem(targetItem);
        });
    
        $(document).ready(function() {
            var professionSelectedItem = $(".account-type").val();
            selectContainItem(professionSelectedItem);
        });
    
    
        function selectContainItem(targetItem) {
            $(".select-account").slideUp(300);
            if(targetItem == null) return false;
            if(targetItem.length > 0) {
                var findTargetItem = $(".select-account");
                $.each(findTargetItem, function(index,item) {
                    if($(item).attr("data-select-target") == targetItem) {
                        $(item).slideDown(300);
                    }else {
                        $(item).slideUp(300);
                    }
                })
            }
        }
    </script>

@endpush
