@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($pageTitle)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __("Gift Card Trades")])
@endsection

@section('content')
        <div class="col-lg-12">
            <div class="card b-radius--10 ">
                <div class="card-body p-0">
                    <div class="table-responsive--sm table-responsive">
                        <table class="table table--light style--two">
                            <thead>
                                <tr>
                                    <th>@lang('User')</th>
                                    <th>@lang('Reference')</th>
                                    <th>@lang('Card Info')</th>
                                    <th>@lang('Amount')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Date')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($trades as $trade)
                                    <tr>
                                        <td data-label="@lang('User')">
                                            <span class="font-weight-bold">{{ @$trade->user->fullname }}</span>
                                            <br>
                                            <span class="small">
                                            <a href="{{ route('admin.users.details', $trade->user_id) }}"><span>@</span>{{ @$trade->user->username }}</a>
                                            </span>
                                        </td>
                                        <td data-label="@lang('Reference')">
                                            {{ $trade->id }}
                                        </td>
                                        <td data-label="@lang('Card Info')">
                                            <strong>{{ __($trade->country->name) }}</strong>
                                            <br>
                                            {{ __($trade->category->name) }} - {{ __($trade->type->name) }}
                                        </td>
                                        <td data-label="@lang('Amount')">
                                            <div>
                                                {{ get_amount($trade->card_amount) }} {{ __($trade->card_currency) }}
                                            </div>
                                            <strong class="text-success" data-toggle="tooltip" title="@lang('Payable Amount')">
                                                {{ get_amount($trade->ngn_amount) }} NGN
                                            </strong>
                                        </td>
                                        <td data-label="@lang('Status')">
                                            @if($trade->status == 'pending')
                                                <span class="badge badge--warning">@lang('Pending')</span>
                                            @elseif($trade->status == 'approved')
                                                <span class="badge badge--success">@lang('Approved')</span>
                                                <br>{{ $trade->updated_at->diffForHumans() }}
                                            @elseif($trade->status == 'rejected')
                                                <span class="badge badge--danger">@lang('Rejected')</span>
                                                <br>{{ $trade->updated_at->diffForHumans() }}
                                            @endif
                                        </td>
                                        <td data-label="@lang('Date')">
                                            {{ $trade->created_at->format('d-m-Y H:i:s') }} <br> {{ $trade->created_at->diffForHumans() }}
                                        </td>
                                        <td data-label="@lang('Action')">
                                            <a href="{{ route('admin.gift.card.trade.trade.details', $trade->id) }}" class="icon-btn" data-toggle="tooltip" title="" data-original-title="@lang('Details')">
                                                <i class="las la-desktop text--shadow"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage ?? 'No data found') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($trades->hasPages())
                    <div class="card-footer py-4">
                        {{ get_paginate($trades) }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
