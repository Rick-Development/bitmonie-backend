<table class="custom-table branch-search-table">
    <thead>
        <tr>

            <th>{{ __('Branch Name') }}</th>
            <th>{{ __('Bank Name') }}</th>
            <th>{{ __('Status') }}</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($branches ?? [] as $item)
            <tr data-item="{{ $item->editData }}">
                <td>{{ $item->name }}</td>
                <td>{{ $item->bank->name }}</td>
                <td>
                    @include('admin.components.form.switcher',[
                        'name'          => 'bank_status',
                        'value'         => $item->status,
                        'options'       => [__('Enable') => 1,__('Disable') => 0],
                        'onload'        => true,
                        'data_target'   => $item->id,
                        'permission'    => "admin.remitance.bank.deposit.status.update",
                    ])
                </td>
                <td>
                    @include('admin.components.link.edit-default',[
                        'href'          => "javascript:void(0)",
                        'class'         => "edit-modal-button",
                        'permission'    => "admin.remitance.bank.deposit.update",
                    ])

                    @include('admin.components.link.delete-default',[
                        'href'          => "javascript:void(0)",
                        'class'         => "delete-modal-button",
                        'permission'    => "admin.remitance.bank.deposit.delete",
                    ])

                </td>
            </tr>
        @empty
            @include('admin.components.alerts.empty',['colspan' => 47])
        @endforelse
    </tbody>
</table>

@push("script")
    <script>
        $(document).ready(function(){
            // Switcher
            switcherAjax("{{ setRoute('admin.fund-transfer.bank.branch.status.update') }}");
        })
    </script>
@endpush
