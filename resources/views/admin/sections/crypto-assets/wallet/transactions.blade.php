@extends('admin.layouts.master')

@push('css')
    <style>
        .fileholder {
            min-height: 200px !important;
        }

        .fileholder-files-view-wrp.accept-single-file .fileholder-single-file-view,.fileholder-files-view-wrp.fileholder-perview-single .fileholder-single-file-view{
            height: 156px !important;
        }
    </style>
@endpush

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ],
        [
            'name'  => __("Gateways"),
            'url'   => setRoute('admin.payment.gateway.view',[$gateway->slug,strtolower($gateway->type)]),
        ],
        [
            'name'  => __("Gateway Edit"),
            'url'   => setRoute('admin.payment.gateway.edit',[$gateway->slug,strtolower($gateway->type),$gateway->alias]),
        ],
        [
            'name'  => __("Crypto Assets"),
            'url'   => setRoute('admin.crypto.assets.gateway.index',$gateway->alias),
        ],
    ], 'active' => __("Incoming Transactions")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("Transacitons") }}
                    (Gateway: {{ $gateway->name }}, Coin: {{ $crypto_asset->coin }})
                </h5>

                <div class="table-btn-area">
                    @include('admin.components.search-input',[
                        'name'          => 'transactioin_search',
                        'placeholder'   => "Txn Hash",
                    ])
                </div>
            </div>
            <div class="table-responsive">
                {{-- <table class="custom-table transaction-search-table">
                    <thead>
                        <tr>
                            <th>SL NO</th>
                            <th>TXN Type</th>
                            <th>Sender</th>
                            <th>Receiver</th>
                            <th>TXN Hash</th>
                            <th>Asset</th>
                            <th>Chain</th>
                            <th>Amount</th>
                            <th>Block Number</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($incoming_transactions ?? [] as $key => $item)
                            <tr>
                                <td>
                                    {{ $key + $incoming_transactions->firstItem() }}
                                </td>
                                <td>{{ $item->transaction_type }}
                                <td>{{ $item->sender_address }}</td>
                                <td>{{ $item->receiver_address }}</td>
                                <td>{{ $item->txn_hash }}</td>
                                <td>{{ $item->asset }}</td>
                                <td>{{ $item->chain }}</td>
                                <td>{{ $item->amount }}</td>
                                <td>{{ $item->block_number }}</td>
                                <td>
                                    @if ($item->status == payment_gateway_const()::NOT_USED)
                                        <div class="badge badge--danger">
                                            {{ __("Not Used") }}
                                        </div>
                                    @elseif ($item->status == payment_gateway_const()::USED)
                                        <div class="badge badge--success">
                                            {{ __("Used") }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 7])
                        @endforelse
                    </tbody>
                </table> --}}

                @include('admin.components.data-table.crypto-address-transaction-table',compact('incoming_transactions'))
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        itemSearch($("input[name=transactioin_search]"),$(".transaction-search-table"),"{{ setRoute('admin.crypto.assets.wallet.transaction.search',[$crypto_asset->id,$wallet_credentials_id]) }}");
    </script>
@endpush