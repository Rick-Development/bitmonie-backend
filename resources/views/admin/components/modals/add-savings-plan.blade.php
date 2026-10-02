<div id="add-plan" class="mfp-hide large">
    <div class="modal-data">
        <div class="modal-header px-4 py-3">
            <h5 class="modal-title">{{ __("Add New SafeLock Plan") }}</h5>
        </div>
        <div class="modal-form-data">
            <form class="modal-form" method="POST" action="{{ setRoute('admin.savings.plans.store') }}">
                @csrf
                <div class="row mb-10-none">
                    <div class="col-xl-12 col-lg-12 form-group mt-2">
                        @include('admin.components.form.input',[
                            'label'         => __("Plan Name")."*",
                            'name'          => "name",
                            'value'         => old("name"),
                            'placeholder'   => __("e.g. 30 Days Lock"),
                        ])
                    </div>
                    <div class="col-xl-6 col-lg-6 form-group mt-2">
                        @include('admin.components.form.input',[
                            'label'         => __("Duration (Days)")."*",
                            'name'          => "duration_days",
                            'type'          => "number",
                            'value'         => old("duration_days"),
                            'placeholder'   => __("e.g. 30"),
                        ])
                    </div>
                    <div class="col-xl-6 col-lg-6 form-group mt-2">
                        @include('admin.components.form.input',[
                            'label'         => __("Interest Rate (%)")."*",
                            'name'          => "interest_rate",
                            'type'          => "number",
                            'step'          => "0.01",
                            'value'         => old("interest_rate"),
                            'placeholder'   => __("e.g. 6.00"),
                        ])
                    </div>
                    <div class="col-xl-6 col-lg-6 form-group mt-2">
                        @include('admin.components.form.input',[
                            'label'         => __("Min Amount")."*",
                            'name'          => "min_amount",
                            'type'          => "number",
                            'step'          => "0.01",
                            'value'         => old("min_amount"),
                            'placeholder'   => __("e.g. 1000"),
                        ])
                    </div>
                    <div class="col-xl-6 col-lg-6 form-group mt-2">
                        @include('admin.components.form.input',[
                            'label'         => __("Max Amount (Optional)"),
                            'name'          => "max_amount",
                            'type'          => "number",
                            'step'          => "0.01",
                            'value'         => old("max_amount"),
                            'placeholder'   => __("e.g. 5000000"),
                        ])
                    </div>

                    <div class="col-xl-12 col-lg-12 form-group d-flex align-items-center justify-content-between mt-4">
                        <button type="button" class="btn btn--danger modal-close">{{ __("Cancel") }}</button>
                        <button type="submit" class="btn btn--base">{{ __("Add") }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
