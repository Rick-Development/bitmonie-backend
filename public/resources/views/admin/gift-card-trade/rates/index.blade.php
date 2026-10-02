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
    ], 'active' => __("Gift Card Rates")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("Gift Card Rates") }}</h5>
                <div class="table-btn-area">
                    <button class="btn btn--primary addBtn"><i class="las la-plus"></i> @lang('Add New')</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>@lang('Category')</th>
                            <th>@lang('Type')</th>
                            <th>@lang('Country')</th>
                            <th>@lang('Rate')</th>
                            <th>@lang('Limits')</th>
                            <th>@lang('Action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rates as $rate)
                            <tr>
                                <td data-label="@lang('Category')">{{ __($rate->category->name) }}</td>
                                <td data-label="@lang('Type')">{{ __($rate->type->name) }}</td>
                                <td data-label="@lang('Country')">{{ __($rate->country->name) }}</td>
                                <td data-label="@lang('Rate')">{{ get_amount($rate->rate_per_dollar) }} NGN / 1 {{ __($rate->currency) }}</td>
                                <td data-label="@lang('Limits')">
                                    {{ get_amount($rate->min_amount) }} - {{ get_amount($rate->max_amount) }} {{ __($rate->currency) }}
                                </td>
                                <td data-label="@lang('Action')">
                                    <button class="btn btn-sm btn--primary editBtn" 
                                        data-id="{{ $rate->id }}" 
                                        data-category_id="{{ $rate->category_id }}"
                                        data-type_id="{{ $rate->type_id }}"
                                        data-country_id="{{ $rate->country_id }}"
                                        data-min="{{ $rate->min_amount }}" 
                                        data-max="{{ $rate->max_amount }}" 
                                        data-rate="{{ $rate->rate_per_dollar }}"
                                        data-currency="{{ $rate->currency }}">
                                        <i class="la la-pencil"></i> @lang('Edit')
                                    </button>
                                    
                                    <button class="btn btn-sm btn--danger deleteBtn" 
                                        data-id="{{ $rate->id }}">
                                        <i class="la la-trash"></i> @lang('Delete')
                                    </button>
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
        @if ($rates->hasPages())
            <div class="table-footer">
                {{ get_paginate($rates) }}
            </div>
        @endif
    </div>

    {{-- Add Modal --}}
    <div id="addModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Add Rate')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.gift.card.trade.rate.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>@lang('Category')</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">@lang('Select Category')</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ __($category->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>@lang('Type')</label>
                            <select name="type_id" class="form-control" required>
                                <option value="">@lang('Select Type')</option>
                                @foreach($types as $type)
                                    <option value="{{ $type->id }}">{{ __($type->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>@lang('Country')</label>
                            <select name="country_id" class="form-control" required>
                                <option value="">@lang('Select Country')</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}">{{ __($country->name) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Min Amount')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" name="min_amount" class="form-control" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text currency_text">USD</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Max Amount')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" name="max_amount" class="form-control" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text currency_text">USD</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>@lang('Rate per 1 Unit')</label>
                            <div class="input-group">
                                <input type="number" step="any" name="rate_per_dollar" class="form-control" required>
                                <div class="input-group-append">
                                    <span class="input-group-text">NGN</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>@lang('Currency Code')</label>
                            <input type="text" name="currency" class="form-control" value="USD" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn--primary w-100">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div id="editModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Edit Rate')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                         <div class="form-group">
                            <label>@lang('Category')</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">@lang('Select Category')</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ __($category->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>@lang('Type')</label>
                            <select name="type_id" class="form-control" required>
                                <option value="">@lang('Select Type')</option>
                                @foreach($types as $type)
                                    <option value="{{ $type->id }}">{{ __($type->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>@lang('Country')</label>
                            <select name="country_id" class="form-control" required>
                                <option value="">@lang('Select Country')</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}">{{ __($country->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                         <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Min Amount')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" name="min_amount" class="form-control" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text currency_text">USD</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Max Amount')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" name="max_amount" class="form-control" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text currency_text">USD</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>@lang('Rate per 1 Unit')</label>
                            <div class="input-group">
                                <input type="number" step="any" name="rate_per_dollar" class="form-control" required>
                                <div class="input-group-append">
                                    <span class="input-group-text">NGN</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>@lang('Currency Code')</label>
                            <input type="text" name="currency" class="form-control" value="USD" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn--primary w-100">@lang('Update')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Delete Modal --}}
    <div id="deleteModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Confirmation')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-body">
                        <p>@lang('Are you sure to delete this rate?')</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark" data-dismiss="modal">@lang('Close')</button>
                        <button type="submit" class="btn btn--danger">@lang('Delete')</button>
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
            $('.addBtn').on('click', function () {
                var modal = $('#addModal');
                modal.modal('show');
            });

            $('.editBtn').on('click', function () {
                var modal = $('#editModal');
                var data = $(this).data();
                var action = "{{ route('admin.gift.card.trade.rate.update', ':id') }}";
                action = action.replace(':id', data.id);
                modal.find('form').attr('action', action);
                
                modal.find('select[name=category_id]').val(data.category_id);
                modal.find('select[name=type_id]').val(data.type_id);
                modal.find('select[name=country_id]').val(data.country_id);
                
                modal.find('input[name=min_amount]').val(data.min);
                modal.find('input[name=max_amount]').val(data.max);
                modal.find('input[name=rate_per_dollar]').val(data.rate);
                modal.find('input[name=currency]').val(data.currency);
                $('.currency_text').text(data.currency);
                modal.modal('show');
            });

            $('input[name=currency]').on('input', function() {
                 $('.currency_text').text($(this).val());
            });

            $('.deleteBtn').on('click', function () {
                var modal = $('#deleteModal');
                var data = $(this).data();
                var action = "{{ route('admin.gift.card.trade.rate.delete', ':id') }}";
                action = action.replace(':id', data.id);
                modal.find('form').attr('action', action);
                modal.modal('show');
            });
        })(jQuery);
    </script>
@endpush
