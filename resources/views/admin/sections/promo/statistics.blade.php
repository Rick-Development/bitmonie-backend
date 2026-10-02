@extends('admin.layouts.master')

@section('page-title')
@include('admin.components.page-title', [
'title' => __('Promo Statistics'),
])
@endsection

@section('breadcrumb')
@include('admin.components.breadcrumb', [
'breadcrumbs' => [
[
'name' => __('Dashboard'),
'url' => setRoute('admin.dashboard'),
],
[
'name' => __('Promos'),
'url' => route('admin.promo.index'),
],
],
'active' => __('Statistics'),
])
@endsection

@section('content') <div class="table-area"> <div class="table-wrapper">

        {{-- ============================================================
             TABLE HEADER
             ============================================================ --}}
        <div class="table-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                <div class="title">
                    <h4 class="title">
                        {{ __('Promo Statistics') }}
                    </h4>
                </div>

                <a
                    href="{{ route('admin.promo.index') }}"
                    class="btn--base"
                >
                    <i class="las la-arrow-left"></i>
                    {{ __('Back to Promos') }}
                </a>

            </div>
        </div>
        


        {{-- ============================================================
             STATISTICS
             ============================================================ --}}
        @php
            $total = is_array($statistics)
                ? ($statistics['total'] ?? 0)
                : ($statistics->total ?? 0);

            $pending = is_array($statistics)
                ? ($statistics['pending'] ?? 0)
                : ($statistics->pending ?? 0);

            $active = is_array($statistics)
                ? ($statistics['active'] ?? 0)
                : ($statistics->active ?? 0);

            $inactive = is_array($statistics)
                ? ($statistics['inactive'] ?? 0)
                : ($statistics->inactive ?? 0);
        @endphp


        {{-- ============================================================
             SUMMARY CARDS
             ============================================================ --}}
        <div class="row">

            {{-- TOTAL --}}
            <div class="col-xl-3 col-lg-3 col-md-6 mb-20">
                <div class="dashboard-widget">
                    <div class="dashboard-widget__content">
                        <h3>
                            {{ $total }}
                        </h3>

                        <p>
                            {{ __('Total Promos') }}
                        </p>
                    </div>

                    <div class="dashboard-widget__icon">
                        <i class="las la-bullhorn"></i>
                    </div>
                </div>
            </div>


            {{-- PENDING --}}
            <div class="col-xl-3 col-lg-3 col-md-6 mb-20">
                <div class="dashboard-widget">
                    <div class="dashboard-widget__content">
                        <h3>
                            {{ $pending }}
                        </h3>

                        <p>
                            {{ __('Pending Promos') }}
                        </p>
                    </div>

                    <div class="dashboard-widget__icon">
                        <i class="las la-clock"></i>
                    </div>
                </div>
            </div>


            {{-- ACTIVE --}}
            <div class="col-xl-3 col-lg-3 col-md-6 mb-20">
                <div class="dashboard-widget">
                    <div class="dashboard-widget__content">
                        <h3>
                            {{ $active }}
                        </h3>

                        <p>
                            {{ __('Active Promos') }}
                        </p>
                    </div>

                    <div class="dashboard-widget__icon">
                        <i class="las la-check-circle"></i>
                    </div>
                </div>
            </div>


            {{-- INACTIVE --}}
            <div class="col-xl-3 col-lg-3 col-md-6 mb-20">
                <div class="dashboard-widget">
                    <div class="dashboard-widget__content">
                        <h3>
                            {{ $inactive }}
                        </h3>

                        <p>
                            {{ __('Inactive Promos') }}
                        </p>
                    </div>

                    <div class="dashboard-widget__icon">
                        <i class="las la-times-circle"></i>
                    </div>
                </div>
            </div>

        </div>


        {{-- ============================================================
             STATISTICS TABLE
             ============================================================ --}}
        <div class="table-responsive mt-20">
            <table class="custom-table">

                <thead>
                    <tr>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Count') }}</th>
                    </tr>
                </thead>

                <tbody>

                    {{-- PENDING --}}
                    <tr>
                        <td>
                            <span class="badge badge--warning">
                                {{ __('Pending') }}
                            </span>
                        </td>

                        <td>
                            {{ $pending }}
                        </td>
                    </tr>


                    {{-- ACTIVE --}}
                    <tr>
                        <td>
                            <span class="badge badge--success">
                                {{ __('Active') }}
                            </span>
                        </td>

                        <td>
                            {{ $active }}
                        </td>
                    </tr>


                    {{-- INACTIVE --}}
                    <tr>
                        <td>
                            <span class="badge badge--danger">
                                {{ __('Inactive') }}
                            </span>
                        </td>

                        <td>
                            {{ $inactive }}
                        </td>
                    </tr>


                    {{-- TOTAL --}}
                    <tr>
                        <td>
                            <strong>
                                {{ __('Total') }}
                            </strong>
                        </td>

                        <td>
                            <strong>
                                {{ $total }}
                            </strong>
                        </td>
                    </tr>

                </tbody>

            </table>
        </div>

    </div>
</div>


@endsection
