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
    ], 'active' => __("P2P Risk Management")])
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
                            <th>{{__('Risk Score')}}</th>
                            <th>{{__('Level')}}</th>
                            <th>{{__('Stats (Compl/Total/Disputes)')}}</th>
                            <th>{{__('Action')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $item)
                            <tr>
                                <td>
                                    <ul class="user-list">
                                        <li>{{ $item->user->fullname ?? 'N/A' }}</li>
                                        <li><span>{{ $item->user->email ?? 'N/A' }}</span></li>
                                    </ul>
                                </td>
                                <td>{{ $item->risk_score }}</td>
                                <td>
                                    @if($item->risk_level == 'low')
                                        <span class="badge badge--success">{{ __('Low') }}</span>
                                    @elseif($item->risk_level == 'moderate')
                                        <span class="badge badge--warning">{{ __('Moderate') }}</span>
                                    @else
                                        <span class="badge badge--danger">{{ __('High') }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $item->completion_rate }}% 
                                    ({{ $item->total_orders }} orders)
                                    <br>
                                    <span class="text--danger">{{ $item->disputes_lost }} lost</span> / {{ $item->disputes_won }} won
                                </td>
                                <td>
                                    <form action="{{ setRoute('admin.p2p.risk.recalculate', $item->user_id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn--base btn-sm" title="Recalculate Score"><i class="las la-sync"></i></button>
                                    </form>
                                    @if($item->risk_level != 'high')
                                    <form action="{{ setRoute('admin.p2p.risk.flag', $item->user_id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn--danger btn-sm" title="Flag as High Risk" onclick="return confirm('Flag user as High Risk?')"><i class="las la-flag"></i></button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 5])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $users->links() }}
    </div>
@endsection
