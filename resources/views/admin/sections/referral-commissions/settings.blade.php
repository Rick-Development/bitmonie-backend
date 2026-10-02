@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        ['name' => __("Dashboard"), 'url' => setRoute("admin.dashboard")],
        ['name' => __("Shared Referral Commissions"), 'url' => route('admin.referral.commissions.index')]
    ], 'active' => __("Settings")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <form method="POST" action="{{ route('admin.referral.commissions.settings.update') }}">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-lg-4 mb-3"><label>{{ __('Commission Rate %') }}</label><input class="form--control" name="commission_rate_percent" value="{{ old('commission_rate_percent', $settings->commission_rate_percent) }}"></div>
                    <div class="col-lg-4 mb-3">
                        <label>{{ __('Auto Credit') }}</label>
                        <select class="form--control" name="auto_credit">
                            <option value="1" @selected($settings->auto_credit)>{{ __('Enabled') }}</option>
                            <option value="0" @selected(!$settings->auto_credit)>{{ __('Disabled / Pending') }}</option>
                        </select>
                    </div>
                </div>
                <button class="btn--base">{{ __('Update Settings') }}</button>
            </form>
        </div>
    </div>
@endsection
