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
    ], 'active' => __("Merchant Applications")])
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
                            <th>{{__('Business Name')}}</th>
                            <th>{{__('Contact')}}</th>
                            <th>{{__('USDT Balance')}}</th>
                            <th>{{__('Deposit')}}</th>
                            <th>{{__('Status')}}</th>
                            <th>{{__('Date')}}</th>
                            <th>{{__('Action')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($applications as $item)
                            <tr>
                                <td>
                                    <ul class="user-list">
                                        <li>{{ $item->user->fullname ?? 'N/A' }}</li>
                                        <li><span>{{ $item->user->email ?? 'N/A' }}</span></li>
                                    </ul>
                                </td>
                                <td>{{ $item->business_name ?? 'N/A' }}</td>
                                <td>
                                    <span>Phone: {{ $item->phone }}</span><br>
                                    <small>WA: {{ $item->whatsapp ?? 'N/A' }}</small>
                                </td>
                                <td>{{ get_amount($item->quidax_usdt_balance, 'USDT') }}</td>
                                <td>
                                    <span>{{ get_amount($item->security_deposit_amount, strtoupper($item->security_deposit_currency ?? 'USDT')) }}</span><br>
                                    <small>{{ ucfirst(str_replace('_', ' ', $item->security_deposit_status ?? 'not_locked')) }}</small>
                                </td>
                                <td>
                                    @php
                                        $displayStatus = $item->display_status;
                                        $statusClass = match($displayStatus) {
                                            'pending' => 'badge--warning',
                                            'approved' => 'badge--success',
                                            'rejected' => 'badge--danger',
                                            'deactivated' => 'badge--secondary',
                                            default => 'badge--primary'
                                        };
                                    @endphp
                                    <span class="badge {{ $statusClass }}">{{ ucfirst($displayStatus) }}</span>
                                </td>
                                <td>{{ $item->created_at->format('d M Y, h:i A') }}</td>
                                <td>
                                    <a href="{{ setRoute('admin.p2p.merchant.applications.show', $item->id) }}" class="btn btn--base"><i class="las la-eye"></i></a>
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 8])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $applications->links() }}
    </div>
@endsection
