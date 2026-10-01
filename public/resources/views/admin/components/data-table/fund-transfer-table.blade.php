<table class="custom-table transaction-search-table">
    <thead>
        <tr>
            <th></th>
            <th>{{__('Transaction ID')}}</th>
            <th>{{__('Transaction Type')}}</th>
            <th>{{__('Account No')}}</th>
            <th>{{__('Email')}}</th>
            <th>{{ __('Amount') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Time') }}</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($logs  as $key => $item)
            <tr>
                <td>
                    <ul class="user-list">
                        <li><img src="{{ get_image($item->user->image,'user-profile') }}" alt="user"></li>
                    </ul>
                </td>
                <td>{{ $item->trx_id }}</td>
                <td>{{ $item->type }}</td>
                <td>{{ $item->creator->account_no }}</td>
                <td>{{ $item->creator->email }}</td>
                <td>{{ get_amount($item->request_amount,$item->request_currency) }}</td>
                <td>
                    <span class="{{ $item->string_status->class }}">{{ $item->string_status->value }}</span>
                </td>
                <td>{{ $item->created_at->format('d-m-y h:i:s A') }}</td>
                <td>
                    @if ($item->status == payment_gateway_const()::STATUSSUCCESS)
                        <button type="button" class="btn btn--base bg--success"><i class="las la-check-circle"></i></button>
                    @endif

                    @if ($item->status == payment_gateway_const()::STATUSREJECTED)
                        <button type="button" class="btn btn--base bg--danger"><i class="las la-times-circle"></i></button>
                    @endif

                    @if ($item->type == payment_gateway_const()::TYPE_OWN_BANK_TRANSFER)
                        <a href="{{ setRoute('admin.fund.transfer.log.own.details',$item->trx_id) }}" class="btn btn--base"><i class="las la-expand"></i></a>
                    @else
                        <a href="{{ route('admin.fund.transfer.log.other.details',$item->trx_id) }}" class="btn btn--base"><i class="las la-expand"></i></a>
                    @endif

                </td>
            </tr>
        @empty
            @include('admin.components.alerts.empty',['colspan' => 11])
        @endforelse
    </tbody>
</table>
