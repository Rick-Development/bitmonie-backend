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
    ], 'active' => __($page_title)])
@endsection

@section('content')
<div class="row mb-30-none">
    <div class="col-lg-12 mb-30">
        <div class="transaction-area">
            <h4 class="title mb-0"><i class="fas fa-university text--base me-2"></i>{{ __("Company Details") }}</h4>
            <div class="content pt-0">
                <div class="list-wrapper">
                    <ul class="list">
                        <li>{{ __("Company Name") }}<span>{{ $sender_transaction->user->company_name ?? '' }}</span></li>
                        <li>{{ __("Email") }}<span class="text-lowercase">{{ $sender_transaction->user->email ?? '' }}</span></li>
                        <li>{{ __("Username") }}<span class="text-lowercase">{{ $sender_transaction->user->username ?? '' }}</span></li> 
                        <li>{{ __("Available Balance") }}<span>{{ get_amount($sender_transaction->user_wallet->balance,get_default_currency_code()) }}</span></li> 
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-12 mb-30">
        <div class="transaction-area">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="title"><i class="fas fa-user text--base me-2"></i>{{ __("Employee Details") }}</h4>
            </div>            
            <table class="custom-table transaction-search-table">
                <thead>
                    <tr>
                        <th>{{ __('Full Name') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Username') }}</th>
                        <th>{{ __('Salary Amount') }}</th>
                        <th>{{ __("Status") }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receiver_transaction ?? [] as $item)
                        <tr data-item="{{ $item }}">
                            <td><span>{{ $item->user->fullname ?? '' }}</span></td>
                            <td>{{ $item->user->email ?? '' }}</td>
                            <td>{{ $item->user->username ?? '' }}</td>
                            <td>{{ get_amount($item->request_amount,$item->request_currency) }}</td>
                            <td>{{ $item->string_status->value }}</td>
                        </tr>
                    @empty
                        @include('admin.components.alerts.empty',['colspan' => 11])
                    @endforelse
                </tbody>
            </table>
            <h4 class="text-end">{{ __("Total Salary") }} : {{ get_amount($total_amount,$sender_transaction->request_currency) }}</h4>
        </div>
    </div>
</div>
@endsection