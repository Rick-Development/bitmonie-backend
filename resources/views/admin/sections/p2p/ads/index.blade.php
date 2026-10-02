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
    ], 'active' => __("P2P Ads")])
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
                            <th>{{__('Type')}}</th>
                            <th>{{__('Asset')}}</th>
                            <th>{{__('Fiat')}}</th>
                            <th>{{__('Price')}}</th>
                            <th>{{__('Available')}}</th>
                            <th>{{__('Status')}}</th>
                            <th>{{__('Action')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ads as $item)
                            <tr>
                                <td>
                                    <ul class="user-list">
                                        <li>{{ $item->user->fullname ?? 'N/A' }}</li>
                                        <li><span>{{ $item->user->email ?? 'N/A' }}</span></li>
                                    </ul>
                                </td>
                                <td><span class="badge {{ $item->type == 'buy' ? 'badge--success' : 'badge--primary' }}">{{ strtoupper($item->type) }}</span></td>
                                <td>{{ $item->asset }}</td>
                                <td>{{ $item->fiat }}</td>
                                <td>{{ get_amount($item->fixed_price, $item->fiat) }}</td>
                                <td>{{ get_amount($item->available_amount, $item->asset) }}</td>
                                <td>
                                    <span class="badge {{ $item->status == 'active' ? 'badge--success' : 'badge--warning' }}">{{ $item->status }}</span>
                                </td>
                                <td>
                                    <a href="{{ setRoute('admin.p2p.ads.show', $item->id) }}" class="btn btn--base"><i class="las la-eye"></i></a>
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 8])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $ads->links() }}
    </div>
@endsection
