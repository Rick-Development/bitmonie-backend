@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __("Savings Logs")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __($page_title) }}</h5>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{__('User')}}</th>
                            <th>{{__('Balance')}}</th>
                            <th>{{__('Date')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $item)
                            <tr>
                                <td>
                                    <ul class="user-list">
                                        <li>{{ $item->user->fullname }}</li>
                                        <li><span>{{ $item->user->email }}</span></li>
                                    </ul>
                                </td>
                                <td>{{ get_amount($item->balance, get_default_currency_code()) }}</td>
                                <td>{{ $item->created_at->format('d-m-y h:i:s A') }}</td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 3])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $logs->links() }}
    </div>
@endsection
