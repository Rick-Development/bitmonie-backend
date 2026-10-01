@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ],
        [
            'name'  => __("Merchant P2P"),
            'url'   => setRoute("admin.p2p.merchant.applications.index"),
        ]
    ], 'active' => __("Settings")])
@endsection

@section('content')
    <div class="row mb-30-none">
        <div class="col-xl-12 col-lg-12 mb-30">
            <div class="card">
                <div class="card-header bg--primary">
                    <h5 class="card-title text-white">{{ __("Merchant Configuration") }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ setRoute('admin.p2p.merchant.applications.settings.update') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-xl-6 col-lg-6 form-group">
                                <label>{{ __("Minimum USDT Required") }} <span class="text--danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" name="merchant_min_usdt" value="{{ $settings['merchant_min_usdt'] ?? 0 }}" required>
                                    <span class="input-group-text">USDT</span>
                                </div>
                                <small class="text--muted">{{ __("The minimum USDT balance a user must have in their Quidax wallet to apply for merchant status.") }}</small>
                            </div>
                            <div class="col-xl-6 col-lg-6 form-group">
                                <label>{{ __("Security Deposit") }} <span class="text--danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" name="merchant_security_deposit_usdt" value="{{ $settings['merchant_security_deposit_usdt'] ?? 200 }}" required>
                                    <span class="input-group-text">USDT</span>
                                </div>
                                <small class="text--muted">{{ __("The refundable USDT amount locked when a merchant application is submitted and released when the merchant is rejected or deactivated.") }}</small>
                            </div>
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group">
                            <button type="submit" class="btn btn--primary w-100">{{ __("Update Settings") }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
