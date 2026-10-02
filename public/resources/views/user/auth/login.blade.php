@extends('frontend.layouts.master')
@php
    $app_local      = get_default_language_code();
    $default        = App\Constants\LanguageConst::NOT_REMOVABLE;
    $slug           = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::LOGIN_SECTION);
    $login          = App\Models\Admin\SiteSections::getData($slug)->first();
@endphp
@push('css')

@endpush

@section('content')
    <!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Start Banner Section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<section class="login-section bg-overlay  bg_img" data-background="{{ get_image($login->value->image ?? '' , 'site-section') }}">
    <div class="container">
        <div class="user-login-area">
            <div class="row justify-content-center">
                <div class="col-xl-6 col-lg-8 col-md-10">
                    <div class="account-form-area">
                        <h3 class="title">{{ $login->value->language->$app_local->title ?? $login->value->language->$app_local->title ?? '' }}</h3>
                        <p>{{ $login->value->language->$app_local->heading ?? $login->value->language->$app_local->heading ?? '' }}</p>
                        <form action="{{ route('user.login') }}" class="account-form" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-lg-12 form-group">
                                    <input type="email" class="form-control form--control" placeholder="{{ __('Enter Email') }}..." name="credentials" rqeuired value="{{ old('email') }}">
                                </div>
                                <div class="col-lg-12 form-group show_hide_password">
                                    <input type="password" class="form-control form--control" placeholder="{{ __('Enter Password') }}..." name="password" required>
                                    <a href="#0" class="show-pass"><i class="fa fa-eye-slash" aria-hidden="true"></i></a>
                                </div>
                                <div class="col-lg-12 form-group">
                                    <div class="forgot-item text-end">
                                        <label><a href="{{ setRoute('user.password.forgot') }}" class="text--base">{{ __('Forgot Password') }}?</a></label>
                                    </div>
                                </div>
                                <div class="col-lg-12 form-group text-center">
                                    <button type="submit" class="btn--base btn-loading w-100">{{ __('Login Now') }}</button>
                                </div>

                                <div class="col-lg-12 text-center">
                                    <div class="account-item">
                                        <label>{{ __("Don't Have An Account?") }} <a href="{{ setRoute('user.register') }}">{{ __('Register Now') }}</a></label>
                                    </div>
                                </div>
                            </div>
                        </form>
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

@endpush
