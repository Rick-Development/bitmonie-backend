@extends('admin.layouts.master')

@push('css')
@endpush

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
            'name'  => __("USDT EasyEarn"),
            'url'   => setRoute("admin.usdt.easyearn.index"),
        ]
    ], 'active' => __("Settings")])
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-6 col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="title">{{ __("Global Settings") }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ setRoute('admin.usdt.easyearn.settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-xl-6 col-lg-6 form-group">
                                <label>{{ __("Monthly Interest Rate (%)") }}*</label>
                                <input type="number" step="0.01" name="current_monthly_rate" class="form--control" value="{{ $settings->current_monthly_rate }}" required>
                                <small class="text--info">{{ __("Standard monthly return rate") }}</small>
                            </div>
                            <div class="col-xl-6 col-lg-6 form-group">
                                <label>{{ __("Base Vault APY (%)") }}*</label>
                                <input type="number" step="0.01" name="base_apy" class="form--control" value="{{ $settings->base_apy ?? 15.00 }}" required>
                                <small class="text--info">{{ __("Vault base APY e.g. 15.00") }}</small>
                            </div>
                            <div class="col-xl-6 col-lg-6 form-group">
                                <label>{{ __("PowerBonus Multiplier (X)") }}*</label>
                                <input type="number" step="0.01" name="power_bonus_multiplier" class="form--control" value="{{ $settings->power_bonus_multiplier ?? 2.00 }}" required>
                                <small class="text--info">{{ __("PowerBonus multiplier if held to maturity (e.g. 2.00)") }}</small>
                            </div>
                            <div class="col-xl-6 col-lg-6 form-group">
                                <label>{{ __("Early Withdrawal Penalty Fee (%)") }}*</label>
                                <input type="number" step="0.01" name="early_withdrawal_penalty_percent" class="form--control" value="{{ $settings->early_withdrawal_penalty_percent ?? 5.00 }}" required min="0" max="100">
                                <small class="text--info">{{ __("Deducted from principal on early withdrawal") }}</small>
                            </div>
                            <div class="col-xl-6 col-lg-6 form-group">
                                <label>{{ __("Minimum Investment (USDT)") }}*</label>
                                <input type="number" step="0.01" name="min_investment" class="form--control" value="{{ $settings->min_investment }}" required>
                            </div>
                            <div class="col-xl-6 col-lg-6 form-group">
                                <label>{{ __("Maximum Investment (USDT)") }}</label>
                                <input type="number" step="0.01" name="max_investment" class="form--control" value="{{ $settings->max_investment }}">
                                <small class="text--info">{{ __("Leave empty for unlimited") }}</small>
                            </div>
                            <div class="col-xl-6 col-lg-6 form-group">
                                <label>{{ __("Payout Day (1-28)") }}*</label>
                                <input type="number" name="payout_day" class="form--control" value="{{ $settings->payout_day }}" required min="1" max="28">
                                <small class="text--info">{{ __("Day of month when interest is eligible for credit") }}</small>
                            </div>
                            <div class="col-xl-6 col-lg-6 form-group">
                                <label>{{ __("Early Withdrawal Allowed") }}*</label>
                                <select name="early_withdrawal_enabled" class="form--control">
                                    <option value="1" {{ ($settings->early_withdrawal_enabled ?? true) ? 'selected' : '' }}>{{ __("Enabled (Option B)") }}</option>
                                    <option value="0" {{ !($settings->early_withdrawal_enabled ?? true) ? 'selected' : '' }}>{{ __("Disabled (Strict Lock)") }}</option>
                                </select>
                            </div>
                            <div class="col-xl-6 col-lg-6 form-group">
                                <label>{{ __("Status") }}*</label>
                                <select name="is_active" class="form--control">
                                    <option value="1" {{ $settings->is_active ? 'selected' : '' }}>{{ __("Active") }}</option>
                                    <option value="0" {{ !$settings->is_active ? 'selected' : '' }}>{{ __("Inactive") }}</option>
                                </select>
                            </div>
                            <div class="col-xl-6 col-lg-6 form-group">
                                <label>{{ __("Auto-Credit Enabled") }}*</label>
                                <select name="auto_credit_enabled" class="form--control">
                                    <option value="1" {{ $settings->auto_credit_enabled ? 'selected' : '' }}>{{ __("Yes") }}</option>
                                    <option value="0" {{ !$settings->auto_credit_enabled ? 'selected' : '' }}>{{ __("No") }}</option>
                                </select>
                            </div>
                            <div class="col-xl-12 col-lg-12 form-group">
                                <button type="submit" class="btn--base w-100">{{ __("Update Settings") }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
@endpush
