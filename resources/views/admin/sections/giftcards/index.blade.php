@extends('admin.layouts.master')

@section('page-title')
    {{ __($page_title) }}
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __("Gift Card Transactions")])
@endsection

@section('content')
<div class="table-area">
    <div class="table-wrapper">
        <div class="table-header">
            <h5 class="title">{{ __("Transactions") }}</h5>
        </div>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>{{ __("TRX ID") }}</th>
                        <th>{{ __("User") }}</th>
                        <th>{{ __("Wallet") }}</th>
                        <th>{{ __("Product") }}</th>
                        <th>{{ __("Amount") }}</th>
                        <th>{{ __("Status") }}</th>
                        <th>{{ __("Time") }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $item)
                        <tr>
                            <td>{{ $item->custom_identifier }}</td>
                            <td>
                                <a href="{{ setRoute('admin.users.details',$item->user->username) }}">{{ $item->user->fullname }}</a>
                            </td>
                            <td>{{ $item->wallet->currency }}</td>
                            <td>{{ $item->product_name }} (x{{ $item->quantity }})</td>
                            <td>{{ get_amount($item->amount) }} {{ $item->currency }}</td>
                            <td>
                                <span class="badge {{ $item->status == 'SUCCESSFUL' ? 'badge--success' : ($item->status == 'PENDING' ? 'badge--warning' : 'badge--danger') }}">
                                    {{ $item->status }}
                                </span>
                            </td>
                            <td>{{ $item->created_at->format('d-m-Y H:i') }}</td>
                            <td>
                                <button class="btn btn--base detailBtn" 
                                    data-item="{{ json_encode($item) }}">
                                    <i class="las la-desktop"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        @include('admin.components.alerts.empty',['colspan' => 8])
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ get_paginate($transactions) }}
    </div>
</div>
@endsection

@push('script')
<script>
    $('.detailBtn').on('click', function() {
        var item = $(this).data('item');
        // Simple alert for now, can be replaced by a modal
        alert("Transaction Details:\n" + 
              "Reloadly ID: " + (item.reloadly_transaction_id || 'N/A') + "\n" +
              "PIN: " + (item.pin_code || 'N/A') + "\n" +
              "Card: " + (item.card_number || 'N/A')
        );
    });
</script>
@endpush
