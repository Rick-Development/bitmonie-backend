@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        ['name' => __("Dashboard"), 'url' => setRoute("admin.dashboard")],
        ['name' => __("Shared Referral Commissions"), 'url' => route('admin.referral.commissions.index')]
    ], 'active' => __("Details")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header"><h5 class="title">#{{ $commission->id }} {{ Str::headline($commission->status) }}</h5></div>
            <div class="row">
                <div class="col-lg-4 mb-3"><label>{{ __('Referrer') }}</label><input class="form--control" value="{{ optional($commission->referrer)->email }}" disabled></div>
                <div class="col-lg-4 mb-3"><label>{{ __('Referred') }}</label><input class="form--control" value="{{ optional($commission->referred)->email }}" disabled></div>
                <div class="col-lg-4 mb-3"><label>{{ __('Transaction') }}</label><input class="form--control" value="{{ $commission->transaction_type }} / {{ $commission->transaction_id }}" disabled></div>
                <div class="col-lg-4 mb-3"><label>{{ __('Platform Fee') }}</label><input class="form--control" value="{{ $commission->platform_fee_amount }} {{ $commission->currency_code }}" disabled></div>
                <div class="col-lg-4 mb-3"><label>{{ __('Commission') }}</label><input class="form--control" value="{{ $commission->commission_amount }} {{ $commission->currency_code }}" disabled></div>
                <div class="col-lg-4 mb-3"><label>{{ __('Rate') }}</label><input class="form--control" value="{{ $commission->commission_rate_percent }}%" disabled></div>
            </div>
            <form method="POST" action="{{ route('admin.referral.commissions.update', $commission->id) }}">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-lg-3 mb-3">
                        <select class="form--control" name="action">
                            <option value="approve">{{ __('Approve/Credit') }}</option>
                            <option value="flag">{{ __('Flag') }}</option>
                        </select>
                    </div>
                    <div class="col-lg-2 mb-3"><button class="btn--base">{{ __('Submit') }}</button></div>
                </div>
            </form>
        </div>
    </div>
@endsection
