@extends('layouts.master')

@push('css')

@endpush

@section('content')
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
                            <h4 class="title text-center">{{ __('Please enter the code') }}</h4>
                        <p class="d-block text-center">{{ __('We sent a 6 digit code here') }} <strong> {{ $user_email ?? '' }}</strong></p>
                        </div>
                        <form class="account-form pt-20" method="POST" action="{{ route('user.password.forgot.verify.code', $token) }}">
                            @csrf
                            <div class="row ml-b-20">
                                <div class="col-lg-12 form-group text-center">
                                    <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(1)'
                                        maxlength=1 required>
                                    <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(2)'
                                        maxlength=1 required>
                                    <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(3)'
                                        maxlength=1 required>
                                    <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(4)'
                                        maxlength=1 required>
                                    <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(5)'
                                        maxlength=1 required>
                                        <input class="otp" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(6)'
                                        maxlength=1 required>
                                </div>
                                <div class="col-lg-12 form-group ">
                                    <div class="time-area">{{ __('You can resend the code after') }} <span id="time"></span></div>
                                </div>
                                <div class="col-lg-12 form-group text-center">
                                    <button type="submit" class="btn--base btn w-100">{{ __('Submit') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
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

        var resendTime = "{{ $resend_time ?? 0 }}";
        var resendCodeLink = "{{ setRoute('user.password.forgot.resend.code',$token) }}";

        console.log(resendCodeLink);

        function resetTime (second = 20) {
            var coundDownSec = second;
            var countDownDate = new Date();
            countDownDate.setMinutes(countDownDate.getMinutes() + 120);
            var x = setInterval(function () {  // Get today's date and time
                var now = new Date().getTime();  // Find the distance between now and the count down date
                var distance = countDownDate - now;  // Time calculations for days, hours, minutes and seconds  var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((distance % (1000 * coundDownSec)) / (1000 * coundDownSec));
                var seconds = Math.floor((distance % (1000 * coundDownSec)) / 1000);  // Output the result in an element with id="time"
                document.getElementById("time").innerHTML =second + "s ";  // If the count down is over, write some text
                if (distance <= 0 || second <= 0 ) {
                    // alert();
                    clearInterval(x);
                    // document.getElementById("time").innerHTML = "RESEND";
                    document.querySelector(".time-area").innerHTML = `{{ __("Didn't get the code?") }} <a class='text--danger' href='${resendCodeLink}'>{{ __("Resend") }}</a>`;
                }
                second--
            }, 1000);
        }
        resetTime(resendTime);
    </script>
@endpush
