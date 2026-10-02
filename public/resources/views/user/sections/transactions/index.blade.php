@extends('user.layouts.master')

@section('content')
<div class="dashboard-list-area mt-20">
    <div class="dashboard-header-wrapper">
        <h4 class="title">{{ __($page_title) ?? 'Transaction Log' }}</h4>
        <div class="header-search-wrapper">
            <div class="position-relative">
                <input class="form-control search" type="text" name="search" placeholder="{{ __("Ex: Transaction, Add Money") }}" aria-label="Search">
                <span class="las la-search"></span>
            </div>
        </div>
    </div>
    <div class="dashboard-list-wrapper">
        <div class="item-wrapper transactions-search">
            @include('user.components.transaction.log', compact('transactions'))
        </div>
    </div>
</div>
@endsection
@push('script')
    <script>
        itemSearch($(".search"),$(".transactions-search"),"{{ setRoute('user.transactions.search') }}",2);
    </script>
@endpush
