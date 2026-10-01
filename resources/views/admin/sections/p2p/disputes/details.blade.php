@extends('admin.layouts.master')

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
            'name'  => __("P2P Disputes"),
            'url'   => setRoute("admin.p2p.disputes.index"),
        ]
    ], 'active' => __("Dispute Details")])
@endsection

@section('content')
    <div class="row mb-30-none">
        
        <!-- Order Info -->
        <div class="col-xl-6 col-lg-6 mb-30">
            <div class="sidebar-user-area">
                <div class="sidebar-user-content">
                    <h4 class="title">{{ __('Order Information') }}</h4>
                    <ul class="user-list">
                        <li><span class="caption">Order ID:</span> <span class="value">{{ $dispute->uid }}</span></li>
                        <li><span class="caption">Type:</span> <span class="value">{{ strtoupper($dispute->type) }}</span></li>
                        <li><span class="caption">Asset:</span> <span class="value">{{ $dispute->asset }}</span></li>
                        <li><span class="caption">Amount:</span> <span class="value">{{ get_amount($dispute->amount, $dispute->asset) }}</span></li>
                        <li><span class="caption">Fiat Amount:</span> <span class="value">{{ get_amount($dispute->total_price, $dispute->fiat) }}</span></li>
                        <li><span class="caption">Payment Method:</span> <span class="value">{{ $dispute->payment_method_input->name ?? 'N/A' }}</span></li>
                        <li><span class="caption">Status:</span> <span class="badge badge--warning">{{ $dispute->status }}</span></li>
                    </ul>
                </div>
            </div>
            
            <div class="sidebar-user-area mt-4">
                <div class="sidebar-user-content">
                    <h4 class="title">{{ __('Resolution Form') }}</h4>
                    <form action="{{ setRoute('admin.p2p.disputes.resolve', $dispute->id) }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label>{{ __('Decision') }}*</label>
                            <select name="action" class="form-control" required>
                                <option value="">{{ __('Select Action') }}</option>
                                <option value="release_to_buyer">{{ __('Release to Buyer (Buyer Wins)') }}</option>
                                <option value="refund_to_seller">{{ __('Refund to Seller (Seller Wins)') }}</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>{{ __('Admin Notes') }}</label>
                            <textarea name="admin_notes" class="form-control" rows="4"></textarea>
                        </div>
                        <button type="submit" class="btn btn--base w-100" onclick="return confirm('Are you sure? This action is irreversible.')">{{ __('Resolve Dispute') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Chat History -->
        <div class="col-xl-6 col-lg-6 mb-30">
            <div class="custom-card">
                <div class="card-header">
                    <h6 class="title">{{ __('Chat History') }}</h6>
                </div>
                <div class="card-body">
                    <div class="chat-area" style="max-height: 600px; overflow-y: auto;">
                        @forelse ($dispute->chats as $chat)
                            <div class="chat-item {{ $chat->sender_id == $dispute->maker_id ? 'text-end' : 'text-start' }} mb-3 p-2" style="border: 1px solid #eee; border-radius: 5px; {{ $chat->sender_id == $dispute->maker_id ? 'background: #f9f9f9;' : 'background: #fff;' }}">
                                <strong>{{ $chat->sender_id == $dispute->maker_id ? 'Maker' : 'Taker' }}</strong>
                                <p class="mb-0">{{ $chat->message }}</p>
                                @if($chat->file)
                                    <a href="{{ asset('storage/' . $chat->file) }}" target="_blank" class="text--base">View Attachment</a>
                                @endif
                                <small class="text-muted">{{ $chat->created_at->format('d M, h:i A') }}</small>
                            </div>
                        @empty
                            <div class="text-center p-4">{{ __('No messages found.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
