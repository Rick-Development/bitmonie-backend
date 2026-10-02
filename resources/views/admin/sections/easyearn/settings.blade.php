@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        ['name' => __("Dashboard"), 'url' => setRoute("admin.dashboard")],
        ['name' => __("EasyEarn"), 'url' => route('admin.easyearn.index')]
    ], 'active' => __("Settings")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <form method="POST" action="{{ route('admin.easyearn.settings.update') }}">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-lg-3 mb-3"><label>{{ __('Return Multiplier') }}</label><input class="form--control" name="return_multiplier" value="{{ old('return_multiplier', $settings->return_multiplier) }}"></div>
                    <div class="col-lg-3 mb-3"><label>{{ __('Term Days') }}</label><input class="form--control" type="number" name="term_days" value="{{ old('term_days', $settings->term_days) }}"></div>
                    <div class="col-lg-3 mb-3"><label>{{ __('Early Withdrawal Penalty %') }}</label><input class="form--control" name="early_withdrawal_penalty_percent" value="{{ old('early_withdrawal_penalty_percent', $settings->early_withdrawal_penalty_percent) }}"></div>
                    <div class="col-lg-3 mb-3">
                        <label>{{ __('Early Withdrawal') }}</label>
                        <select class="form--control" name="early_withdrawal_enabled">
                            <option value="0" @selected(!$settings->early_withdrawal_enabled)>{{ __('Locked') }}</option>
                            <option value="1" @selected($settings->early_withdrawal_enabled)>{{ __('Enabled') }}</option>
                        </select>
                    </div>
                    <div class="col-lg-12 mb-3"><label>{{ __('Terms') }}</label><textarea class="form--control" name="terms" rows="5">{{ old('terms', $settings->terms) }}</textarea></div>
                </div>
                <button class="btn--base">{{ __('Update Settings') }}</button>
            </form>
        </div>
    </div>
@endsection
