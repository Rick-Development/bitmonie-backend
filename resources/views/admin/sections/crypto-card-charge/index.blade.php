@extends('admin.layouts.master')

@section('page-title')
@include('admin.components.page-title', [
    'title' => __($page_title)
])
@endsection

@section('breadcrumb')
@include('admin.components.breadcrumb', [
    'breadcrumbs' => [
        [
            'name' => __("Dashboard"),
            'url' => setRoute("admin.dashboard")
        ]
    ],
    'active' => __("Crypto Card Charges")
])
@endsection

@section('content')
<div class="table-area">
    <div class="table-wrapper mb-20">
        <div class="table-header">
            <h5 class="title">
                {{ __('Crypto Card Charges Overview') }}
            </h5>
            <div>
                <a href="{{ route('admin.crypto-card-charge.edit') }}" class="btn--base">
                    <i class="las la-edit me-1"></i>{{ __('Edit Charges') }}
                </a>
            </div>
        </div>
    </div>

    <div class="row mb-30-none">
        {{-- Virtual Card Charges Table --}}
        <div class="col-xl-6 col-lg-6 mb-30">
            <div class="card h-100">
                <div class="card-header bg--primary d-flex justify-content-between align-items-center">
                    <h5 class="card-title text-white">
                        <i class="las la-credit-card me-2"></i>{{ __('Virtual Card Charges') }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Charge Type') }}</th>
                                    <th>{{ __('Amount (USDT)') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ __('Virtual Card Issuance Fee') }}</td>
                                    <td><strong>{{ number_format((float) ($setup?->virtual_issuance_fee ?? 0), 2) }} USDT</strong></td>
                                    <td>
                                        <a href="{{ route('admin.crypto-card-charge.edit') }}" class="btn--base">
                                            {{ __('Edit') }}
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{ __('Virtual Card Funding Fee') }}</td>
                                    <td><strong>{{ number_format((float) ($setup?->virtual_funding_fee ?? 0), 2) }} USDT</strong></td>
                                    <td>
                                        <a href="{{ route('admin.crypto-card-charge.edit') }}" class="btn--base">
                                            {{ __('Edit') }}
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{ __('Virtual Card Monthly Maintenance Fee') }}</td>
                                    <td><strong>{{ number_format((float) ($setup?->virtual_maintenance_fee ?? 0), 2) }} USDT</strong></td>
                                    <td>
                                        <a href="{{ route('admin.crypto-card-charge.edit') }}" class="btn--base">
                                            {{ __('Edit') }}
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Physical Card Charges Table --}}
        <div class="col-xl-6 col-lg-6 mb-30">
            <div class="card h-100">
                <div class="card-header bg--info d-flex justify-content-between align-items-center">
                    <h5 class="card-title text-white">
                        <i class="las la-id-card me-2"></i>{{ __('Physical Card Charges') }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Charge Type') }}</th>
                                    <th>{{ __('Amount (USDT)') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ __('Physical Card Order / Issuance Fee') }}</td>
                                    <td><strong>{{ number_format((float) ($setup?->physical_order_fee ?? 0), 2) }} USDT</strong></td>
                                    <td>
                                        <a href="{{ route('admin.crypto-card-charge.edit') }}" class="btn--base">
                                            {{ __('Edit') }}
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{ __('Physical Card Funding Fee') }}</td>
                                    <td><strong>{{ number_format((float) ($setup?->physical_funding_fee ?? 0), 2) }} USDT</strong></td>
                                    <td>
                                        <a href="{{ route('admin.crypto-card-charge.edit') }}" class="btn--base">
                                            {{ __('Edit') }}
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{ __('Physical Card Monthly Maintenance Fee') }}</td>
                                    <td><strong>{{ number_format((float) ($setup?->physical_maintenance_fee ?? 0), 2) }} USDT</strong></td>
                                    <td>
                                        <a href="{{ route('admin.crypto-card-charge.edit') }}" class="btn--base">
                                            {{ __('Edit') }}
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
