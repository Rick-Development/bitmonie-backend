@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        ['name' => __("Dashboard"), 'url' => setRoute("admin.dashboard")]
    ], 'active' => __("Saved Beneficiaries")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __($page_title) }}</h5>
                <a href="{{ route('admin.beneficiaries.export', request()->query()) }}" class="btn--base">{{ __('Export CSV') }}</a>
            </div>

            <form method="GET" class="mb-20">
                <div class="row">
                    <div class="col-lg-3 mb-2"><input class="form--control" name="search" value="{{ request('search') }}" placeholder="{{ __('Search') }}"></div>
                    <div class="col-lg-3 mb-2"><input class="form--control" name="user" value="{{ request('user') }}" placeholder="{{ __('User/email') }}"></div>
                    <div class="col-lg-2 mb-2">
                        <select class="form--control" name="transaction_type">
                            <option value="">{{ __('All Types') }}</option>
                            @foreach($types as $type)
                                <option value="{{ $type }}" @selected(request('transaction_type') === $type)>{{ Str::headline($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 mb-2"><input type="date" class="form--control" name="from" value="{{ request('from') }}"></div>
                    <div class="col-lg-2 mb-2"><button class="btn--base w-100">{{ __('Filter') }}</button></div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __('User') }}</th>
                            <th>{{ __('Beneficiary') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Favorite') }}</th>
                            <th>{{ __('Created') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($beneficiaries as $beneficiary)
                            <tr>
                                <td>
                                    <ul class="user-list">
                                        <li>{{ optional($beneficiary->user)->fullname }}</li>
                                        <li><span>{{ optional($beneficiary->user)->email }}</span></li>
                                    </ul>
                                </td>
                                <td>{{ $beneficiary->beneficiary_name ?? data_get($beneficiary->info, 'account_holder_name') }}</td>
                                <td>{{ Str::headline($beneficiary->transaction_type ?? data_get($beneficiary->info, 'beneficiary_subtype')) }}</td>
                                <td>{{ $beneficiary->is_favorite ? __('Yes') : __('No') }}</td>
                                <td>{{ optional($beneficiary->created_at)->format('d-m-Y h:i A') }}</td>
                                <td><a href="{{ route('admin.beneficiaries.show', $beneficiary->id) }}" class="btn--base">{{ __('View') }}</a></td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 6])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $beneficiaries->links() }}
    </div>
@endsection
