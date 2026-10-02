@extends('frontend.layouts.master')
@section('content')
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Start Account
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<section class="verification-otp ptb-80">
    <div class="container">
        <div class="row justify-content-center">
            <div class=" col-xl-6 col-lg-8 col-md-10 col-sm-12">
                <div class="verification-otp-area">
                    <div class="account-wrapper otp-verification">
                        <div class="account-logo text-center">
                            <a href="{{ setRoute('frontend.index') }}" class="site-logo">
                                <img src="{{ get_logo() }}" alt="logo">
                            </a>
                        </div>
                        <div class="verification-otp-content pt-3">
                        <h4 class="title text-center">{{ __("Two Factor Authorization") }}</h4>
                        <p class="d-block text-center">{{ __("Please enter your authorization code to access dashboard.") }}</p>
                        </div>
                        <form method="POST" class="account-form" action="{{ setRoute('user.authorize.google.2fa.submit') }}">
                            @csrf
                            <div class="row ml-b-20">
                                <div class="col-lg-12 form-group text-center">
                                    <input class="otp" type="text" name="code[]" oninput="digitValidate(this)" onkeyup="tabChange(1)" maxlength="1" required="">
                                    <input class="otp" type="text" name="code[]" oninput="digitValidate(this)" onkeyup="tabChange(2)" maxlength="2" required="">
                                    <input class="otp" type="text" name="code[]" oninput="digitValidate(this)" onkeyup="tabChange(3)" maxlength="1" required="">
                                    <input class="otp" type="text" name="code[]" oninput="digitValidate(this)" onkeyup="tabChange(4)" maxlength="1" required="">
                                    <input class="otp" type="text" name="code[]" oninput="digitValidate(this)" onkeyup="tabChange(5)" maxlength="1" required="">
                                    <input class="otp" type="text" name="code[]" oninput="digitValidate(this)" onkeyup="tabChange(6)" maxlength="1" required="">
                                </div>
                                <div class="col-lg-12 form-group text-center">
                                    <button type="submit" class="btn--base w-100 btn-loading">{{ __("Verify 2FA") }}</button>
                                </div>
                                <div class="col-lg-12 text-center">
                                    <div class="account-item">
                                        <label>{{ __('Already Have An Account?') }} <a href="{{ setRoute('user.login') }}" class="account-control-btn">{{ __('Login Here') }}</a></label>
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
    End Account
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
@endsection
@push('script')
<script>
    let digitValidate = function (ele) {
            ele.value = ele.value.replace(/[^0-9]/g, '');
        }

        let tabChange = function (val) {
            let ele = document.querySelectorAll('.otp');
            if (ele[val - 1].value != '') {
                ele[val].focus()
            } else if (ele[val - 1].value == '') {
                ele[val - 2].focus()
            }
        }
</script>
@endpush
