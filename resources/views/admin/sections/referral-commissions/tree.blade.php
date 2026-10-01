@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        ['name' => __("Dashboard"), 'url' => setRoute("admin.dashboard")]
    ], 'active' => __("Referral Tree")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header"><h5 class="title">{{ $user->email }}</h5></div>
            <pre style="white-space: pre-wrap;">{{ json_encode($tree, JSON_PRETTY_PRINT) }}</pre>
        </div>
    </div>
@endsection
