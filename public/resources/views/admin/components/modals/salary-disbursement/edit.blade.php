@if (admin_permission_by_name("admin.salary.disbursement.employee.update"))
    <div id="edit-employee" class="mfp-hide large">
        <div class="modal-data">
            <div class="modal-header px-0">
                <h5 class="modal-title">{{ __("Update Amount") }}</h5>
            </div>
            <div class="modal-form-data">
                <form class="modal-form" method="POST" action="{{ setRoute('admin.salary.disbursement.employee.update') }}">
                    @csrf
                    @method("PUT")
                    <input type="hidden" name="target" value="{{ old('target') }}">
                    <div class="row mb-10-none mt-2">
                        <div class="col-xl-12 col-lg-12 form-group">
                            <label>{{ __("Amount") }}</label>
                            <div class="input-group">
                                <input type="text" class="form--control number-input" name="edit_amount" placeholder="{{ __("Enter Amount") }}">
                                <span class="input-group-text">{{ get_default_currency_code($default_currency) }}</span>
                            </div>
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
            openModalWhenError("edit-employee","#edit-employee");
            $(".edit-modal-button").click(function(){
                var oldData = JSON.parse($(this).parents("tr").attr("data-item"));
                var editModal = $("#edit-employee");

                editModal.find("form").first().find("input[name=target]").val(oldData.id);
                editModal.find("input[name=edit_amount]").val(parseFloat(oldData.amount).toFixed(2));

                openModalBySelector("#edit-employee");
            });

        </script>
    @endpush
@endif