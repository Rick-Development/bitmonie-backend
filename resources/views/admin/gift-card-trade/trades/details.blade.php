@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => "Trade Details"])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ],
        [
            'name'  => __("Gift Card Trades"),
            'url'   => setRoute("admin.gift.card.trade.trade.index"),
        ]
    ], 'active' => __("Trade Details")])
@endsection

@section('content')
        <div class="col-xl-4 col-md-6 mb-30">
            <div class="card b-radius--10 overflow-hidden box--shadow1">
                <div class="card-body">
                    <h5 class="mb-20 text-muted">@lang('User Information')</h5>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            @lang('Date')
                            <span class="font-weight-bold">{{ $trade->created_at->format('d-m-Y H:i:s') }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            @lang('Username')
                            <span class="font-weight-bold">
                                <a href="{{ route('admin.users.details', $trade->user_id) }}">{{ @$trade->user->username }}</a>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            @lang('Status')
                            @if($trade->status == 'pending')
                                <span class="badge badge--warning">@lang('Pending')</span>
                            @elseif($trade->status == 'approved')
                                <span class="badge badge--success">@lang('Approved')</span>
                            @elseif($trade->status == 'rejected')
                                <span class="badge badge--danger">@lang('Rejected')</span>
                            @endif
                        </li>
                    </ul>
                </div>
            </div>
            
            @if($trade->status != 'pending')
            <div class="card b-radius--10 overflow-hidden box--shadow1 mt-3">
                <div class="card-body">
                    <h5 class="mb-20 text-muted">@lang('Admin Action')</h5>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            @lang('Admin')
                            <span class="font-weight-bold">{{ @$trade->admin->name ?? 'System' }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            @lang('Date')
                            <span class="font-weight-bold">{{ $trade->updated_at->format('d-m-Y H:i:s') }}</span>
                        </li>
                        @if($trade->rejection_reason)
                        <li class="list-group-item">
                            @lang('Rejection Reason')
                            <p class="font-weight-bold mt-2">{{ $trade->rejection_reason }}</p>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
            @endif
        </div>

        <div class="col-xl-8 col-md-6 mb-30">
            <div class="card b-radius--10 overflow-hidden box--shadow1">
                <div class="card-body">
                    <h5 class="card-title mb-20">@lang('Trade Information')</h5>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            @lang('Category')
                            <span class="font-weight-bold">{{ __($trade->category->name) }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            @lang('Type')
                            <span class="font-weight-bold">{{ __($trade->type->name) }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            @lang('Country')
                            <span class="font-weight-bold">{{ __($trade->country->name) }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            @lang('Card Amount')
                            <span class="font-weight-bold">{{ get_amount($trade->card_amount) }} {{ __($trade->card_currency) }}</span>
                        </li>
                         <li class="list-group-item d-flex justify-content-between align-items-center">
                            @lang('Payable Amount')
                            <span class="font-weight-bold">{{ get_amount($trade->ngn_amount) }} NGN</span>
                        </li>
                        @if($trade->card_code)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                @lang('Card Code')
                                <span class="font-weight-bold">{{ $trade->card_code }}</span>
                            </li>
                        @endif
                    </ul>

                    @if($trade->images->count() > 0)
                        <h5 class="card-title mt-4 mb-20">@lang('Attachments')</h5>
                        <div class="row">
                            @foreach($trade->images as $image)
                                <div class="col-md-3">
                                    <a href="{{ asset($image->image_path) }}" target="_blank">
                                        <img src="{{ asset($image->image_path) }}" alt="attachment" class="img-thumbnail">
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    
                    @if($trade->status == 'pending')
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <button class="btn btn--success ml-1 approveBtn" data-id="{{ $trade->id }}" data-amount="{{ get_amount($trade->ngn_amount) }}">
                                <i class="fas fa-check"></i> @lang('Approve')
                            </button>
                            
                            <button class="btn btn--danger ml-1 rejectBtn" data-id="{{ $trade->id }}">
                                <i class="fas fa-ban"></i> @lang('Reject')
                            </button>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Approve Modal --}}
    <div id="approveModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Approve Trade')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.gift.card.trade.trade.approve', $trade->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <p>@lang('Are you sure to approve this trade?')</p>
                        <p>@lang('User will be credited with') <span class="font-weight-bold amount_text"></span> NGN</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark" data-dismiss="modal">@lang('Close')</button>
                        <button type="submit" class="btn btn--success">@lang('Approve')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Reject Modal --}}
    <div id="rejectModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Reject Trade')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.gift.card.trade.trade.reject', $trade->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>@lang('Reason for Rejection')</label>
                            <textarea name="reason" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark" data-dismiss="modal">@lang('Close')</button>
                        <button type="submit" class="btn btn--danger">@lang('Reject')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        (function ($) {
            "use strict";
            $('.approveBtn').on('click', function () {
                var modal = $('#approveModal');
                var data = $(this).data();
                modal.find('.amount_text').text(data.amount);
                modal.modal('show');
            });

            $('.rejectBtn').on('click', function () {
                var modal = $('#rejectModal');
                modal.modal('show');
            });
        })(jQuery);
    </script>
@endpush
