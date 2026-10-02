@extends('user.layouts.master')

@section('content')
<div class="dashboard-card-area pt-3">
    @php
        $default_currency = get_default_currency_code();
    @endphp
    <div class="row mb-20-none">
        <div class="col-xxl-3 col-xl-4 col-lg-4 col-md-4 col-sm-6 mb-20">
            <div class="dasboard-card-item bg-overlay  bg_img" data-background="assets/images/element/card-bg.webp">
                <div class="card-title">
                    <span class="title">{{ __('Account Balance') }}</span>
                    <h4 class="sub-title text--base">{{ get_amount($wallet->balance, $default_currency) }}</h4>
                </div>
                <div class="card-icon">
                    <i class="las la-money-check-alt"></i>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-4 col-lg-4 col-md-4 col-sm-6 mb-20">
            <div class="dasboard-card-item bg-overlay  bg_img" data-background="assets/images/element/card-bg.webp">
                <div class="card-title">
                    <span class="title">{{ __('Total Add Money') }}</span>
                    <h4 class="sub-title text--base">{{ get_amount($total_add_money, $default_currency) }}</h4>
                </div>
                <div class="card-icon">
                    <i class="las la-money-bill"></i>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-4 col-lg-4 col-md-4 col-sm-6 mb-20">
            <div class="dasboard-card-item bg-overlay  bg_img" data-background="assets/images/element/card-bg.webp">
                <div class="card-title">
                    <span class="title">{{ __('Total Money Out') }}</span>
                    <h4 class="sub-title text--base">{{ get_amount($total_money_out, $default_currency) }}</h4>
                </div>
                <div class="card-icon">
                    <i class="las la-money-bill-wave"></i>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-4 col-lg-4 col-md-4 col-sm-6 mb-20">
            <div class="dasboard-card-item bg-overlay  bg_img" data-background="assets/images/element/card-bg.webp">
                <div class="card-title">
                    <span class="title">{{ __('Total Fund Transfer') }}</span>
                    <h4 class="sub-title text--base">{{ get_amount($fund_transfer, $default_currency) }}</h4>
                </div>
                <div class="card-icon">
                    <i class="lab la-cc-amazon-pay"></i>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-4 col-lg-4 col-md-4 col-sm-6 mb-20">
            <div class="dasboard-card-item bg-overlay  bg_img" data-background="assets/images/element/card-bg.webp">
                <div class="card-title">
                    <span class="title">{{ __('Total Fund Received') }}</span>
                    <h4 class="sub-title text--base">{{ get_amount($fund_received, $default_currency) }}</h4>
                </div>
                <div class="card-icon">
                    <i class="las la-coins"></i>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-4 col-lg-4 col-md-4 col-sm-6 mb-20">
            <div class="dasboard-card-item bg-overlay  bg_img" data-background="assets/images/element/card-bg.webp">
                <div class="card-title">
                    <span class="title">{{ __('Transactions') }}</span>
                    <h4 class="sub-title text--base">{{ $transaction_count }}</h4>
                </div>
                <div class="card-icon">
                    <i class="las la-exchange-alt"></i>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-4 col-lg-4 col-md-4 col-sm-6 mb-20">
            <div class="dasboard-card-item bg-overlay  bg_img" data-background="assets/images/element/card-bg.webp">
                <div class="card-title">
                    <span class="title">{{ __('Complete Transaction') }}</span>
                    <h4 class="sub-title text--base">{{ $complete_transaction_count }}</h4>
                </div>
                <div class="card-icon">
                    <i class="las la-handshake"></i>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-xl-4 col-lg-4 col-md-4 col-sm-6 mb-20">
            <div class="dasboard-card-item bg-overlay  bg_img" data-background="assets/images/element/card-bg.webp">
                <div class="card-title">
                    <span class="title">{{ __('Pending Transaction') }}</span>
                    <h4 class="sub-title text--base">{{ $pending_transaction_count }}</h4>
                </div>
                <div class="card-icon">
                    <i class="las fa-clock"></i>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="dashboard-chart">
    <div class="dashboard-header-wrapper">
        <h4 class="title">{{ __("Transactions Overview") }}</h4>
    </div>
    <div class="chart-container">
        <div id="chart" data-transaction_chart="{{ json_encode($transaction_chart) }}" class="chart"></div>
    </div>
</div>
<div class="dashboard-trx-area mt-60">
    <div class="dashboard-header-wrapper">
        <h4 class="title">{{ __('Latest Transactions') }}</h4>
        <div class="dashboard-btn-wrapper">
            <div class="dashboard-btn">
                <a href="{{ setRoute('user.transactions.index') }}" class="btn--base">{{ __('View More') }}</a>
            </div>
        </div>
    </div>
    <div class="dashboard-list-wrapper">
        <div class="item-wrapper transactions-search">
            @include('user.components.transaction.log', compact('transactions'))
        </div>
    </div>
</div>
@endsection

@push('script')
    <!-- ApexCharts -->
    <script src="{{ asset('public/frontend/js/apexcharts.js/') }}"></script>

    <script>

    var chart = $('#chart');
    var transaction_chart = chart.data('transaction_chart');

    const d = new Date();
    let year = d.getFullYear();

    var options = {
      series: [{
      name: 'Inflation',
      data: transaction_chart.transaction_data
    }],
      chart: {
      height: 350,
      type: 'bar',
    },
    plotOptions: {
      bar: {
        borderRadius: 10,
        dataLabels: {
          position: 'top', // top, center, bottom
        },
      }
    },
    dataLabels: {
      enabled: true,
      formatter: function (val) {
        return val + " {{ get_default_currency_code() }}";
      },
      offsetY: -20,
      style: {
        fontSize: '12px',
        colors: ["#304758"]
      }
    },

    xaxis: {
      categories:  transaction_chart.transaction_month,
      position: 'top',
      axisBorder: {
        show: false
      },
      axisTicks: {
        show: false
      },
      crosshairs: {
        fill: {
          type: 'gradient',
          gradient: {
            colorFrom: '#D8E3F0',
            colorTo: '#BED1E6',
            stops: [0, 100],
            opacityFrom: 0.4,
            opacityTo: 0.5,
          }
        }
      },
      tooltip: {
        enabled: true,
      }
    },
    yaxis: {
      axisBorder: {
        show: false
      },
      axisTicks: {
        show: false,
      },
      labels: {
        show: false,
        formatter: function (val) {
          return val + " {{ get_default_currency_code() }}";
        }
      }

    },
    title: {
      text: "{{ __('Yearly Transactions') }}" + ' , ' +year,
      floating: true,
      offsetY: 330,
      align: 'center',
      style: {
        color: '#444'
      }
    }
    };

    var chart = new ApexCharts(document.querySelector("#chart"), options);
    chart.render();

</script>
@endpush
