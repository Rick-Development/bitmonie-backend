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
    ], 'active' => __("EduSave Logs")])
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
                            <th>{{__('Title')}}</th>
                            <th>{{__('Amount')}}</th>
                            <th>{{__('Period')}}</th>
                            <th>{{__('Start Date')}}</th>
                            <th>{{__('Status')}}</th>
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
                                <td>{{ $item->title }}</td>
                                <td>{{ get_amount($item->amount, get_default_currency_code()) }}</td>
                                <td>{{ $item->period }}</td>
                                <td>{{ $item->start_date->format('d-m-Y') }}</td>
                                <td>
                                    <span class="badge {{ $item->status == 'active' ? 'badge--success' : 'badge--warning' }}">{{ $item->status }}</span>
                                </td>
                                <td>{{ $item->created_at->format('d-m-y h:i:s A') }}</td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 7])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $logs->links() }}
    </div>
@endsection
