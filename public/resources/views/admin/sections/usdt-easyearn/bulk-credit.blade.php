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
    ], 'active' => __("Bulk Credit")])
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-4 col-lg-4 mb-30">
            <div class="dashboard-area">
                <div class="dashboard-item-area">
                    <div class="dashbord-item">
                        <div class="dashboard-content">
                            <div class="left">
                                <h6 class="title">{{ __("Eligible Investments") }}</h6>
                                <div class="user-info">
                                    <h2 class="user-count">{{ $eligible->count() }}</h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="dashbord-item">
                        <div class="dashboard-content">
                            <div class="left">
                                <h6 class="title">{{ __("Total Required Payout") }}</h6>
                                <div class="user-info">
                                    <h2 class="user-count">{{ number_format($totalRequired, 2) }} USDT</h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-20">
                <div class="card-header">
                    <h5 class="title">{{ __("Process Bulk Credit") }}</h5>
                </div>
                <div class="card-body">
                    <p class="mb-20 text--warning">{{ __("This will credit monthly interest to all eligible investments listed on the right. Currently set to credit on day") }} {{ $settings->payout_day }} {{ __("of the month.") }}</p>
                    
                    @if ($eligible->count() > 0)
                        <form action="{{ setRoute('admin.usdt.easyearn.bulk.credit') }}" method="POST" onsubmit="return confirm('Are you sure you want to process bulk credit for {{ $eligible->count() }} investments?')">
                            @csrf
                            <button type="submit" class="btn--base w-100">{{ __("Execute Bulk Credit") }}</button>
                        </form>
                    @else
                        <button class="btn--base w-100 disabled" disabled>{{ __("No Eligible Investments") }}</button>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-lg-8 mb-30">
            <div class="table-area">
                <div class="table-wrapper">
                    <div class="table-header">
                        <h5 class="title">{{ __("Eligible Investments List") }}</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>{{ __("User") }}</th>
                                    <th>{{ __("Principal") }}</th>
                                    <th>{{ __("Monthly Interest") }}</th>
                                    <th>{{ __("Last Credit") }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($eligible as $item)
                                    <tr>
                                        <td>{{ $item->user->username }}</td>
                                        <td>{{ number_format($item->amount, 2) }} USDT</td>
                                        <td>{{ number_format($item->calculateMonthlyInterest(), 8) }} USDT</td>
                                        <td>{{ $item->last_interest_credit_at ? $item->last_interest_credit_at->format('Y-m-d') : 'Never' }}</td>
                                    </tr>
                                @empty
                                    @include('admin.components.alerts.empty',['colspan' => 4])
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
@endpush
