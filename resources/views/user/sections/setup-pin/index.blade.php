@extends('user.layouts.master')

@push('css')

@endpush

@section('breadcrumb')
    @include('user.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("user.dashboard"),
        ]
    ], 'active' => __($page_title)])

@endsection

@section('content')
    <div class="row justify-content-center mb-20-none">
        <div class="col-xl-8 col-lg-8 mb-20">
            <div class="custom-card mt-10">
                <div class="dashboard-header-wrapper">
                    @if (auth()->user()->pin_status == false)   
                        <h4 class="title">{{ __("Setup Pin") }}</h4>
                        
                    @else
                        <h4>{{ __("Congratulations! Your PIN has been successfully set up.Thank you for securing your account.") }}</h4>
                    @endif
                </div>
                @if (auth()->user()->pin_status == false)   
                    <div class="card-body">
                        <form class="card-form" action="{{ setRoute('user.setup.pin.store') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-lg-12 form-group">
                                    <label>{{ __("Pin") }}<span>*</span></label>
                                    <input type="text" class="form-control form--control number-input" placeholder="{{ __('Enter Pin') }}..." name="pin_code" required>
                                </div>
                            </div>
                            <div class="col-xl-12 col-lg-12">
                                <button type="submit" class="btn--base btn-loading w-100">{{ __("Save") }}</button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="card-body">
                        <form class="card-form" action="{{ setRoute('user.setup.pin.update') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-lg-12 form-group">
                                    <label>{{ __("Old Pin") }}<span>*</span></label>
                                    <input type="text" class="form-control form--control number-input" placeholder="{{ __('Enter Old Pin') }}..." name="old_pin" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-12 form-group">
                                    <label>{{ __("New Pin") }}<span>*</span></label>
                                    <input type="text" class="form-control form--control number-input" placeholder="{{ __('Enter New Pin') }}..." name="new_pin" required>
                                </div>
                            </div>
                            <div class="col-xl-12 col-lg-12">
                                <button type="submit" class="btn--base btn-loading w-100">{{ __("Update") }}</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection