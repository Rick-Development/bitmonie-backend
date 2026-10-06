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
    'active' => __("Create Charges")
])


@endsection

@section('content')


<div class="row mb-30-none">

    <div class="col-xl-12 col-lg-12 mb-30">

        <div class="card">

            <div class="card-header bg--primary">

                <h5 class="card-title text-white">
                    {{ __("Create Crypto Card Charges") }}
                </h5>

            </div>

            <div class="card-body">

                <form
                    action="{{ setRoute('admin.crypto-card-charge.store') }}"
                    method="POST"
                >

                    @csrf

                    <div class="row">

                        <div class="col-xl-6 col-lg-6 form-group">

                            <label>
                                {{ __("Physical Card Fee") }}
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control"
                                    name="physical_card_fee"
                                    placeholder="0.00"
                                    value="{{ old('physical_card_fee', 0) }}"
                                >

                                <span class="input-group-text">
                                    {{ __("USDT") }}
                                </span>

                            </div>

                            <small class="text--muted">
                                {{ __("The fee charged for issuing or obtaining a physical crypto card (leave blank or 0 for free).") }}
                            </small>

                        </div>

                        <div class="col-xl-6 col-lg-6 form-group">

                            <label>
                                {{ __("Virtual Card Fee (Issuance)") }}
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control"
                                    name="card_issuance_fee"
                                    placeholder="0.00"
                                    value="{{ old('card_issuance_fee', 0) }}"
                                >

                                <span class="input-group-text">
                                    {{ __("USDT") }}
                                </span>

                            </div>

                            <small class="text--muted">
                                {{ __("The fee charged when a virtual crypto card is issued (leave blank or 0 for free).") }}
                            </small>

                        </div>

                        <div class="col-xl-6 col-lg-6 form-group">

                            <label>
                                {{ __("Card Funding Fee") }}
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control"
                                    name="card_funding_fee"
                                    placeholder="0.00"
                                    value="{{ old('card_funding_fee', 0) }}"
                                >

                                <span class="input-group-text">
                                    {{ __("USDT") }}
                                </span>

                            </div>

                            <small class="text--muted">
                                {{ __("The fee charged when funds are added to a crypto card (leave blank or 0 for free).") }}
                            </small>

                        </div>

                        <div class="col-xl-6 col-lg-6 form-group">

                            <label>
                                {{ __("Monthly Card Maintenance Fee") }}
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control"
                                    name="monthly_card_maintenance_fee"
                                    placeholder="0.00"
                                    value="{{ old('monthly_card_maintenance_fee', 0) }}"
                                >

                                <span class="input-group-text">
                                    {{ __("USDT") }}
                                </span>

                            </div>

                            <small class="text--muted">
                                {{ __("The recurring monthly fee charged for maintaining an active crypto card (leave blank or 0 for free).") }}
                            </small>

                        </div>

                    </div>

                    <div class="col-xl-12 col-lg-12 form-group">

                        <button
                            type="submit"
                            class="btn btn--primary w-100"
                        >
                            {{ __("Create Charges") }}
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>


@endsection
