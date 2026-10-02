@extends('admin.layouts.master')

@push('css')
@endpush

@section('page-title')
    @include('admin.components.page-title', ['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb', [
        'breadcrumbs' => [
            [
                'name' => __('Dashboard'),
                'url' => setRoute('admin.dashboard'),
            ],
        ],
        'active' => __(@$page_title),
    ])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">

            <div class="table-header">
                <h5 class="title">{{ $page_title }}</h5>
                @if(count($transactions) > 0)
                <div class="table-btn-area">
                    <a href="{{ setRoute('admin.virtual.card.export.data') }}" class="btn--base"><i class="fas fa-download me-1"></i>{{ __("Export Data") }}</a>
                </div>
            @endif
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __("Transaction ID") }}</th>
                            <th>{{ __("User") }}</th>
                            <th>{{ __("Type") }}</th>
                            <th>{{ __("Amount") }}</th>
                            <th>{{ __("Charge") }}</th>
                            <th>{{ __("Status") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions ??[]  as $key => $item)
                            <tr>
                                <td>{{ $item->trx_id }}</td>
                                <td>
                                    <a href="{{ setRoute('admin.users.details',$item->user->username) }}"><span class="text-info">{{ $item->user->fullname }}</span></a>
                                </td>
                                <td>{{ __(@$item->remark) }}</td>
                                <td>{{ number_format($item->request_amount,2) }} {{ $item->request_currency }}</td>
                                <td>{{ get_amount($item->total_charge,$item->request_currency) }}</td>
                                <td>
                                    <span class="{{ $item->stringStatus->class }}">{{ __($item->stringStatus->value) }}</span>
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 9])
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ get_paginate($transactions) }}
        </div>
    </div>
@endsection

@push('script')
@endpush
