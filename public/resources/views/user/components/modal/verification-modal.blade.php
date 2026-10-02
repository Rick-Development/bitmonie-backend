<div class="modal fade" id="verification-modal" tabindex="-1" aria-labelledby="towfaVerifyerifi" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body">
          <p class="d-block text-center">{{ __('We sent a 6 digit code here') }} <strong class="to-address"></strong></p>
          <p class="d-block text-center">{{ __('Please Enter your Verification Code') }}.</p>
            <div class="row ml-b-20">
                <input type="hidden" class="resend_time" name="resend_time">
                <div class="col-lg-12 form-group text-center">
                    <input class="otp password_check" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(1)'
                        maxlength=1 required>
                    <input class="otp password_check" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(2)'
                        maxlength=1 required>
                    <input class="otp password_check" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(3)'
                        maxlength=1 required>
                    <input class="otp password_check" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(4)'
                        maxlength=1 required>
                    <input class="otp password_check" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(5)'
                        maxlength=1 required>
                        <input class="otp password_check" name="code[]" type="text" oninput='digitValidate(this)' onkeyup='tabChange(6)'
                        maxlength=1 required>
                </div>
                <div class="col-lg-12 form-group ">
                    <div class="time-area">{{ __('You can resend the code after') }} <span id="time"></span></div>
                </div>
                <div class="col-lg-12 form-group text-center">
                    <button type="submit" class="btn--base btn-loading btn w-100">{{ __('Submit') }}</button>
                </div>
            </div>
        </div>
      </div>
    </div>
 </div>

 @push('script')
     <script>

        let array_check = [];
        let digitValidate = function (ele) {
            ele.value = ele.value.replace(/[^0-9]/g, '');
            array_check.push(ele.value);
        }

        let tabChange = function (val) {
            let ele = document.querySelectorAll('.otp');
            if (ele[val - 1].value != '') {
                ele[val].focus()
            } else if (ele[val - 1].value == '') {
                ele[val - 2].focus()
            }
        }

        $(document).ready(function(){
            @if(session('error') || session('success'))
                var resendCodeLink = "{{ setRoute('user.verification-code.resend') }}";

                second = 56;
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
                        clearInterval(x);
                        // document.getElementById("time").innerHTML = "RESEND";
                        document.querySelector(".time-area").innerHTML = `Didn't get the code? <a class='text--danger' href='${resendCodeLink}'>Resend</a>`;
                    }
                    second--
                }, 1000);

                var modal = $('#verification-modal');

                modal.modal('show');
            @endif
        });

     </script>
 @endpush
