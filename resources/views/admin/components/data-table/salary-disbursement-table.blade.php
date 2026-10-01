<table class="custom-table transaction-search-table">
    <thead>
        <tr>
            <th></th>
            <th>{{ __('Transaction ID') }}</th>
            <th>{{ __('Company Name') }}</th>
            <th>{{ __('Email') }}</th>
            <th>{{ __('Amount') }}</th>
            <th>{{ __('Status') }}</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($transactions as $item)
            <tr>
                <td>
                    <ul class="user-list">
                        <li><img src="{{ get_image($item->user->image,'user-profile') }}" alt="user"></li>
                    </ul>
                </td>
                <td>{{ $item->trx_id }}</td>
                <td><span>{{ $item->user->company_name }}</span></td>
                <td>{{ $item->user->email }}</td>
                <td>{{ get_amount($item->request_amount,$item->request_currency) }}</td>
                <td><span class="{{ $item->string_status->class }}">{{ $item->string_status->value }}</span></td>
                <td>
                    <a href="{{ setRoute('admin.salary.disbursement.logs.details',$item->salary_disbursement_id) }}" class="btn btn--base"><i class="las la-expand"></i></a>
                </td>
            </tr>
        @empty
            @include('admin.components.alerts.empty',['colspan' => 11])
        @endforelse
    </tbody>
</table>
