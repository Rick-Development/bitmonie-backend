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
    ], 'active' => __("Investment Details")])
@endsection

@section('content')
    <div class="row mb-30-none">
        <div class="col-xl-4 col-lg-4 mb-30">
            <div class="dashboard-area">
                <div class="dashboard-item-area">
                    <div class="dashbord-item">
                        <div class="dashboard-content">
                            <div class="left">
                                <h6 class="title">{{ __("Investment Amount") }}</h6>
                                <div class="user-info">
                                    <h2 class="user-count">{{ number_format($investment->amount, 2) }} USDT</h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="dashbord-item">
                        <div class="dashboard-content">
                            <div class="left">
                                <h6 class="title">{{ __("Total Interest Earned") }}</h6>
                                <div class="user-info">
                                    <h2 class="user-count">{{ number_format($investment->total_interest_earned, 2) }} USDT</h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-20">
                <div class="card-header">
                    <h5 class="title">{{ __("User Information") }}</h5>
                </div>
                <div class="card-body">
                    <ul class="user-profile-list">
                        <li><span>{{ __("UserName") }}:</span> {{ $investment->user->username }}</li>
                        <li><span>{{ __("Email") }}:</span> {{ $investment->user->email }}</li>
                        <li><span>{{ __("Status") }}:</span> {{ $investment->user->status ? 'Active' : 'Banned' }}</li>
                    </ul>
                </div>
            </div>

            <div class="card mt-20">
                <div class="card-header">
                    <h5 class="title">{{ __("Actions") }}</h5>
                </div>
                <div class="card-body">
                    <div class="btn-area">
                        @if ($investment->status === 'active')
                            <form action="{{ setRoute('admin.usdt.easyearn.credit.interest', $investment->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn--base w-100 mb-2">{{ __("Credit Monthly Interest") }}</button>
                            </form>
                            <form action="{{ setRoute('admin.usdt.easyearn.cancel', $investment->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to cancel this investment? Principal will be returned to user.')">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="btn--base bg--danger w-100">{{ __("Cancel Investment") }}</button>
                            </form>
                        @else
                            <button class="btn--base w-100 disabled" disabled>{{ __("No Actions Available") }}</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-lg-8 mb-30">
            <div class="table-area">
                <div class="table-wrapper">
                    <div class="table-header">
                        <h5 class="title">{{ __("Interest Credit History") }}</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>{{ __("Date") }}</th>
                                    <th>{{ __("Amount") }}</th>
                                    <th>{{ __("Type") }}</th>
                                    <th>{{ __("By") }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($investment->interestCredits as $item)
                                    <tr>
                                        <td>{{ $item->created_at->format('Y-m-d H:i') }}</td>
                                        <td>{{ number_format($item->amount, 8) }} USDT</td>
                                        <td>
                                            <span class="badge {{ $item->type === 'monthly' ? 'badge--info' : 'badge--success' }}">
                                                {{ ucfirst($item->type) }}
                                            </span>
                                        </td>
                                        <td>{{ $item->admin ? $item->admin->username : 'System' }}</td>
                                    </tr>
                                @empty
                                    @include('admin.components.alerts.empty',['colspan' => 4])
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mt-20">
                <div class="card-header">
                    <h5 class="title">{{ __("Investment Details") }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="user-profile-list">
                                <li><span>{{ __("Start Date") }}:</span> {{ $investment->start_date->format('Y-m-d') }}</li>
                                <li><span>{{ __("End Date") }}:</span> {{ $investment->end_date->format('Y-m-d') }}</li>
                                <li><span>{{ __("Interest Rate") }}:</span> {{ $investment->interest_rate }}%</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="user-profile-list">
                                <li><span>{{ __("Duration") }}:</span> {{ $investment->duration_months }} Months</li>
                                <li><span>{{ __("Auto Compound") }}:</span> {{ $investment->auto_compound ? 'Yes' : 'No' }}</li>
                                <li><span>{{ __("Status") }}:</span> <span class="badge {{ $investment->status === 'active' ? 'badge--success' : ($item->status === 'completed' ? 'badge--info' : 'badge--danger') }}">{{ ucfirst($investment->status) }}</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
@endpush
