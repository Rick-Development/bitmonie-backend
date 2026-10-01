@extends('admin.layouts.master')

@push('css')

@endpush

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __("Dashboard")])
@endsection

@section('content')
    <div class="dashboard-area">
        <div class="dashboard-item-area">
            <div class="row">
                <div class="col-xxxl-4 col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-15">
                    <div class="dashbord-item">
                        <div class="dashboard-content">
                            <div class="left">
                                <h6 class="title">{{ __("Total Users") }}</h6>
                                <div class="user-info">
                                    <h2 class="user-count">{{ formatNumberInKNotation($data['total_user_count']) }}</h2>
                                </div>
                                <div class="user-badge">
                                    <span class="badge badge--success">{{ __("Active") }} {{ $data['active_user'] }}</span>
                                    <span class="badge badge--warning">{{ __("Unverified") }} {{ $data['unverified_user'] }}</span>
                                </div>
                            </div>
                            <div class="right">
                                <div class="chart" id="chart6" data-percent="{{ $data['user_percent'] }}"><span>{{ round($data['user_percent']) }}%</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxxl-4 col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-15">
                    <div class="dashbord-item">
                        <div class="dashboard-content">
                            <div class="left">
                                <h6 class="title">{{ __("Total Support Ticket") }}</h6>
                                <div class="user-info">
                                    <h2 class="user-count">{{ formatNumberInkNotation($data['total_support_ticket_count']) }}</h2>
                                </div>
                                <div class="user-badge">
                                    <span class="badge badge--info">{{ __("Active") }} {{ formatNumberInkNotation($data['active_ticket']) }}</span>
                                    <span class="badge badge--warning">{{ __("Pending") }} {{ formatNumberInkNotation($data['pending_ticket']) }}</span>
                                </div>
                            </div>
                            <div class="right">
                                <div class="chart" id="chart7" data-percent="{{ $data['percent_ticket'] }}"><span>{{ round($data['percent_ticket']) }}%</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxxl-4 col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-15">
                    <div class="dashbord-item">
                        <div class="dashboard-content">
                            <div class="left">
                                <h6 class="title">{{ __("Total Transactions") }}</h6>
                                <div class="user-info">
                                    <h2 class="user-count">{{ formatNumberInkNotation($data['total_transactions_count']) }}</h2>
                                </div>
                                <div class="user-badge">
                                    <span class="badge badge--success">{{ __("Success") }} {{ formatNumberInkNotation($data['success_transactions']) }}</span>
                                    <span class="badge badge--warning">{{ __("Pending") }} {{ formatNumberInkNotation($data['pending_transactions']) }}</span>
                                </div>
                            </div>
                            <div class="right">
                                <div class="chart" id="chart10" data-percent="{{ $data['percent_transactions'] }}"><span>{{ round($data['percent_transactions']) }}%</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxxl-4 col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-15">
                    <div class="dashbord-item">
                        <div class="dashboard-content">
                            <div class="left">
                                <h6 class="title">{{ __("Total Add Money Balance") }}</h6>
                                <div class="user-info">
                                    <h2 class="user-count">{{ get_amount($data['total_add_money'],get_default_currency_code()) }}</h2>
                                </div>
                                <div class="user-badge">
                                    <span class="badge badge--info">{{ __("Success Balance") }} {{ get_amount($data['total_add_money_success'],get_default_currency_code()) }}</span>
                                    <span class="badge badge--warning">{{ __("Pending Balance") }} {{ get_amount($data['total_add_money_pending'],get_default_currency_code()) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxxl-4 col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-15">
                    <div class="dashbord-item">
                        <div class="dashboard-content">
                            <div class="left">
                                <h6 class="title">{{ __("Total Money Out Balance") }}</h6>
                                <div class="user-info">
                                    <h2 class="user-count">{{ get_amount($data['total_money_out'],get_default_currency_code()) }}</h2>
                                </div>
                                <div class="user-badge">
                                    <span class="badge badge--info">{{ __("Success Balance") }} {{ get_amount($data['total_money_out_success'],get_default_currency_code()) }}</span>
                                    <span class="badge badge--warning">{{ __("Pending Balance") }} {{ get_amount($data['total_money_out_pending'],get_default_currency_code()) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xxxl-4 col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-12 mb-15">
                    <div class="dashbord-item">
                        <div class="dashboard-content">
                            <div class="left">
                                <h6 class="title">{{ __("Admin Profits") }}</h6>
                                <div class="user-info">
                                    <h2 class="user-count">{{ get_amount($data['admin_profits'],get_default_currency_code()) }}</h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="chart-area mt-15">
        <div class="row mb-15-none">
            <div class="col-xxl-6 col-xl-6 col-lg-6 mb-15">
                <div class="chart-wrapper">
                    <div class="chart-area-header">
                        <h5 class="title">{{ __("Transactions Analytics") }}</h5>
                    </div>
                    <div class="chart-container">
                        <div id="chart1" class="order-chart" data-chart_one_data="{{ json_encode($data['chart_one_data']) }}" data-month_day="{{ json_encode($data['month_day']) }}"></div>
                    </div>
                </div>
            </div>
            <div class="col-xxxl-6 col-xxl-3 col-xl-6 col-lg-6 mb-15">
                <div class="chart-wrapper">
                    <div class="chart-area-header">
                        <h5 class="title">{{ __("User Analytics Chart") }}</h5>
                    </div>
                    <div class="chart-container">
                        <div id="chart4" class="balance-chart" data-user_chart_data="{{ json_encode($data['user_chart_data']) }}"></div>
                    </div>
                    <div class="chart-area-footer">
                        <div class="chart-btn">
                            <a href="{{ setRoute('admin.users.index') }}" class="btn--base w-100">{{ __("View User") }}</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxxl-12 col-xxl-3 col-xl-12 col-lg-12 mb-15">
                <div class="chart-wrapper">
                    <div class="chart-area-header">
                        <h5 class="title">{{ __("Growth") }}</h5>
                    </div>
                    <div class="chart-container">
                        <div id="chart5" class="growth-chart" data-growth_chart="{{ json_encode($data['growth_chart']) }}"></div>
                    </div>                    
                </div>
            </div>
        </div>
    </div>
    <div class="table-area mt-15">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("Latest Transactions") }}</h5>
            </div>
            <div class="table-responsive">
                @include('admin.components.data-table.add-money-table',compact('logs'))
            </div>
        </div>
    </div>
@endsection

@push('script')
<!-- apexcharts js -->
<script src="{{ asset('backend/js/apexcharts.js') }}"></script>
<!-- chart js -->
<script src="{{ asset('backend/js/chart.js') }}"></script>
<script>
    // apex-chart
    var chart1 = $('#chart1');
    var chart_one_data = chart1.data('chart_one_data');
    var month_day = chart1.data('month_day');
    var options = {
    series: [{
    name: '{{ __("Pending") }}',
    color: "#C8102E",
    data: chart_one_data.pending_data
    }, {
    name: '{{ __("Success") }}',
    color: "#6F6593",
    data: chart_one_data.success_data
    },],
    chart: {
    type: 'bar',
    height: 350,
    stacked: true,
    toolbar: {
        show: false
    },
    zoom: {
        enabled: true
    }
    },
    responsive: [{
    breakpoint: 480,
    options: {
        legend: {
        position: 'bottom',
        offsetX: -10,
        offsetY: 0
        }
    }
    }],
    plotOptions: {
    bar: {
        horizontal: false,
        borderRadius: 10
    },
    },
    xaxis: {
    type: 'datetime',
    categories: month_day,

    },
    legend: {
    position: 'bottom',
    offsetX: 40
    },
    fill: {
    opacity: 1
    }
    };

    var chart = new ApexCharts(document.querySelector("#chart1"), options);
    chart.render();

    // apex-chart
    var chart4 = $('#chart4');
    var user_chart_data = chart4.data('user_chart_data');
    var options = {
    series: user_chart_data,
    chart: {
    width: 350,
    type: 'pie'
    },
    colors: ['#C8102E', '#6F6593', '#8075AA', '#A192D9'],
    labels: ['{{ __("Active") }}', '{{ __("Unverified") }}', '{{ __("Banned") }}', '{{ __("ALL") }}'],
    responsive: [{
    breakpoint: 1480,
    options: {
        chart: {
        width: 280
        },
        legend: {
        position: 'bottom'
        }
    },
    breakpoint: 1199,
    options: {
        chart: {
        width: 380
        },
        legend: {
        position: 'bottom'
        }
    },
    breakpoint: 575,
    options: {
        chart: {
        width: 280
        },
        legend: {
        position: 'bottom'
        }
    }
    }],
    legend: {
    position: 'bottom'
    },
    };

    var chart = new ApexCharts(document.querySelector("#chart4"), options);
    chart.render();

    /******* growth chart ********/
    var growthChartData = document.getElementById('chart5').dataset.growth_chart;
    var growthData = JSON.parse(growthChartData);

    var today_profit = parseFloat(growthData.today_profit).toFixed(2) || 0;
    var last_week_profit = parseFloat(growthData.last_week_profit).toFixed(2) || 0;
    var last_month_profit = parseFloat(growthData.last_month_profit).toFixed(2) || 0;
    var last_year_profit = parseFloat(growthData.last_year_profit).toFixed(2) || 0;
    
    var options = {
        series: [
            parseFloat(today_profit), 
            parseFloat(last_week_profit), 
            parseFloat(last_month_profit), 
            parseFloat(last_year_profit)
        ],
        chart: {
            width: 350,
            type: 'donut',
        },
        colors: ['#C8102E', '#6F6593', '#8075AA', '#A192D9'],
        labels: ['{{ __("Today") }}', '{{ __("Last Week") }}', '{{ __("Last Month") }}', '{{ __("Last Year") }}'],
        legend: {
            position: 'bottom'
        },
        responsive: [{
            breakpoint: 1600,
            options: {
                chart: {
                    width: 100,
                },
                legend: {
                    position: 'bottom'
                }
            },
            breakpoint: 1199,
            options: {
                chart: {
                    width: 380
                },
                legend: {
                    position: 'bottom'
                }
            },
            breakpoint: 575,
            options: {
                chart: {
                    width: 280
                },
                legend: {
                    position: 'bottom'
                }
            }
        }]
    };

    var chart = new ApexCharts(document.querySelector("#chart5"), options);
    chart.render();

</script>
@endpush