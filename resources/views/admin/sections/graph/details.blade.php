@extends('admin.layouts.master')

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
            'name'  => __("Transactions"),
            'url'   => setRoute("admin.graph.transactions"),
        ]
    ], 'active' => __("Details")])
@endsection

@section('content')
    <div class="custom-card">
        <div class="card-header">
            <h6 class="title">{{ __("Transaction Details") }}</h6>
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-xl-12">
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            {{ __("Transaction ID") }}
                            <span>{{ $transaction->transaction_id }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            {{ __("User") }}
                            <span>{{ $transaction->user->fullname }} ({{ $transaction->user->email }})</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            {{ __("Type") }}
                            <span class="text--uppercase font-weight-bold">{{ $transaction->type }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            {{ __("Amount") }}
                            <span>{{ number_format($transaction->amount, 2) }} {{ $transaction->currency }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            {{ __("Status") }}
                            <span class="badge badge--{{ $transaction->status == 'successful' || $transaction->status == 'completed' ? 'success' : ($transaction->status == 'pending' ? 'warning' : 'danger') }}">{{ $transaction->status }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            {{ __("Reference") }}
                            <span>{{ $transaction->reference }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            {{ __("Description") }}
                            <span>{{ $transaction->description }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            {{ __("Date") }}
                            <span>{{ $transaction->created_at->format('d-m-Y H:i A') }}</span>
                        </li>
                    </ul>

                    @if(!empty($transaction->metadata))
                    <div class="mt-4">
                        <h6>{{ __("Metadata (Raw)") }}</h6>
                        <pre class="bg--gray p-3 rounded mt-2">{{ json_encode($transaction->metadata, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
