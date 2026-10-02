@extends('admin.layouts.master')

@section('page-title')
@include('admin.components.page-title', ['title' => __('Edit Promo')])
@endsection

@section('breadcrumb')
@include('admin.components.breadcrumb', [
'breadcrumbs' => [
['name' => __('Dashboard'), 'url' => setRoute('admin.dashboard')],
['name' => __('Promos'), 'url' => route('admin.promo.index')],
['name' => $promo->name, 'url' => route('admin.promo.show', $promo->id)],
],
'active' => __('Edit Promo'),
])
@endsection

@section('content') <div class="table-area"> <div class="table-wrapper">

```
        <div class="table-header">
            <div class="title">
                <h4 class="title">{{ __('Edit Promo') }}</h4>
            </div>
        </div>

        <form action="{{ route('admin.promo.update', $promo->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row">

                <div class="col-xl-6 col-lg-6 mb-20">
                    <label class="form--label">
                        {{ __('Promo Name') }}
                    </label>

                    <input
                        type="text"
                        name="name"
                        class="form--control"
                        value="{{ old('name', $promo->name) }}"
                        placeholder="{{ __('Enter promo name') }}"
                        required
                    >

                    @error('name')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="col-xl-6 col-lg-6 mb-20">
                    <label class="form--label">
                        {{ __('Expiry Payment Percentage') }}
                    </label>

                    <input
                        type="number"
                        name="expiry_payment_percentage"
                        class="form--control"
                        value="{{ old('expiry_payment_percentage', $promo->expiry_payment_percentage) }}"
                        min="0"
                        max="100"
                        step="0.01"
                        placeholder="{{ __('e.g. 50') }}"
                    >

                    @error('expiry_payment_percentage')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="col-xl-6 col-lg-6 mb-20">
                    <label class="form--label">
                        {{ __('Start Date') }}
                    </label>

                    <input
                        type="datetime-local"
                        name="start_date"
                        class="form--control"
                        value="{{ old('start_date', $promo->start_date?->format('Y-m-d\TH:i')) }}"
                        required
                    >

                    @error('start_date')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="col-xl-6 col-lg-6 mb-20">
                    <label class="form--label">
                        {{ __('End Date') }}
                    </label>

                    <input
                        type="datetime-local"
                        name="end_date"
                        class="form--control"
                        value="{{ old('end_date', $promo->end_date?->format('Y-m-d\TH:i')) }}"
                        required
                    >

                    @error('end_date')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="col-xl-12 mb-20">
                    <label class="form--label">
                        {{ __('Features') }}
                    </label>

                    @php
                        $selectedFeatures = old(
                            'features_id',
                            $promo->features->pluck('id')->toArray()
                        );
                    @endphp

                    <select
                        name="features_id[]"
                        class="form--control"
                        multiple
                        required
                    >
                        @foreach($features as $feature)
                            <option
                                value="{{ $feature->id }}"
                                {{ in_array($feature->id, $selectedFeatures) ? 'selected' : '' }}
                            >
                                {{ $feature->name }}
                            </option>
                        @endforeach
                    </select>

                    <small class="text-muted">
                        {{ __('Select the features this promo will apply to.') }}
                    </small>

                    @error('features_id')
                        <span class="text-danger d-block">{{ $message }}</span>
                    @enderror

                    @error('features_id.*')
                        <span class="text-danger d-block">{{ $message }}</span>
                    @enderror
                </div>

                <div class="col-xl-12 mb-20">
                    <label class="form--label">
                        {{ __('Description') }}
                    </label>

                    <textarea
                        name="description"
                        class="form--control"
                        rows="5"
                        placeholder="{{ __('Enter promo description') }}"
                    >{{ old('description', $promo->description) }}</textarea>

                    @error('description')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="col-xl-12">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn--base">
                            <i class="las la-save"></i>
                            {{ __('Update Promo') }}
                        </button>

                        <a
                            href="{{ route('admin.promo.show', $promo->id) }}"
                            class="btn btn--danger"
                        >
                            {{ __('Cancel') }}
                        </a>
                    </div>
                </div>

            </div>
        </form>

    </div>
</div>
```

@endsection
