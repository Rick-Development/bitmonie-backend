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
    ], 'active' => __("Investments")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __($page_title) }}</h5>
                <div class="table-btn-area">
                    <form action="{{ setRoute('admin.usdt.easyearn.investments') }}" method="GET">
                        <div class="input-group">
                            <input type="text" name="search" class="form--control" placeholder="Email or Username" value="{{ request()->search }}">
                            <select name="status" class="form--control">
                                <option value="">All Status</option>
                                <option value="active" {{ request()->status == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="completed" {{ request()->status == 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="cancelled" {{ request()->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                            <button type="submit" class="btn--base"><i class="las la-search"></i></button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __("User") }}</th>
                            <th>{{ __("Amount") }}</th>
                            <th>{{ __("Rate") }}</th>
                            <th>{{ __("Auto Compound") }}</th>
                            <th>{{ __("End Date") }}</th>
                            <th>{{ __("Status") }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($investments as $item)
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <span>{{ $item->user->username }}</span><br>
                                        <small>{{ $item->user->email }}</small>
                                    </div>
                                </td>
                                <td>{{ number_format($item->amount, 2) }} USDT</td>
                                <td>{{ $item->interest_rate }}%</td>
                                <td>{{ $item->auto_compound ? 'Yes' : 'No' }}</td>
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
            {{ get_paginate($investments) }}
        </div>
    </div>
@endsection

@push('script')
@endpush
