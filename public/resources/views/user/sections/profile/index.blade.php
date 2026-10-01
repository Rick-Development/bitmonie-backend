@extends('user.layouts.master')

@push('css')

@endpush

@section('breadcrumb')
    @include('user.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("user.dashboard"),
        ]
    ], 'active' => __("Profile")])

@endsection

@section('content')
    <div class="row mb-20-none">
        <div class="col-xl-6 col-lg-6 mb-20">
            <div class="custom-card mt-10">
                <div class="dashboard-header-wrapper">
                    <div class="header-title">
                        <h4 class="title">{{ __('Profile Settings') }}</h4>
                    </div>
                    <div class="account-delate">
                        <button class="delete-btn" data-bs-toggle="modal" data-bs-target="#deleteModal">{{ __('Delete Account') }}</button>
                    </div>
                </div>
                @php
                    $user = auth()->user();
                @endphp
                <div class="card-body profile-body-wrapper">
                    <form class="card-form" method="POST" action="{{ setRoute('user.profile.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method("PUT")
                        <div class="profile-settings-wrapper">
                            <div class="preview-thumb profile-wallpaper">
                                <div class="avatar-preview">
                                    <div class="profilePicPreview bg-overlay-base bg_img" data-background="{{ asset('public/frontend/images/element/profile-thumb.webp') }}"></div>
                                </div>
                            </div>
                            <div class="profile-thumb-content">
                                <div class="preview-thumb profile-thumb">
                                    <div class="avatar-preview">
                                        <div class="profilePicPreview bg_img" data-background="{{ $user->userImage }}"></div>
                                    </div>
                                    <div class="avatar-edit">
                                        <input type='file' class="profilePicUpload" name="image" id="profilePicUpload2" accept=".png, .jpg, .jpeg, .webp, .svg" />
                                        <label for="profilePicUpload2"><i class="las la-upload"></i></label>
                                    </div>
                                </div>
                                <div class="profile-content">
                                    <h6 class="username">{{ $user->username }}</h6>
                                    <ul class="user-info-list md-2">
                                        <li><i class="fas fa-envelope"></i>{{ $user->email }}</li>
                                    </ul>
                                    <div class="profile-account-number">
                                        <span><i class="fas fa-university"></i> <span>{{ auth()->user()->account_no }}</span> <input type="hidden" id="account-id" value="{{ auth()->user()->account_no }}"><span class="copytext" id="copy-account-id"><i class="fas fa-copy copy-btn"></i></span> </span>
                                     </div>
                                </div>
                            </div>
                        </div>
                        <div class="profile-form-area">
                            <div class="row">
                                <div class="col-xl-6 col-lg-6 form-group">
                                    @include('admin.components.form.input',[
                                        'label'               => __('First Name')."<span>*</span>",
                                        'name'                => "firstname",
                                        'placeholder'         => __('Enter First Name')."...",
                                        'block_error_message' => true,
                                        'value'               => old('firstname',$user->firstname)
                                    ])
                                </div>
                                <div class="col-xl-6 col-lg-6 form-group">
                                    @include('admin.components.form.input',[
                                        'label'               => __('Last Name')."<span>*</span>",
                                        'name'                => "lastname",
                                        'placeholder'         => __('Enter Last Name')."...",
                                        'block_error_message' => true,
                                        'value'               => old('lastname',$user->lastname)
                                    ])
                                </div>
                                <div class="col-xl-6 col-lg-6 form-group">
                                    <label>{{ __("Country") }}<span>*</span></label>
                                    <select name="country" class="form--control select2-auto-tokenize country-select" data-placeholder="{{ __('Select Country') }}" data-old="{{ old('country',$user->address->country ?? "") }}"></select>
                                </div>
                                <div class="col-xl-6 col-lg-6 form-group">
                                    <label>{{ __("Phone") }}</label>
                                    <div class="input-group">
                                        <div class="input-group-text phone-code">+{{ $user->mobile_code }}</div>
                                        <input class="phone-code" type="hidden" name="phone_code" value="{{ $user->mobile_code }}" />
                                        <input type="text" class="form--control" placeholder="{{ __('Enter Phone') }}" name="phone" value="{{ old('phone',$user->mobile) }}">
                                    </div>
                                </div>
                                <div class="col-lg-6 form-group">
                                    <label for="">{{ __('Gender') }}</label>
                                    <select name="gender" class="form-control select2-auto-tokenize" value="{{ old('gender', $user->gender) }}" required>
                                        <option value="">{{ __('Select Gender') }}</option>
                                        <option {{ $user->gender == 'Male' ? 'selected': '' }} value="Male">{{ __('Male') }}</option>
                                        <option {{ $user->gender == 'Female' ? 'selected': '' }} value="Female">{{ __('Female') }}</option>
                                        <option {{ $user->gender == 'Other' ? 'selected': '' }} value="Other">{{ __('Other') }}</option>
                                    </select>
                               </div>
                               <div class="col-lg-6 col-md-6 form-group">
                                    <label for="">{{ __('Date Of Birth') }}</label>
                                    <input type="date" class="form-control form--control" placeholder="{{ __('Birthday') }}" name="birthdate" value="{{ $user->birthdate ? date('Y-m-d', strtotime($user->birthdate)) : '' }}" required>
                                </div>
                                <div class="col-xl-6 col-lg-6 form-group">
                                    @include('admin.components.form.input',[
                                        'label'               => __('Address'),
                                        'name'                => "address",
                                        'placeholder'         => __('Enter Address')."...",
                                        'block_error_message' => true,
                                        'value'               => old('address',$user->address->address ?? "")
                                    ])
                                </div>
                                <div class="col-xl-6 col-lg-6 form-group">
                                    @include('admin.components.form.input',[
                                        'label'               => __('City'),
                                        'name'                => "city",
                                        'placeholder'         => __('Enter City')."...",
                                        'block_error_message' => true,
                                        'value'               => old('city',$user->address->address ?? "")
                                    ])
                                </div>
                                <div class="col-xl-6 col-lg-6 form-group">
                                    @include('admin.components.form.input',[
                                        'label'               => __('Zip Code')."",
                                        'name'                => "zip_code",
                                        'placeholder'         => __('Enter Zip')."...",
                                        'block_error_message' => true,
                                        'value'               => old('zip_code',$user->address->zip ?? "")
                                    ])
                                </div>
                                <div class="col-xl-6 col-lg-6 form-group">
                                    @include('admin.components.form.input',[
                                        'label'               => __('State'),
                                        'name'                => "state",
                                        'placeholder'         => __('Enter State')."...",
                                        'block_error_message' => true,
                                        'value'               => old('state',$user->address->address ?? "")
                                    ])
                                </div>
                            </div>
                            <div class="col-xl-12 col-lg-12">
                                <button type="submit" class="btn--base btn-loading w-100">{{ __("Update") }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-lg-6 mb-20">
            <div class="custom-card mt-10">
                <div class="dashboard-header-wrapper">
                    <h4 class="title">{{ __("Change Password") }}</h4>
                </div>
                <div class="card-body">
                    <form class="card-form" action="{{ setRoute('user.profile.password.update') }}" method="POST">
                        @csrf
                        @method("PUT")
                        <div class="row">
                            <div class="col-lg-12 form-group show_hide_password two">
                                <label>{{ __("Current Password") }}<span>*</span></label>
                                <input type="password" class="form-control form--control" placeholder="{{ __('Current Password') }}..." name="current_password" required>
                                <a href="#0" class="show-pass three"><i class="fa fa-eye-slash" aria-hidden="true"></i></a>
                            </div>
                            <div class="col-lg-12 form-group show_hide_password two">
                                <label>{{ __("New Password") }}<span>*</span></label>
                                <input type="password" class="form-control form--control" placeholder="{{ __('New Password') }}..." name="password" required>
                                <a href="#0" class="show-pass three"><i class="fa fa-eye-slash" aria-hidden="true"></i></a>
                            </div>
                            <div class="col-lg-12 form-group show_hide_password two">
                                <label>{{ __("Confirm Password") }}<span>*</span></label>
                                <input type="password" class="form-control form--control" placeholder="{{ __('Confirm Password') }}..." name="password_confirmation" required>
                                <a href="#0" class="show-pass three"><i class="fa fa-eye-slash" aria-hidden="true"></i></a>
                            </div>
                        </div>
                        <div class="col-xl-12 col-lg-12">
                            <button type="submit" class="btn--base btn-loading w-100">{{ __("Change") }}</button>
                        </div>
                    </form>
                </div>
            </div>
            @include('user.components.profile.kyc',compact("kyc_data"))
        </div>
    </div>
@endsection

@push('script')
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


        $(".delete-btn").click(function(){
            var actionRoute =  "{{ setRoute('user.delete.account') }}";
            var target      = 1;
            var btnText = "Delete Account";
            var projectName = "{{ @$basic_settings->site_name }}";
            var name = $(this).data('name');
            var message     = `Are you sure to delete <strong>your account</strong>?<br>If you do not think you will use “<strong>${projectName}</strong>”  again and like your account deleted, we can take card of this for you. Keep in mind you will not be able to reactivate your account or retrieve any of the content or information you have added. If you would still like your account deleted, click “Delete Account”.?`;
            openAlertModal(actionRoute,target,message,btnText,"DELETE");
        });

        $('#copy-account-id').on('click',function(){
            var copyText = document.getElementById("account-id");
            var tempInput = document.createElement("input");
            tempInput.value = copyText.value;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand("copy");
            document.body.removeChild(tempInput);
            console.log(copyText.value);
            throwMessage('success', ["Copied Account Number: " + copyText.value]);
        });
    </script>
@endpush
