<table class="custom-table bank-search-table">
    <thead>
        <tr>

            <th>{{ __('Mobile Bank Name') }}</th>
            <th>{{ __('Status') }}</th>
            <th></th>
        </tr>
    </thead>
    <tbody>

        @forelse ($mobile_banks ?? [] as $item)
            <tr data-item="{{ $item->editData }}">
                <td>{{ $item->name }}</td>
                <td>
                    @include('admin.components.form.switcher',[
                        'name'          => 'bank_status',
                        'value'         => $item->status,
                        'options'       => [__('Enable') => 1,__('Disable') => 0],
                        'onload'        => true,
                        'data_target'   => $item->id,
                        'permission'    => "admin.fund-transfer.mobile.bank.status.update",
                    ])
                </td>
                <td>
                    @include('admin.components.link.edit-default',[
                        'href'          => "javascript:void(0)",
                        'class'         => "edit-modal-button",
                        'permission'    => "admin.fund-transfer.mobile.bank.update",
                    ])

                    @include('admin.components.link.delete-default',[
                        'href'          => "javascript:void(0)",
                        'class'         => "delete-modal-button",
                        'permission'    => "admin.fund-transfer.mobile.bank.delete",
                    ])

                </td>
            </tr>
        @empty
            @include('admin.components.alerts.empty',['colspan' => 3])
        @endforelse
    </tbody>
</table>

@push("script")
    <script>
        $(document).ready(function(){
            switcherAjax("{{ setRoute('admin.fund-transfer.mobile.bank.status.update') }}");
        })
    </script>
@endpush
