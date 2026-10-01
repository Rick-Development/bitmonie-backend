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
    ], 'active' => __("Money Out Logs")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("All Logs") }}</h5>
                <div class="table-btn-area">
                    @include('admin.components.search-input',[
                        'name'  => 'transactioin_search',
                    ])
                </div>
            </div>
            <div class="table-responsive">
                @include('admin.components.data-table.money-out-table',compact('logs'))
            </div>
            {{ get_paginate($logs) }}
        </div>
    </div>
@endsection

@push('script')
    <script>
        itemSearch($("input[name=transactioin_search]"),$(".transaction-search-table"),"{{ setRoute('admin.money.out.search') }}");
    </script>
@endpush