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
        ]
    ], 'active' => __("USDT EasyEarn")])
@endsection

@section('content')
    <div class="dashboard-area">
        <div class="dashboard-item-area">
            <div class="row">
                <div class="col-xxxl-3 col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-15">
                    <div class="dashbord-item">
                        <div class="dashboard-content">
                            <div class="left">
                                <h6 class="title">{{ __("Total Locked") }}</h6>
                                <div class="user-info">
                                    <h2 class="user-count">{{ number_format($stats['total_locked'], 2) }} USDT</h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxxl-3 col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-15">
                    <div class="dashbord-item">
                        <div class="dashboard-content">
                            <div class="left">
                                <h6 class="title">{{ __("Total Interest Paid") }}</h6>
                                <div class="user-info">
                                    <h2 class="user-count">{{ number_format($stats['total_interest_paid'], 2) }} USDT</h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxxl-3 col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-15">
                    <div class="dashbord-item">
                        <div class="dashboard-content">
                            <div class="left">
                                <h6 class="title">{{ __("Active Investments") }}</h6>
                                <div class="user-info">
                                    <h2 class="user-count">{{ $stats['active_investments'] }}</h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxxl-3 col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-15">
                    <div class="dashbord-item">
                        <div class="dashboard-content">
                            <div class="left">
                                <h6 class="title">{{ __("Matured Today") }}</h6>
                                <div class="user-info">
                                    <h2 class="user-count">{{ $stats['matured_investments'] }}</h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-area mt-15">
            <div class="table-wrapper">
                <div class="table-header">
                    <h5 class="title">{{ __("Recent Investments") }}</h5>
                    <div class="table-btn-area">
                        <a href="{{ setRoute('admin.usdt.easyearn.investments') }}" class="btn--base">{{ __("View All") }}</a>
                        <a href="{{ setRoute('admin.usdt.easyearn.bulk.credit.page') }}" class="btn--base bg--success">{{ __("Bulk Credit Interest") }}</a>
                        <a href="{{ setRoute('admin.usdt.easyearn.settings') }}" class="btn--base bg--info">{{ __("Settings") }}</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>{{ __("User") }}</th>
                                <th>{{ __("Amount") }}</th>
                                <th>{{ __("Rate") }}</th>
                                <th>{{ __("Start Date") }}</th>
                                <th>{{ __("End Date") }}</th>
                                <th>{{ __("Status") }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recent_investments as $item)
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <span>{{ $item->user->username }}</span><br>
                                            <small>{{ $item->user->email }}</small>
                                        </div>
                                    </td>
                                    <td>{{ number_format($item->amount, 2) }} USDT</td>
                                    <td>{{ $item->interest_rate }}%</td>
                                    <td>{{ $item->start_date->format('Y-m-d') }}</td>
                                    <td>{{ $item->end_date->format('Y-m-d') }}</td>
                                    <td>
                                        <span class="badge {{ $item->status === 'active' ? 'badge--success' : ($item->status === 'completed' ? 'badge--info' : 'badge--danger') }}">
                                            {{ ucfirst($item->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ setRoute('admin.usdt.easyearn.details', $item->id) }}" class="btn btn--base btn--icon"><i class="las la-expand"></i></a>
                                    </td>
                                </tr>
                            @empty
                                @include('admin.components.alerts.empty',['colspan' => 7])
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
@endpush
