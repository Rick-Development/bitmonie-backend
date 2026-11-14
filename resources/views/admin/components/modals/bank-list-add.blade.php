@if (admin_permission_by_name("admin.fund-transfer.bank.list.store"))
    <div id="bank-list-add" class="mfp-hide large">
        <div class="modal-data">
            <div class="modal-header px-0">
                <h5 class="modal-title">{{ __("Add New Bank") }}</h5>
            </div>
            <div class="modal-form-data">
                <form class="modal-form" method="POST" action="{{ setRoute('admin.fund-transfer.bank.list.store') }}">
                    @csrf
                    <div class="row mb-10-none">

                        <div class="col-xl-12 col-lg-12 form-group mt-2">
                            <label>{{ __("Select Bank from SafeHaven") }}*</label>
                            <select class="form--control select2-basic" id="safehaven-bank-add-select" style="width: 100%;">
                                <option value="" selected disabled>{{ __("Search & Select Bank") }}</option>
                                @foreach($safeHavenBanks ?? [] as $shBank)
                                    <option value="{{ $shBank->code }}" data-name="{{ $shBank->name }}" {{ old('bank_code') == $shBank->code ? 'selected' : '' }}>
                                        {{ $shBank->name }} ({{ $shBank->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-xl-12 col-lg-12 form-group mt-2">
                            @include('admin.components.form.input',[
                                'label'         => __("Bank Name").'*',
                                'name'          => "name",
                                'required'      => 'required',
                                'value'         => old("name"),
                                'placeholder'   => __("Auto-filled on bank selection (or enter custom)"),
                            ])
                        </div>

                        <div class="col-xl-12 col-lg-12 form-group mt-2">
                            @include('admin.components.form.input',[
                                'label'         => __("Bank Code").'*',
                                'name'          => "bank_code",
                                'value'         => old("bank_code"),
                                'placeholder'   => __("Auto-filled on bank selection (or enter custom)"),
                                'attribute'     => "readonly",
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

    @push('script')
        <script>
            openModalWhenError("bank-list-add","#bank-list-add");
            $(document).ready(function() {
                $('#safehaven-bank-add-select').select2({
                    dropdownParent: $('#bank-list-add')
                });

                $(document).on('change', '#safehaven-bank-add-select', function() {
                    var selectedOption = $(this).find('option:selected');
                    var bankName = selectedOption.data('name');
                    var bankCode = $(this).val();
                    if (bankName) {
                        $('#bank-list-add input[name=name]').val(bankName);
                    }
                    if (bankCode) {
                        $('#bank-list-add input[name=bank_code]').val(bankCode);
                    }
                });
            });
        </script>
    @endpush
@endif
