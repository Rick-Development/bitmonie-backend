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
    ], 'active' => __("Graph Transactions")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("Graph Transactions") }}</h5>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __("Trx ID") }}</th>
                            <th>{{ __("User") }}</th>
                            <th>{{ __("Type") }}</th>
                            <th>{{ __("Amount") }}</th>
                            <th>{{ __("Status") }}</th>
                            <th>{{ __("Date") }}</th>
                            <th>{{ __("Action") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $item)
                            <tr>
                                <td>{{ $item->transaction_id }}</td>
                                <td>
                                    <a href="{{ setRoute('admin.users.details', $item->user->username) }}" class="text--info">{{ $item->user->fullname }}</a>
                                </td>
                                <td><span class="text--uppercase">{{ $item->type }}</span></td>
                                <td>{{ number_format($item->amount, 2) }} {{ $item->currency }}</td>
                                <td>
                                    <span class="badge badge--{{ $item->status == 'successful' || $item->status == 'completed' ? 'success' : ($item->status == 'pending' ? 'warning' : 'danger') }}">{{ $item->status }}</span>
                                </td>
                                <td>{{ $item->created_at->format('d-m-Y H:i A') }}</td>
                                <td>
                                    <a href="{{ setRoute('admin.graph.transactions.details', $item->id) }}" class="btn btn--base btn--primary"><i class="las la-info-circle"></i></a>
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 7])
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ get_paginate($logs) }}
        </div>
    </div>
@endsection
