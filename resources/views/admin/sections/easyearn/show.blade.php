@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        ['name' => __("Dashboard"), 'url' => setRoute("admin.dashboard")],
        ['name' => __("EasyEarn"), 'url' => route('admin.easyearn.index')]
    ], 'active' => __("Plan Details")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper mb-20">
            <div class="table-header"><h5 class="title">{{ __('EasyEarn Plan') }} #{{ $plan->id }}</h5></div>
            <div class="row">
                <div class="col-lg-3 mb-3"><label>{{ __('User') }}</label><input class="form--control" value="{{ optional($plan->user)->email }}" disabled></div>
                <div class="col-lg-3 mb-3"><label>{{ __('Deposited') }}</label><input class="form--control" value="{{ $plan->usdt_amount_deposited }} USDT" disabled></div>
                <div class="col-lg-3 mb-3"><label>{{ __('Expected Return') }}</label><input class="form--control" value="{{ $plan->expected_return }} USDT" disabled></div>
                <div class="col-lg-3 mb-3"><label>{{ __('Status') }}</label><input class="form--control" value="{{ Str::headline($plan->status) }}" disabled></div>
            </div>
            <form method="POST" action="{{ route('admin.easyearn.update', $plan->id) }}">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-lg-4 mb-3">
                        <select class="form--control" name="action">
                            <option value="trigger_payout">{{ __('Trigger Maturity Payout') }}</option>
                            <option value="mark_matured">{{ __('Mark Matured') }}</option>
                            <option value="cancel">{{ __('Cancel') }}</option>
                        </select>
                    </div>
                    <div class="col-lg-3 mb-3">
                        <button class="btn--base" onclick="return confirm('{{ __('Confirm EasyEarn action?') }}')">{{ __('Submit') }}</button>
                    </div>
                </div>
            </form>
            <form method="POST" action="{{ route('admin.easyearn.destroy', $plan->id) }}" class="mt-2">
                @csrf
                @method('DELETE')
                <button class="btn--danger" onclick="return confirm('{{ __('Cancel this EasyEarn plan?') }}')">{{ __('Cancel Plan') }}</button>
            </form>
        </div>

        <div class="table-wrapper">
            <div class="table-header"><h5 class="title">{{ __('Immutable Transaction History') }}</h5></div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Date') }}</th></tr></thead>
                    <tbody>
                        @forelse($transactions as $transaction)
                            <tr>
                                <td>{{ Str::headline($transaction->type) }}</td>
                                <td>{{ Str::headline($transaction->status) }}</td>
                                <td>{{ $transaction->amount }} USDT</td>
                                <td>{{ $transaction->reference }}</td>
                                <td>{{ optional($transaction->created_at)->format('d-m-Y h:i A') }}</td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 5])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $transactions->links() }}
    </div>
@endsection
