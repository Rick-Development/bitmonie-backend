@isset($transactions)
    @forelse ($transactions ?? [] as $item)
        @if ($item->type == payment_gateway_const()::TYPEADDMONEY)
            @include('user.components.transaction.add-money',[
                'transaction'   => $item,
            ])
        @elseif ($item->type == payment_gateway_const()::TYPEMONEYOUT)
          @include('user.components.transaction.money-out',[
              'transaction'   => $item,
          ])
        @elseif ($item->type == payment_gateway_const()::TYPEADDSUBTRACTBALANCE)
            @include('user.components.transaction.balance-update',[
                'transaction'   => $item,
            ])
        @elseif ($item->type == payment_gateway_const()::TYPE_OWN_BANK_TRANSFER)
            @include('user.components.transaction.own-bank-transfer',[
                'transaction'   => $item,
            ])
        @elseif ($item->type == payment_gateway_const()::TYPE_OTHER_BANK_TRANSFER)
            @include('user.components.transaction.other-bank-transfer',[
                'transaction'   => $item,
            ])
        @elseif ($item->type == payment_gateway_const()::TYPEVIRTUALCARD)
            @include('user.components.transaction.virtual-card-log',[
                'transaction'   => $item,
            ])
        @elseif ($item->type == payment_gateway_const()::SALARYDISBURSEMENT)
            @include('user.components.transaction.salary-disbursement',[
                'transaction'   => $item,
            ])
        @endif
    @empty
    <div class="alert alert-primary text-center">
        {{ __("No Record Found!") }}
    </div>
    @endforelse
@endisset

