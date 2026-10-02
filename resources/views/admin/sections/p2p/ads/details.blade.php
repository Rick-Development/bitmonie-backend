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
            'name'  => __("P2P Ads"),
            'url'   => setRoute("admin.p2p.ads.index"),
        ]
    ], 'active' => __("Ad Details")])
@endsection

@section('content')
    <div class="row mb-30-none">
        <!-- Ad Details -->
        <div class="col-xl-6 col-lg-6 mb-30">
            <div class="sidebar-user-area">
                <div class="sidebar-user-content">
                    <h4 class="title">{{ __('Ad Configuration') }}</h4>
                    <ul class="user-list">
                        <li><span class="caption">Type:</span> <span class="value">{{ strtoupper($ad->type) }}</span></li>
                        <li><span class="caption">Asset:</span> <span class="value">{{ $ad->asset }}</span></li>
                        <li><span class="caption">Fiat:</span> <span class="value">{{ $ad->fiat }}</span></li>
                        <li><span class="caption">Price:</span> <span class="value">{{ get_amount($ad->fixed_price, $ad->fiat) }}</span></li>
                        <li><span class="caption">Total Amount:</span> <span class="value">{{ get_amount($ad->total_amount, $ad->asset) }}</span></li>
                        <li><span class="caption">Available:</span> <span class="value">{{ get_amount($ad->available_amount, $ad->asset) }}</span></li>
                        <li><span class="caption">Limits:</span> <span class="value">{{ $ad->min_limit }} - {{ $ad->max_limit }} {{ $ad->fiat }}</span></li>
                        <li><span class="caption">Payment Window:</span> <span class="value">{{ $ad->time_limit }} Minutes</span></li>
                        <li><span class="caption">Status:</span> <span class="badge {{ $ad->status == 'active' ? 'badge--success' : 'badge--warning' }}">{{ $ad->status }}</span></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="col-xl-6 col-lg-6 mb-30">
            <div class="custom-card item-center">
                <div class="card-header">
                    <h6 class="title">{{ __('Admin Actions') }}</h6>
                </div>
                <div class="card-body">
                    <form action="{{ setRoute('admin.p2p.ads.update', $ad->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="active" {{ $ad->status == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="offline" {{ $ad->status == 'offline' ? 'selected' : '' }}>Offline</option>
                                <option value="completed" {{ $ad->status == 'completed' ? 'selected' : '' }}>Completed</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn--base w-100">{{ __('Update Status') }}</button>
                    </form>
                    
                    <form action="{{ setRoute('admin.p2p.ads.destroy', $ad->id) }}" method="POST" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--danger w-100" onclick="return confirm('Are you sure?')">{{ __('Delete Ad') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
