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
                {{ __('Crypto Card Charges') }}
            </h5>

            <div>
                <a
                    href="{{ route('admin.crypto-card-charge.edit') }}"
                    class="btn--base"
                >
                    {{ __('Edit Charges') }}
                </a>
            </div>

        </div>

    </div>

    <div class="table-wrapper">

        <div class="table-responsive">

            <table class="custom-table">

                <thead>
                    <tr>
                        <th>{{ __('Charge') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>

                <tbody>

                    <tr>
                        <td>
                            {{ __('Physical Card Fee') }}
                        </td>

                        <td>
                            {{ number_format((float) ($setup?->physical_card_fee ?? 0), 2) }}
                        </td>

                        <td>
                            <a
                                href="{{ route('admin.crypto-card-charge.edit') }}"
                                class="btn--base"
                            >
                                {{ __('Edit') }}
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            {{ __('Virtual Card Fee (Issuance)') }}
                        </td>

                        <td>
                            {{ number_format((float) ($setup?->card_issuance_fee ?? 0), 2) }}
                        </td>

                        <td>
                            <a
                                href="{{ route('admin.crypto-card-charge.edit') }}"
                                class="btn--base"
                            >
                                {{ __('Edit') }}
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            {{ __('Card Funding Fee') }}
                        </td>

                        <td>
                            {{ number_format((float) ($setup?->card_funding_fee ?? 0), 2) }}
                        </td>

                        <td>
                            <a
                                href="{{ route('admin.crypto-card-charge.edit') }}"
                                class="btn--base"
                            >
                                {{ __('Edit') }}
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            {{ __('Monthly Card Maintenance Fee') }}
                        </td>

                        <td>
                            {{ number_format((float) ($setup?->monthly_card_maintenance_fee ?? 0), 2) }}
                        </td>

                        <td>
                            <a
                                href="{{ route('admin.crypto-card-charge.edit') }}"
                                class="btn--base"
                            >
                                {{ __('Edit') }}
                            </a>
                        </td>
                    </tr>

                    @if (!$setup)

                        <tr>
                            <td colspan="3" class="text-center">
                                {{ __('No crypto card charge configuration has been created yet.') }}
                            </td>
                        </tr>

                    @endif

                </tbody>

            </table>

        </div>

    </div>

</div>


@endsection
