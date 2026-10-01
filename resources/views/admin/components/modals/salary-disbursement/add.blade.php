@if (admin_permission_by_name("admin.salary.disbursement.employee.store"))
    <div id="employee-add" class="mfp-hide large">
        <div class="modal-data">
            <div class="modal-header px-0">
                <h5 class="modal-title">{{ __("Add Employee") }}</h5>
            </div>
            <div class="modal-form-data">
                <form class="modal-form" method="POST" action="{{ setRoute('admin.salary.disbursement.employee.store',$company->username) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row mb-10-none">
                        <div class="col-xl-6 col-lg-6 form-group">
                            <label>{{ __("Employee Email") }}*</label>
                            <input type="text" name="user_email" class="user-email"  placeholder="{{ __("Enter Employee Email") }}...">
                            <label class="exist text-start"></label>
                        </div>
                        <div class="col-xl-6 col-lg-6 form-group">
                            <label>{{ __("Amount") }}</label>
                            <div class="input-group">
                                <input type="text" class="form--control number-input" name="amount" placeholder="{{ __("Enter Amount") }}...">
                                <span class="input-group-text">{{ get_default_currency_code($default_currency) }}</span>
                            </div>
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group d-flex align-items-center justify-content-between mt-4">
                            <button type="button" class="btn btn--danger modal-close">{{ __("Cancel") }}</button>
                            <button type="submit" class="btn btn--base ">{{ __("Add") }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push("script")
        <script>
            $(document).ready(function(){
                openModalWhenError("user_add","#user-add");
            });
            $(".user-email").keyup(function(){
                var checkUserURL    = "{{ setRoute('admin.salary.disbursement.check.user') }}";
                var email           = $(this).val();
                checkUser(checkUserURL,email);
                             
            });

            function checkUser(checkUserURL,email){
                $('.message').html('');
                $.post(checkUserURL,{email:email,_token:"{{ csrf_token() }}"},function(response){
                    if(response.own){
                    if($('.exist').hasClass('text--success')){
                        $('.exist').removeClass('text--success');
                    }
                    $('.exist').addClass('text--danger').text(response.own);
                    return false
                }
                if(response['data'] != null){
                    if($('.exist').hasClass('text--danger')){
                        $('.exist').removeClass('text--danger');
                    }
                    $('.exist').text(`Valid user.`).addClass('text--success');
                } else {
                    if($('.exist').hasClass('text--success')){
                        $('.exist').removeClass('text--success');
                    }
                    $('.exist').text('User doesn\'t  exists.').addClass('text--danger');
                    return false
                }
                }); 
            }
        </script>
    @endpush
@endif