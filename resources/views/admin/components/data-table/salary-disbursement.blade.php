<table class="custom-table company-search-table">
    <thead>
        <tr>
            <th></th>
            <th>{{ __('Company Name') }}</th>
            <th>{{ __('Email') }}</th>
            <th>{{ __('Username') }}</th>
            <th>{{ __('Amount') }}</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($companies ?? [] as $item)
            <tr>
                <td>
                    <ul class="user-list">
                        <li><img src="{{ get_image($item->image ?? '','user-profile') }}" alt="user"></li>
                    </ul>
                </td>
                <td><span>{{ $item->company_name ?? '' }}</span></td>
                <td>{{ $item->email ?? '' }}</td>
                <td>{{ $item->username ?? '' }}</td>
                <td>{{ get_amount($item->wallet->balance,get_default_currency_code()) }}</td>
                <td>
                    @include('admin.components.link.info-default',[
                        'href'          => setRoute('admin.salary.disbursement.details', $item->username),
                        'permission'    => "admin.salary.disbursement.details",
                    ])
                </td>
            </tr>
        @empty
            @include('admin.components.alerts.empty',['colspan' => 6])
        @endforelse
    </tbody>
</table>
