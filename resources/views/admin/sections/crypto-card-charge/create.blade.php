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
            'name'  => __("Crypto Card Charges"),
            'url'   => setRoute("admin.crypto-card-charge.index"),
        ],
    ], 'active' => __("Create Charges")])
@endsection

@section('content')
<div class="custom-card">
    <div class="card-header">
        <h6 class="title">{{ __("Create Crypto Card Charges") }}</h6>
    </div>
    <div class="card-body">
        <form class="card-form" action="{{ setRoute('admin.crypto-card-charge.store') }}" method="POST">
            @csrf
            <div class="row">
                {{-- Virtual Card Charges Section --}}
                <div class="col-xl-6 col-lg-6 mb-20">
                    <div class="custom-inner-card">
                        <div class="card-inner-header">
                            <h5 class="title">{{ __("Virtual Card Charges") }}</h5>
                        </div>
                        <div class="card-inner-body">
                            <div class="row">
                                <div class="col-12 form-group">
                                    <label>{{ __("Virtual Card Issuance Fee") }}</label>
                                    <div class="input-group">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="form--control"
                                            name="virtual_card_issuance_fee"
                                            placeholder="0.00"
                                            value="{{ old('virtual_card_issuance_fee', $setup?->virtual_issuance_fee ?? 0) }}"
                                        >
                                        <span class="input-group-text">{{ __("USDT") }}</span>
                                    </div>
                                    <small class="text--muted">
                                        {{ __("Fee charged upon issuing a virtual crypto card (leave blank or 0 for free).") }}
                                    </small>
                                </div>

                                <div class="col-12 form-group">
                                    <label>{{ __("Virtual Card Funding Fee") }}</label>
                                    <div class="input-group">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="form--control"
                                            name="virtual_card_funding_fee"
                                            placeholder="0.00"
                                            value="{{ old('virtual_card_funding_fee', $setup?->virtual_funding_fee ?? 0) }}"
                                        >
                                        <span class="input-group-text">{{ __("USDT") }}</span>
                                    </div>
                                    <small class="text--muted">
                                        {{ __("Fee charged when topping up or funding a virtual crypto card.") }}
                                    </small>
                                </div>

                                <div class="col-12 form-group">
                                    <label>{{ __("Virtual Card Monthly Maintenance Fee") }}</label>
                                    <div class="input-group">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="form--control"
                                            name="virtual_monthly_card_maintenance_fee"
                                            placeholder="0.00"
                                            value="{{ old('virtual_monthly_card_maintenance_fee', $setup?->virtual_maintenance_fee ?? 0) }}"
                                        >
                                        <span class="input-group-text">{{ __("USDT") }}</span>
                                    </div>
                                    <small class="text--muted">
                                        {{ __("Recurring monthly maintenance fee charged for active virtual cards.") }}
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Physical Card Charges Section --}}
                <div class="col-xl-6 col-lg-6 mb-20">
                    <div class="custom-inner-card">
                        <div class="card-inner-header">
                            <h5 class="title">{{ __("Physical Card Charges") }}</h5>
                        </div>
                        <div class="card-inner-body">
                            <div class="row">
                                <div class="col-12 form-group">
                                    <label>{{ __("Physical Card Order / Issuance Fee") }}</label>
                                    <div class="input-group">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="form--control"
                                            name="physical_card_fee"
                                            placeholder="0.00"
                                            value="{{ old('physical_card_fee', $setup?->physical_order_fee ?? 0) }}"
                                        >
                                        <span class="input-group-text">{{ __("USDT") }}</span>
                                    </div>
                                    <small class="text--muted">
                                        {{ __("Fee charged for ordering and issuing a physical crypto card.") }}
                                    </small>
                                </div>

                                <div class="col-12 form-group">
                                    <label>{{ __("Physical Card Funding Fee") }}</label>
                                    <div class="input-group">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="form--control"
                                            name="physical_card_funding_fee"
                                            placeholder="0.00"
                                            value="{{ old('physical_card_funding_fee', $setup?->physical_funding_fee ?? 0) }}"
                                        >
                                        <span class="input-group-text">{{ __("USDT") }}</span>
                                    </div>
                                    <small class="text--muted">
                                        {{ __("Fee charged when topping up or funding a physical crypto card.") }}
                                    </small>
                                </div>

                                <div class="col-12 form-group">
                                    <label>{{ __("Physical Card Monthly Maintenance Fee") }}</label>
                                    <div class="input-group">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="form--control"
                                            name="physical_monthly_card_maintenance_fee"
                                            placeholder="0.00"
                                            value="{{ old('physical_monthly_card_maintenance_fee', $setup?->physical_maintenance_fee ?? 0) }}"
                                        >
                                        <span class="input-group-text">{{ __("USDT") }}</span>
                                    </div>
                                    <small class="text--muted">
                                        {{ __("Recurring monthly maintenance fee charged for active physical cards.") }}
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-10-none">
                <div class="col-xl-12 col-lg-12 form-group">
                    @include('admin.components.button.form-btn',[
                        'class'         => "w-100 btn-loading",
                        'text'          => __("Save Charges"),
                        'permission'    => "admin.crypto-card-charge.store"
                    ])
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
