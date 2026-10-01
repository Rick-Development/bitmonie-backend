@extends('user.layouts.master')

@push('css')

@endpush

@section('breadcrumb')
    @include('user.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("user.dashboard"),
        ]
    ], 'active' => __("Add Recipient")])
@endsection

@section('content')
    <div class="add-beneficiary-area">
        <div class="row mb-20-none">
            <div class="col-xl-12 col-lg-12 mb-20">
                <div class="custom-card mt-10">
                    <div class="dashboard-header-wrapper">
                        <h4 class="title">{{ __($page_title) }}</h4>
                    </div>
                    <div class="card-body">
                        <form class="card-form add-recipient-item">
                            <div class="form-group transaction-type">
                                <label>{{ __('Beneficiary Type') }} <span>*</span></label>
                                <select class="form--control trx-type-select select2-auto-tokenize" name="method">
                                    <option disabled selected value="">{{ __('Select Type') }}</option>
                                    @foreach ($methods as $item)
                                        <option value="{{ $item->slug }}">
                                        {{ __($item->readableName) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="beneficiary-btn pt-3">
                                <button type="submit" class="btn--base btn-loading w-100">{{ __('Submit') }} <i class="las la-chevron-right"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        // Verify Otp
        $("select.trx-type-select").change(function() {
            getTrxFields($(this).val());
        });

        $(document).on('change', "select[name='beneficiary_subtype']", function(){
            getTrxSubFields($(this).val());
        });

        $(document).ready(function(){
            var selected_trx = $("select.trx-type-select").find(":selected").val();
            getTrxFields(selected_trx);
        });

        $(document).on("keyup","input[name=account_number]",function(){
            var selected_trx = $("select.trx-type-select").find(":selected").val();            
            if(selected_trx != 'own-bank-transfer') return false;
            getUserByAccount($(this).val(),"{{ setRoute('user.info.account') }}",$(this));
        });

        $(document).on('change', "select[name='bank']", function(){
            getBankBranch($(this).val());
        });

        function getUserByAccount(string,URL,errorPlace = null) {
            if(string.length < 3) {
                return false;
            }
            var CSRF = laravelCsrf();
            var data = {
                _token      : CSRF,
                text        : string,
            };

            $.post(URL,data,function() {
                // success
            }).done(function(response){
                if(response.data == null) {
                    if(errorPlace != null) {
                        if($(errorPlace).parent().find(".get-user-error").length > 0) {
                            $(errorPlace).parent().find(".get-user-error").text("Account doesn't exists");
                        }else {
                            $(`<span class="text--danger get-user-error mt-2">Account doesn't exists!</span>`).insertAfter($(errorPlace));
                        }
                        var user_infos = {
                            account_holder_name: '',
                            email              : '',
                        };

                        $.each(user_infos,function(index,item) {
                            $(errorPlace).parents("form").find("input[name="+index+"],textarea[name="+index+"]").val(item);
                        })
                    }

                }else {
                    if(errorPlace != null) {
                        $(errorPlace).parent().find(".get-user-error").remove();
                    }

                    var user = response.data;

                    var user_infos = {
                        account_holder_name: user.fullname,
                        email              : user.email,
                    };

                    $.each(user_infos,function(index,item) {
                        $(errorPlace).parents("form").find("input[name="+index+"],textarea[name="+index+"]").val(item);
                    })
                }
            }).fail(function(response) {
                var response = JSON.parse(response.responseText);
                throwMessage(response.type,response.message.error);
            });
        }

        $(".add-recipient-item").submit(function(event){
            event.preventDefault();
            var formData = $(this).serialize();

            formAjaxRequest(formData,"{{ setRoute('user.beneficiary.submit', $type) }}");
        })

        function makeInputReadonly(element) {
            var inputs = $(element).find("input");
            $.each(inputs,function(index,item){
                $(item).attr("readonly",true);
            });
        }

        function getTrxFields(value) {
            var value = value;
            if(value == null || value == undefined || value == "") {
                return false;
            }
            var data = {
                _token: laravelCsrf(),
                data:value,
            };
            $.post("{{ setRoute('user.beneficiary.create.get.input') }}",data,function() {
                // success
            }).done(function(response){
                $(".trx-inputs").remove();
                $(response).insertAfter($(".transaction-type"));
                $(".transaction-type").parent().find(".trx-inputs").slideDown(400);
                $("select[name=beneficiary_subtype]").select2();
                $("select[name=mobile_bank]").select2();
            }).fail(function(response) {
                $(".trx-inputs").remove();
                var response = JSON.parse(response.responseText);
                throwMessage(response.type,response.message.error);
            });
        }

        function getTrxSubFields(value) {
            var value = value;
            var method = $("select.trx-type-select").find(":selected").val();

            if(value == null || value == undefined || value == "") {
                return false;
            }
            var data = {
                _token: laravelCsrf(),
                sub_type: value,
                method: method,
            };

            $.post("{{ setRoute('user.beneficiary.create.get.sub.input') }}",data,function() {
                // success
            }).done(function(response){
                $(".beneficiary-account-type-wrapper + .trx-inputs").remove();
                $(response).insertAfter($(".beneficiary-account-type-wrapper"));
                $(".beneficiary-account-type-wrapper").find(".trx-inputs").slideUp();
                $("select[name=bank]").select2();
                $("select[name=branch]").select2();
            }).fail(function(response) {
                $(".beneficiary-account-type-wrapper + .trx-inputs").remove();
                var response = JSON.parse(response.responseText);
                throwMessage(response.type,response.message.error);
            });
        }

        function getBankBranch(value){
            if(value == null || value == undefined || value == ""){
                return false;
            }
            var data ={
                _token: laravelCsrf(),
                value: value
            }
            $.post("{{ setRoute('user.beneficiary.get.bank.branch') }}", data, function () {

            }).done(function(response){
                $("select[name='branch']").html(response);
            }).fail(function(response){
                throwMessage(response.type,response.message.error);
            })
        }

        var delayTime;
        $(document).on("keyup","input[name=acc_number]",function(){
            clearTimeout(delayTime);
            delayTime = setTimeout(validateAccountWithNumber, 500,$("input[name=acc_number]"),"SOF");
        });

    </script>
@endpush
