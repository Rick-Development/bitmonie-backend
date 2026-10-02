<table class="custom-table transaction-search-table">
    <thead>
        <tr>
            <th></th>
            <th>{{__('Transaction ID')}}</th>
            <th>{{__('Full Name')}}</th>
            <th>{{ __('Email') }}</th>
            <th>{{ __('Username') }}</th>
            <th>{{ __('Phone') }}</th>
            <th>{{ __('Amount') }}</th>
            <th>{{ __('Gateway') }}</th>
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
                        <li><img src="{{ get_image($item->creator->image,'user-profile') }}" alt="user"></li>
                    </ul>
                </td>
                <td>{{ $item->trx_id }}</td>
                <td>{{ $item->creator->full_name }}</td>
                <td>{{ $item->creator->email }}</td>
                <td>{{ $item->creator->username }}</td>
                <td>{{ $item->creator->full_mobile ?? '' }}</td>
                <td>{{ get_amount($item->request_amount,$item->request_currency) }}</td>
                <td><span class="text--info">{{ $item->gateway_currency->gateway->name }}</span></td>
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

                    <a href="{{ setRoute('admin.add.money.details',$item->trx_id) }}" class="btn btn--base"><i class="las la-expand"></i></a>
                </td>
            </tr>
        @empty
            @include('admin.components.alerts.empty',['colspan' => 11])
        @endforelse
    </tbody>
</table>
