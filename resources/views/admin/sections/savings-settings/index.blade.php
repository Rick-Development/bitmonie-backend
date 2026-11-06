@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title', ['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb', [
        'breadcrumbs' => [
            ['name' => __('Dashboard'), 'url' => setRoute('admin.dashboard')],
        ],
        'active' => __('Savings Settings'),
    ])
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-10 col-lg-11">
            <div class="alert alert-info mb-3">
                <strong>{{ __('Note') }}:</strong>
                {{ __('These settings power Flexi, Auto Save, Target, EduSave, and global SafeLock knobs on the mobile app. SafeLock tenure rates (30/60/90 days) are managed under “SafeLock Plans”. Rate changes apply to new actions; existing SafeLocks keep the rate snapshotted at create.') }}
            </div>

            <form action="{{ setRoute('admin.savings.settings.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="card mb-3">
                    <div class="card-header"><h5 class="title mb-0">{{ __('Shared Limits') }}</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>{{ __('Min Transaction (NGN)') }}*</label>
                                <input type="number" step="0.01" name="min_transaction" class="form--control" value="{{ old('min_transaction', $settings->min_transaction) }}" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{ __('Max Transaction (NGN)') }}*</label>
                                <input type="number" step="0.01" name="max_transaction" class="form--control" value="{{ old('max_transaction', $settings->max_transaction) }}" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{ __('Max Target Amount (NGN)') }}*</label>
                                <input type="number" step="0.01" name="max_target_amount" class="form--control" value="{{ old('max_target_amount', $settings->max_target_amount) }}" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>{{ __('Max Active Targets') }}*</label>
                                <input type="number" name="max_active_targets" class="form--control" value="{{ old('max_active_targets', $settings->max_active_targets) }}" required min="1">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>{{ __('Max Auto Save Plans') }}*</label>
                                <input type="number" name="max_auto_plans" class="form--control" value="{{ old('max_auto_plans', $settings->max_auto_plans) }}" required min="1">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5 class="title mb-0">{{ __('Flexi Save') }}</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>{{ __('Product Enabled') }}*</label>
                                <select name="flexi_enabled" class="form--control">
                                    <option value="1" {{ old('flexi_enabled', $settings->flexi_enabled) ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                    <option value="0" {{ !old('flexi_enabled', $settings->flexi_enabled) ? 'selected' : '' }}>{{ __('No') }}</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{ __('Daily Interest Enabled') }}*</label>
                                <select name="flexi_interest_enabled" class="form--control">
                                    <option value="1" {{ old('flexi_interest_enabled', $settings->flexi_interest_enabled) ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                    <option value="0" {{ !old('flexi_interest_enabled', $settings->flexi_interest_enabled) ? 'selected' : '' }}>{{ __('No') }}</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{ __('Interest APR (%)') }}*</label>
                                <input type="number" step="0.01" name="flexi_interest_apr_percent" class="form--control" value="{{ old('flexi_interest_apr_percent', $settings->flexi_interest_apr_percent) }}" required>
                                <small class="text--info">{{ __('Used for daily Flexi, Auto Save, and Target accrual (APR/365). e.g. 60 ≈ 5% monthly. SafeLock uses plan tenure rates.') }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5 class="title mb-0">{{ __('Auto Save') }}</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>{{ __('Product Enabled') }}*</label>
                                <select name="auto_enabled" class="form--control">
                                    <option value="1" {{ old('auto_enabled', $settings->auto_enabled) ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                    <option value="0" {{ !old('auto_enabled', $settings->auto_enabled) ? 'selected' : '' }}>{{ __('No') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5 class="title mb-0">{{ __('Target Savings') }}</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>{{ __('Product Enabled') }}*</label>
                                <select name="target_enabled" class="form--control">
                                    <option value="1" {{ old('target_enabled', $settings->target_enabled) ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                    <option value="0" {{ !old('target_enabled', $settings->target_enabled) ? 'selected' : '' }}>{{ __('No') }}</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{ __('Early Closure Fee (%)') }}*</label>
                                <input type="number" step="0.01" name="target_early_closure_fee_percent" class="form--control" value="{{ old('target_early_closure_fee_percent', $settings->target_early_closure_fee_percent) }}" required min="0" max="100">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5 class="title mb-0">{{ __('SafeLock (global)') }}</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>{{ __('Product Enabled') }}*</label>
                                <select name="safelock_enabled" class="form--control">
                                    <option value="1" {{ old('safelock_enabled', $settings->safelock_enabled) ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                    <option value="0" {{ !old('safelock_enabled', $settings->safelock_enabled) ? 'selected' : '' }}>{{ __('No') }}</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{ __('Early Break Penalty (%)') }}*</label>
                                <input type="number" step="0.01" name="safelock_early_break_penalty_percent" class="form--control" value="{{ old('safelock_early_break_penalty_percent', $settings->safelock_early_break_penalty_percent) }}" required min="0" max="100">
                                <small class="text--info">{{ __('Was hardcoded at 1%. Tenure interest rates live under SafeLock Plans.') }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5 class="title mb-0">{{ __('EduSave') }}</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>{{ __('Product Enabled') }}*</label>
                                <select name="edusave_enabled" class="form--control">
                                    <option value="1" {{ old('edusave_enabled', $settings->edusave_enabled) ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                    <option value="0" {{ !old('edusave_enabled', $settings->edusave_enabled) ? 'selected' : '' }}>{{ __('No') }}</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{ __('Minimum Amount (NGN)') }}*</label>
                                <input type="number" step="0.01" name="edusave_min_amount" class="form--control" value="{{ old('edusave_min_amount', $settings->edusave_min_amount) }}" required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{ __('Termly Period (months)') }}*</label>
                                <input type="number" name="edusave_termly_months" class="form--control" value="{{ old('edusave_termly_months', $settings->edusave_termly_months) }}" required min="1" max="24">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn--base w-100 mb-4">{{ __('Update Savings Settings') }}</button>
            </form>
        </div>
    </div>
@endsection
