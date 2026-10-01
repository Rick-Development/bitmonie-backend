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
    ], 'active' => __("P2P Disputes")])
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
                            <th>{{__('Order ID')}}</th>
                            <th>{{__('Type')}}</th>
                            <th>{{__('Maker')}}</th>
                            <th>{{__('Taker')}}</th>
                            <th>{{__('Amount')}}</th>
                            <th>{{__('Total Price')}}</th>
                            <th>{{__('Status')}}</th>
                            <th>{{__('Action')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($disputes as $item)
                            <tr>
                                <td>{{ $item->uid }}</td>
                                <td><span class="badge {{ $item->type == 'buy' ? 'badge--success' : 'badge--primary' }}">{{ strtoupper($item->type) }}</span></td>
                                <td>{{ $item->maker->username }}</td>
                                <td>{{ $item->taker->username }}</td>
                                <td>{{ get_amount($item->amount, $item->asset) }}</td>
                                <td>{{ get_amount($item->total_price, $item->fiat) }}</td>
                                <td>
                                    <span class="badge badge--warning">{{ $item->appeal_status }}</span>
                                </td>
                                <td>
                                    <a href="{{ setRoute('admin.p2p.disputes.show', $item->id) }}" class="btn btn--base"><i class="las la-eye"></i></a>
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 8])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $disputes->links() }}
    </div>
@endsection
