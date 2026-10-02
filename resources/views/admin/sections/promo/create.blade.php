@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title', ['title' => __('Create Promo')])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb', [
        'breadcrumbs' => [
            [
                'name' => __('Dashboard'),
                'url' => setRoute('admin.dashboard'),
            ],
            [
                'name' => __('Promos'),
                'url' => setRoute('admin.promo.index'),
            ],
        ],
        'active' => __('Create Promo'),
    ])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">

            <div class="table-header">
                <div class="title">
                    <h4 class="title">{{ __('Create Promo') }}</h4>
                </div>
            </div>

            <form action="{{ setRoute('admin.promo.store') }}" method="POST">
                @csrf

                <div class="row">

                    {{-- Promo Name --}}
                    <div class="col-xl-6 col-lg-6 form-group mb-20">
                        <label class="form--label">{{ __('Promo Name') }} <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            name="name"
                            class="form--control"
                            value="{{ old('name') }}"
                            placeholder="{{ __('Enter promo name') }}"
                            required
                        >
                        @error('name')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Expiry Payment Percentage --}}
                    <div class="col-xl-6 col-lg-6 form-group mb-20">
                        <label class="form--label">{{ __('Expiry Payment Percentage') }}</label>
                        <input
                            type="number"
                            name="expiry_payment_percentage"
                            class="form--control"
                            value="{{ old('expiry_payment_percentage') }}"
                            min="0"
                            max="100"
                            step="0.01"
                            placeholder="{{ __('e.g. 50') }}"
                        >
                        @error('expiry_payment_percentage')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Start Date --}}
                    <div class="col-xl-6 col-lg-6 form-group mb-20">
                        <label class="form--label">{{ __('Start Date') }} <span class="text-danger">*</span></label>
                        <input
                            type="datetime-local"
                            name="start_date"
                            class="form--control"
                            value="{{ old('start_date') }}"
                            required
                        >
                        @error('start_date')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- End Date --}}
                    <div class="col-xl-6 col-lg-6 form-group mb-20">
                        <label class="form--label">{{ __('End Date') }} <span class="text-danger">*</span></label>
                        <input
                            type="datetime-local"
                            name="end_date"
                            class="form--control"
                            value="{{ old('end_date') }}"
                            required
                        >
                        @error('end_date')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Features --}}
                    <div class="col-xl-12 form-group mb-20">
                        <label class="form--label">{{ __('Features') }} <span class="text-danger">*</span></label>
                        <select
                            name="features_id[]"
                            class="form--control select2-auto-tokenize"
                            multiple
                            required
                        >
                            @foreach($features as $feature)
                                <option
                                    value="{{ $feature->id }}"
                                    {{ in_array($feature->id, old('features_id', [])) ? 'selected' : '' }}
                                >
                                    {{ $feature->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted d-block mt-1">
                            {{ __('Select the features this promo will apply to.') }}
                        </small>
                        @error('features_id')
                            <span class="text-danger d-block">{{ $message }}</span>
                        @enderror
                        @error('features_id.*')
                            <span class="text-danger d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Description --}}
                    <div class="col-xl-12 form-group mb-20">
                        <label class="form--label">{{ __('Description') }}</label>
                        <textarea
                            name="description"
                            class="form--control"
                            rows="5"
                            placeholder="{{ __('Enter promo description') }}"
                        >{{ old('description') }}</textarea>
                        @error('description')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Buttons --}}
                    <div class="col-xl-12">
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn--base">
                                <i class="las la-save"></i>
                                {{ __('Create Promo') }}
                            </button>

                            <a href="{{ setRoute('admin.promo.index') }}" class="btn btn--danger">
                                <i class="las la-times"></i>
                                {{ __('Cancel') }}
                            </a>
                        </div>
                    </div>

                </div>
            </form>

        </div>
    </div>
@endsection