@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        ['name' => __("Dashboard"), 'url' => setRoute("admin.dashboard")]
    ], 'active' => __("EasyEarn Plans")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper mb-20">
            <div class="table-header">
                <h5 class="title">{{ __('Active USDT Locked') }}: {{ $totalLocked }} USDT</h5>
                <div>
                    <a href="{{ route('admin.easyearn.settings') }}" class="btn--base">{{ __('Settings') }}</a>
                    <a href="{{ route('admin.easyearn.export.plans', request()->query()) }}" class="btn--base">{{ __('Export Plans') }}</a>
                    <a href="{{ route('admin.easyearn.export.transactions') }}" class="btn--base">{{ __('Export Transactions') }}</a>
                </div>
            </div>
        </div>

        <div class="table-wrapper">
            <form method="GET" class="mb-20">
                <div class="row">
                    <div class="col-lg-3 mb-2"><input class="form--control" name="user" value="{{ request('user') }}" placeholder="{{ __('User/email') }}"></div>
                    <div class="col-lg-2 mb-2">
                        <select class="form--control" name="status">
                            <option value="">{{ __('All Status') }}</option>
                            @foreach(['active','matured','withdrawn','cancelled'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::headline($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 mb-2"><input type="date" class="form--control" name="from" value="{{ request('from') }}"></div>
                    <div class="col-lg-2 mb-2"><input type="date" class="form--control" name="to" value="{{ request('to') }}"></div>
                    <div class="col-lg-1 mb-2"><button class="btn--base w-100">{{ __('Go') }}</button></div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __('User') }}</th>
                            <th>{{ __('Deposited') }}</th>
                            <th>{{ __('Expected Return') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Maturity') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plans as $plan)
                            <tr>
                                <td>
                                    <ul class="user-list">
                                        <li>{{ optional($plan->user)->fullname }}</li>
                                        <li><span>{{ optional($plan->user)->email }}</span></li>
                                    </ul>
                                </td>
                                <td>{{ $plan->usdt_amount_deposited }} USDT</td>
                                <td>{{ $plan->expected_return }} USDT</td>
                                <td>{{ Str::headline($plan->status) }}</td>
                                <td>{{ optional($plan->maturity_date)->format('d-m-Y h:i A') }}</td>
                                <td><a href="{{ route('admin.easyearn.show', $plan->id) }}" class="btn--base">{{ __('View') }}</a></td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 6])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $plans->links() }}
    </div>
@endsection
