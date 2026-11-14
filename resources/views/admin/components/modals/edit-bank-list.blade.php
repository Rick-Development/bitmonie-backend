@if (admin_permission_by_name("admin.fund-transfer.bank.list.update"))
    <div id="edit-bank-list" class="mfp-hide large">
        <div class="modal-data">
            <div class="modal-header px-0">
                <h5 class="modal-title">{{ __("Edit Bank") }}</h5>
            </div>
            <div class="modal-form-data">
                <form class="modal-form" method="POST" action="{{ setRoute('admin.fund-transfer.bank.list.update') }}">
                    @csrf
                    @method("PUT")
                    @include('admin.components.form.hidden-input',[
                        'name'          => 'target',
                        'value'         => old('target'),
                    ])
                    <div class="row mb-10-none">

                        <div class="col-xl-12 col-lg-12 form-group mt-2">
                            <label>{{ __("Select Bank from SafeHaven") }}</label>
                            <select class="form--control select2-basic" id="safehaven-bank-edit-select" style="width: 100%;">
                                <option value="" selected disabled>{{ __("Search & Select Bank") }}</option>
                                @foreach($safeHavenBanks ?? [] as $shBank)
                                    <option value="{{ $shBank->code }}" data-name="{{ $shBank->name }}">
                                        {{ $shBank->name }} ({{ $shBank->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-xl-12 col-lg-12 form-group mt-2">
                            @include('admin.components.form.input',[
                                'label'         => __("Bank Name").'*',
                                'name'          => "name",
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
                            <button type="submit" class="btn btn--base">{{ __("Update") }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push("script")
        <script>
            $(document).ready(function(){
                openModalWhenError("edit-bank-list","#edit-bank-list");

                $('#safehaven-bank-edit-select').select2({
                    dropdownParent: $('#edit-bank-list')
                });

                $(document).on("click",".edit-modal-button",function(){
                    var oldData = JSON.parse($(this).parents("tr").attr("data-item"));
                    var editModal = $("#edit-bank-list");
                    editModal.find("form").first().find("input[name=target]").val(oldData.id);
                    editModal.find("input[name=name]").val(oldData.name);
                    editModal.find("input[name=bank_code]").val(oldData.bank_code || '');

                    if (oldData.bank_code) {
                        $('#safehaven-bank-edit-select').val(oldData.bank_code).trigger('change.select2');
                    } else {
                        $('#safehaven-bank-edit-select').val('').trigger('change.select2');
                    }

                    openModalBySelector("#edit-bank-list");
                });

                $(document).on('change', '#safehaven-bank-edit-select', function() {
                    var selectedOption = $(this).find('option:selected');
                    var bankName = selectedOption.data('name');
                    var bankCode = $(this).val();
                    if (bankName) {
                        $('#edit-bank-list input[name=name]').val(bankName);
                    }
                    if (bankCode) {
                        $('#edit-bank-list input[name=bank_code]').val(bankCode);
                    }
                });
            });
        </script>
    @endpush
@endif
