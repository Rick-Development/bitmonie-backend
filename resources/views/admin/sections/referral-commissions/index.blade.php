@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        ['name' => __("Dashboard"), 'url' => setRoute("admin.dashboard")]
    ], 'active' => __("Shared Referral Commissions")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper mb-20">
            <div class="table-header">
                <h5 class="title">{{ __('Credited') }}: {{ $stats['credited'] }} | {{ __('Pending') }}: {{ $stats['pending'] }} | {{ __('Flagged') }}: {{ $stats['flagged'] }}</h5>
                <div>
                    <a href="{{ route('admin.referral.commissions.settings') }}" class="btn--base">{{ __('Settings') }}</a>
                    <a href="{{ route('admin.referral.commissions.export', request()->query()) }}" class="btn--base">{{ __('Export') }}</a>
                </div>
            </div>
        </div>
        <div class="table-wrapper">
            <form method="GET" class="mb-20">
                <div class="row">
                    <div class="col-lg-2 mb-2"><input class="form--control" name="referrer" value="{{ request('referrer') }}" placeholder="{{ __('Referrer') }}"></div>
                    <div class="col-lg-2 mb-2"><input class="form--control" name="referred" value="{{ request('referred') }}" placeholder="{{ __('Referred') }}"></div>
                    <div class="col-lg-2 mb-2"><input class="form--control" name="transaction_type" value="{{ request('transaction_type') }}" placeholder="{{ __('Type') }}"></div>
                    <div class="col-lg-2 mb-2">
                        <select class="form--control" name="status">
                            <option value="">{{ __('All Status') }}</option>
                            @foreach(['pending','credited','flagged'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::headline($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-1 mb-2"><input type="date" class="form--control" name="from" value="{{ request('from') }}"></div>
                    <div class="col-lg-1 mb-2"><input type="date" class="form--control" name="to" value="{{ request('to') }}"></div>
                    <div class="col-lg-1 mb-2"><button class="btn--base w-100">{{ __('Go') }}</button></div>
                </div>
            </form>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead><tr><th>{{ __('Referrer') }}</th><th>{{ __('Referred') }}</th><th>{{ __('Type') }}</th><th>{{ __('Fee') }}</th><th>{{ __('Commission') }}</th><th>{{ __('Status') }}</th><th>{{ __('Action') }}</th></tr></thead>
                    <tbody>
                        @forelse($commissions as $commission)
                            <tr>
                                <td>{{ optional($commission->referrer)->email }}</td>
                                <td>{{ optional($commission->referred)->email }}</td>
                                <td>{{ $commission->transaction_type }}</td>
                                <td>{{ $commission->platform_fee_amount }} {{ $commission->currency_code }}</td>
                                <td>{{ $commission->commission_amount }} {{ $commission->currency_code }}</td>
                                <td>{{ Str::headline($commission->status) }}</td>
                                <td><a class="btn--base" href="{{ route('admin.referral.commissions.show', $commission->id) }}">{{ __('View') }}</a></td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 7])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $commissions->links() }}
    </div>
@endsection
