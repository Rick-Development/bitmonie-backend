@if (admin_permission_by_name("admin.fund-transfer.bank.branch.update"))
    <div id="edit-branch" class="mfp-hide large">
        <div class="modal-data">
            <div class="modal-header px-0">
                <h5 class="modal-title">{{ __("Edit Branch") }}</h5>
            </div>
            <div class="modal-form-data">
                <form class="modal-form" method="POST" action="{{ setRoute('admin.fund-transfer.bank.branch.update') }}">
                    @csrf
                    @method("PUT")
                    @include('admin.components.form.hidden-input',[
                        'name'          => 'target',
                        'value'         => old('target'),
                    ])
                    <div class="row mb-10-none">

                        <div class="col-xl-12 col-lg-12 form-group bank-list" data-banks="{{ json_encode($banks) }}">
                            <label>{{ __("Select Bank") }}*</label>
                        </div>

                        <div class="col-xl-12 col-lg-12 form-group mt-2">
                            @include('admin.components.form.input',[
                                'label'         => __("Branch Name").'*',
                                'name'          => "name",
                                'value'         => old("name"),
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
                openModalWhenError("edit-branch","#edit-branch");
                $(document).on("click",".edit-modal-button",function(){
                    var oldData = JSON.parse($(this).parents("tr").attr("data-item"));
                    var editModal = $("#edit-branch");
                    editModal.find("form").first().find("input[name=target]").val(oldData.id);
                    editModal.find("input[name=name]").val(oldData.name)
                    editModal.find("select[name=bank]").val(oldData.bank_list_id)

                    var banks = editModal.find(".bank-list").attr("data-banks");
                    banks = JSON.parse(banks);

                    var options = "";
                    $.each(banks,function(index,item) {
                        if(item.id == oldData.bank_list_id) {
                            options += `<option value="${item.id}" selected>${item.name}</option>`;
                        }else {
                            options += `<option value="${item.id}">${item.name}</option>`;
                        }
                    });

                    var bankSelect = ` <select class="form--control bank-list select2-auto-tokenize" name="bank" data-old="{{ old("bank") }}" data-banks="{{ json_encode($banks) }}" required>
                                        ${options}
                                    </select>`;

                    editModal.find(".bank-list").append(bankSelect);

                    editModal.find(".bank-list select").select2();

                    openModalBySelector("#edit-branch");
                });
            });
        </script>
    @endpush
@endif
