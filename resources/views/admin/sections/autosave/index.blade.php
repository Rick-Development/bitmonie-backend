@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        ['name' => __("Dashboard"), 'url' => setRoute("admin.dashboard")]
    ], 'active' => __("AutoSave Plans")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper mb-20">
            <div class="table-header">
                <h5 class="title">{{ __('Total AutoSave Balance') }}: {{ get_amount($totalBalance, get_default_currency_code()) }}</h5>
                <div>
                    <a href="{{ route('admin.autosave.export.plans', request()->query()) }}" class="btn--base">{{ __('Export Plans') }}</a>
                    <a href="{{ route('admin.autosave.export.transactions') }}" class="btn--base">{{ __('Export Transactions') }}</a>
                </div>
            </div>
        </div>

        <div class="table-wrapper">
            <form method="GET" class="mb-20">
                <div class="row">
                    <div class="col-lg-3 mb-2"><input class="form--control" name="user" value="{{ request('user') }}" placeholder="{{ __('User/email') }}"></div>
                    <div class="col-lg-2 mb-2">
                        <select class="form--control" name="status">
                            <option value="">{{ __('All Status') }}</option>
                            @foreach(['active','paused','cancelled'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::headline($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 mb-2">
                        <select class="form--control" name="mode">
                            <option value="">{{ __('All Modes') }}</option>
                            @foreach(['scheduled','percentage','both'] as $mode)
                                <option value="{{ $mode }}" @selected(request('mode') === $mode)>{{ Str::headline($mode) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 mb-2"><input type="date" class="form--control" name="from" value="{{ request('from') }}"></div>
                    <div class="col-lg-2 mb-2"><input type="date" class="form--control" name="to" value="{{ request('to') }}"></div>
                    <div class="col-lg-1 mb-2"><button class="btn--base w-100">{{ __('Go') }}</button></div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __('User') }}</th>
                            <th>{{ __('Plan') }}</th>
                            <th>{{ __('Mode') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Balance') }}</th>
                            <th>{{ __('Next Due') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plans as $plan)
                            <tr>
                                <td>
                                    <ul class="user-list">
                                        <li>{{ optional($plan->user)->fullname }}</li>
                                        <li><span>{{ optional($plan->user)->email }}</span></li>
                                    </ul>
                                </td>
                                <td>{{ $plan->name }}</td>
                                <td>{{ Str::headline($plan->mode) }}</td>
                                <td>{{ Str::headline($plan->status) }}</td>
                                <td>{{ get_amount($plan->balance, get_default_currency_code()) }}</td>
                                <td>{{ optional($plan->next_due_at)->format('d-m-Y h:i A') ?: '-' }}</td>
                                <td><a href="{{ route('admin.autosave.show', $plan->id) }}" class="btn--base">{{ __('View') }}</a></td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 7])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $plans->links() }}
    </div>
@endsection
