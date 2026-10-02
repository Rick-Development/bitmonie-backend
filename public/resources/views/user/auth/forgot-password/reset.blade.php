@extends('frontend.layouts.master')

@push('css')

@endpush

@section('content')
<div class="new-password ptb-80">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-6 col-lg-8 col-md-10">
                <div class="new-password-area">
                    <div class="account-wrapper">
                        <span class="account-cross-btn"></span>
                        <div class="account-logo text-center">
                            <a href="{{ setRoute('frontend.index') }}" class="site-logo">
                                <img src="{{ get_logo() }}" alt="logo">
                            </a>
                        </div>
                        <form class="account-form pt-3" action="{{ setRoute('user.password.reset',$token) }}" method="POST">
                            @csrf
                            <div class="row ml-b-20">
                                <label>{{ __('Enter New Password') }}</label>
                                <div class="col-lg-12 form-group show_hide_password">
                                    <input type="password" name="password" class="form-control form--control"  placeholder="{{ __('Password') }}">
                                    <a href="javascript:void(0)" class="show-pass"><i class="fa fa-eye-slash" aria-hidden="true"></i></a>
                                </div>
                                <label>{{__('Enter Confirm Password')}}</label>
                                <div class="col-lg-12 form-group show_hide_password">
                                    <input type="password" name="password_confirmation" class="form-control form--control"  placeholder="{{ __('Password') }}">
                                    <a href="javascript:void(0)" class="show-pass"><i class="fa fa-eye-slash" aria-hidden="true"></i></a>
                                </div>
                                <div class="col-lg-12 form-group text-center pt-3">
                                    <button type="submit" class="btn--base btn-loading w-100">{{ __('Confirm') }}</button>
                                </div>
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
