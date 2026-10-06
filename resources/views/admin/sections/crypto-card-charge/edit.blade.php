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
            'url' => setRoute("admin.dashboard"),
        ],
        [
            'name' => __("Crypto Card Charges"),
            'url' => setRoute("admin.crypto-card-charge.index"),
        ],
    ],
    'active' => __("Edit Charges")
])
@endsection

@section('content')
<form action="{{ setRoute('admin.crypto-card-charge.update') }}" method="POST">
    @csrf
    @method('PUT')

    <div class="row mb-30-none">
        {{-- Virtual Card Charges Section --}}
        <div class="col-xl-6 col-lg-6 mb-30">
            <div class="card h-100">
                <div class="card-header bg--primary">
                    <h5 class="card-title text-white">
                        <i class="las la-credit-card me-2"></i>{{ __("Virtual Card Charges") }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 form-group">
                            <label class="form-label font-weight-bold">
                                {{ __("Virtual Card Issuance Fee") }}
                            </label>
                            <div class="input-group">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control"
                                    name="virtual_card_issuance_fee"
                                    placeholder="0.00"
                                    value="{{ old('virtual_card_issuance_fee', $setup?->virtual_issuance_fee ?? 0) }}"
                                >
                                <span class="input-group-text">{{ __("USDT") }}</span>
                            </div>
                            <small class="text--muted">
                                {{ __("The fee charged upon issuing a virtual crypto card (leave 0 or blank for free).") }}
                            </small>
                        </div>

                        <div class="col-12 form-group">
                            <label class="form-label font-weight-bold">
                                {{ __("Virtual Card Funding Fee") }}
                            </label>
                            <div class="input-group">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control"
                                    name="virtual_card_funding_fee"
                                    placeholder="0.00"
                                    value="{{ old('virtual_card_funding_fee', $setup?->virtual_funding_fee ?? 0) }}"
                                >
                                <span class="input-group-text">{{ __("USDT") }}</span>
                            </div>
                            <small class="text--muted">
                                {{ __("The fee charged when topping up or funding a virtual crypto card.") }}
                            </small>
                        </div>

                        <div class="col-12 form-group">
                            <label class="form-label font-weight-bold">
                                {{ __("Virtual Card Monthly Maintenance Fee") }}
                            </label>
                            <div class="input-group">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control"
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
        <div class="col-xl-6 col-lg-6 mb-30">
            <div class="card h-100">
                <div class="card-header bg--info">
                    <h5 class="card-title text-white">
                        <i class="las la-id-card me-2"></i>{{ __("Physical Card Charges") }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 form-group">
                            <label class="form-label font-weight-bold">
                                {{ __("Physical Card Order / Issuance Fee") }}
                            </label>
                            <div class="input-group">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control"
                                    name="physical_card_fee"
                                    placeholder="0.00"
                                    value="{{ old('physical_card_fee', $setup?->physical_order_fee ?? 0) }}"
                                >
                                <span class="input-group-text">{{ __("USDT") }}</span>
                            </div>
                            <small class="text--muted">
                                {{ __("The fee charged for ordering and issuing a physical crypto card.") }}
                            </small>
                        </div>

                        <div class="col-12 form-group">
                            <label class="form-label font-weight-bold">
                                {{ __("Physical Card Funding Fee") }}
                            </label>
                            <div class="input-group">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control"
                                    name="physical_card_funding_fee"
                                    placeholder="0.00"
                                    value="{{ old('physical_card_funding_fee', $setup?->physical_funding_fee ?? 0) }}"
                                >
                                <span class="input-group-text">{{ __("USDT") }}</span>
                            </div>
                            <small class="text--muted">
                                {{ __("The fee charged when topping up or funding a physical crypto card.") }}
                            </small>
                        </div>

                        <div class="col-12 form-group">
                            <label class="form-label font-weight-bold">
                                {{ __("Physical Card Monthly Maintenance Fee") }}
                            </label>
                            <div class="input-group">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control"
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

        {{-- Submit Button --}}
        <div class="col-xl-12 col-lg-12 mb-30">
            <button type="submit" class="btn btn--primary w-100 py-3 font-weight-bold">
                <i class="las la-check-circle me-1"></i>{{ __("Update All Charges") }}
            </button>
        </div>
    </div>
</form>
@endsection
