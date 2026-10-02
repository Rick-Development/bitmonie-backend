@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        ['name' => __("Dashboard"), 'url' => setRoute("admin.dashboard")],
        ['name' => __("Saved Beneficiaries"), 'url' => route('admin.beneficiaries.index')]
    ], 'active' => __("Beneficiary Details")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __('Beneficiary Details') }}</h5>
            </div>

            <form method="POST" action="{{ route('admin.beneficiaries.update', $beneficiary->id) }}">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-lg-6 mb-3">
                        <label>{{ __('Beneficiary Name') }}</label>
                        <input class="form--control" name="beneficiary_name" value="{{ old('beneficiary_name', $beneficiary->beneficiary_name ?? data_get($beneficiary->info, 'account_holder_name')) }}">
                    </div>
                    <div class="col-lg-6 mb-3">
                        <label>{{ __('Transaction Type') }}</label>
                        <select class="form--control" name="transaction_type">
                            @foreach($types as $type)
                                <option value="{{ $type }}" @selected(old('transaction_type', $beneficiary->transaction_type) === $type)>{{ Str::headline($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-12 mb-3">
                        <label>{{ __('Details JSON') }}</label>
                        <textarea class="form--control" name="details" rows="8">{{ old('details', json_encode($beneficiary->details ?? data_get($beneficiary->info, 'details', []), JSON_PRETTY_PRINT)) }}</textarea>
                    </div>
                    <div class="col-lg-12 mb-3">
                        <label>
                            <input type="checkbox" name="is_favorite" value="1" @checked(old('is_favorite', $beneficiary->is_favorite))>
                            {{ __('Pinned / Favorite') }}
                        </label>
                    </div>
                </div>
                <button class="btn--base">{{ __('Save Changes') }}</button>
            </form>

            <form method="POST" action="{{ route('admin.beneficiaries.destroy', $beneficiary->id) }}" class="mt-3">
                @csrf
                @method('DELETE')
                <button class="btn--danger" onclick="return confirm('{{ __('Delete this beneficiary?') }}')">{{ __('Delete') }}</button>
            </form>
        </div>
    </div>
@endsection
