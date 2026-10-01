
@extends('frontend.layouts.master')

@section('content')
   <!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    End Header
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<section class="forgot-password  ptb-80">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-6 col-lg-7 col-md-10">
                <div class="forgot-password-area reset-password-area">
                    <div class="account-wrapper">
                        <div class="account-logo text-center">
                            <a href="{{ setRoute('frontend.index') }}" class="site-logo">
                                <img src="{{ get_logo() }}" alt="logo">
                            </a>
                        </div>
                        <div class="forgot-password-content pt-3">
                            <h3 class="title">{{ __('Reset Your Forgotten Password?') }}</h3>
                            <p>{{ __('Take control of your account by resetting your password. Our password recovery page guides you through the necessary steps to securely reset your password.') }}.</p>
                        </div>
                        <form action="{{ setRoute('user.password.forgot.send.code') }}" method="POST" class="account-form pt-20">
                            @csrf
                            <div class="row ml-b-20">
                                <div class="col-lg-12 form-group pb-20">
                                    <input type="email" class="form-control form--control" name="credentials" placeholder="{{ __('Enter Email') }}..." required value="{{ old('email') }}">
                                </div>
                                <div class="col-lg-12 form-group text-center">
                                    <button type="submit" class="btn--base btn-loading w-100">{{ __('Continue') }}</button>
                                </div>
                                <div class="col-lg-12 text-center">
                                    <div class="account-item">
                                        <label>{{__('Already Have An Account?')}} <a href="{{ setRoute('user.login') }}" class="text--base">{{ __('Login Now') }}</a></label>
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
        Start Footer
 ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
@endsection
