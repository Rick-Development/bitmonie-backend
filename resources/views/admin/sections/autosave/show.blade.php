@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        ['name' => __("Dashboard"), 'url' => setRoute("admin.dashboard")],
        ['name' => __("AutoSave"), 'url' => route('admin.autosave.index')]
    ], 'active' => __("Plan Details")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper mb-20">
            <div class="table-header">
                <h5 class="title">{{ $plan->name }}</h5>
            </div>
            <form method="POST" action="{{ route('admin.autosave.update', $plan->id) }}">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-lg-4 mb-3">
                        <label>{{ __('Status') }}</label>
                        <select class="form--control" name="status">
                            @foreach(['active','paused','cancelled'] as $status)
                                <option value="{{ $status }}" @selected($plan->status === $status)>{{ Str::headline($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-4 mb-3">
                        <label>{{ __('Balance') }}</label>
                        <input class="form--control" value="{{ $plan->balance }}" disabled>
                    </div>
                    <div class="col-lg-4 mb-3">
                        <label>{{ __('User') }}</label>
                        <input class="form--control" value="{{ optional($plan->user)->email }}" disabled>
                    </div>
                </div>
                <button class="btn--base">{{ __('Update Status') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.autosave.destroy', $plan->id) }}" class="mt-3">
                @csrf
                @method('DELETE')
                <button class="btn--danger" onclick="return confirm('{{ __('Cancel this plan?') }}')">{{ __('Cancel Plan') }}</button>
            </form>
        </div>

        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __('Deduction History') }}</h5>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Reference') }}</th>
                            <th>{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $transaction)
                            <tr>
                                <td>{{ Str::headline($transaction->type) }}</td>
                                <td>{{ Str::headline($transaction->status) }}</td>
                                <td>{{ get_amount($transaction->amount, get_default_currency_code()) }}</td>
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
