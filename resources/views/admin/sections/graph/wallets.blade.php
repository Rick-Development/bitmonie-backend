@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __("Graph Wallets")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("Graph USD Wallets") }}</h5>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __("User") }}</th>
                            <th>{{ __("Wallet ID") }}</th>
                            <th>{{ __("Account Number") }}</th>
                            <th>{{ __("Balance") }}</th>
                            <th>{{ __("Status") }}</th>
                            <th>{{ __("Action") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($wallets as $item)
                            <tr>
                                <td>
                                    <a href="{{ setRoute('admin.users.details', $item->user->username) }}" class="text--info">{{ $item->user->fullname }}</a>
                                </td>
                                <td>{{ $item->wallet_id }}</td>
                                <td>{{ $item->account_number ?? 'N/A' }}</td>
                                <td>{{ number_format($item->balance, 2) }} {{ $item->currency }}</td>
                                <td>
                                    <span class="badge badge--{{ $item->status == 'active' ? 'success' : 'danger' }}">{{ $item->status }}</span>
                                </td>
                                <td>
                                    {{-- Actions if any --}}
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 6])
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ get_paginate($wallets) }}
        </div>
    </div>
@endsection
