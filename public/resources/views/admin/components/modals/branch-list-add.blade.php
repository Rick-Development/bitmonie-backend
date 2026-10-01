@if (admin_permission_by_name("admin.fund-transfer.bank.branch.store"))
    <div id="bank-branch-add" class="mfp-hide large">
        <div class="modal-data">
            <div class="modal-header px-0">
                <h5 class="modal-title">{{ __("Add New Branch") }}</h5>
            </div>
            <div class="modal-form-data">
                <form class="modal-form" method="POST" action="{{ setRoute('admin.fund-transfer.bank.branch.store') }}">
                    @csrf
                    <div class="row mb-10-none">

                        <div class="col-xl-12 col-lg-12 form-group">
                            <select class="form--control select2-auto-tokenize" name="bank" data-old="{{ old("bank") }}" required>
                                @foreach ($banks as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-xl-12 col-lg-12 form-group mt-2">
                            @include('admin.components.form.input',[
                                'label'         => __("Branch Name").'*',
                                'name'          => "name",
                                'required'      => 'required',
                                'value'         => old("name"),
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
            openModalWhenError("bank-branch-add","#bank-branch-add");
        </script>
    @endpush
@endif
