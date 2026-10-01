<?php

namespace App\Http\Controllers\Admin;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Models\UserSupportTicket;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Constants\SupportTicketConst;
use App\Constants\PaymentGatewayConst;
use App\Providers\Admin\BasicSettingsProvider;
use Pusher\PushNotifications\PushNotifications;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = "Dashboard";
        
        //user data

        $total_users     = (User::toBase()->count() == 0) ? 1 : User::toBase()->count();
        $unverified_user = User::toBase()->where('email_verified',0)->count();
        $active_user     = User::toBase()->where('status',true)->count();
        $banned_user     = User::toBase()->where('status',false)->count();
        $user_percent    = (($active_user * 100 ) / $total_users);

        if ($user_percent > 100) {
            $user_percent = 100;
        }

        //support ticket

        $total_ticket       = (UserSupportTicket::toBase()->count() == 0) ? 1 : UserSupportTicket::toBase()->count();
        $active_ticket      = UserSupportTicket::toBase()->where('status',SupportTicketConst::ACTIVE)->count();
        $pending_ticket     = UserSupportTicket::toBase()->where('status',SupportTicketConst::PENDING)->count();

        if($pending_ticket == 0 && $active_ticket != 0){
            $percent_ticket = 100;
        }elseif($pending_ticket == 0 && $active_ticket == 0){
         $percent_ticket = 0;
        }else{
           $percent_ticket = ($active_ticket / ($active_ticket + $pending_ticket)) * 100;
        }

        // Add Money 
        $total_add_money            = Transaction::addMoney()->sum('request_amount');
        $total_add_money_pending    = Transaction::addMoney()->where('status',PaymentGatewayConst::STATUSPENDING)->sum('request_amount');
        $total_add_money_success    = Transaction::addMoney()->where('status',PaymentGatewayConst::STATUSSUCCESS)->sum('request_amount');


        // Money Out
        $total_money_out            = Transaction::moneyOut()->sum('request_amount');
        $total_money_out_pending    = Transaction::moneyOut()->where('status',PaymentGatewayConst::STATUSPENDING)->sum('request_amount');
        $total_money_out_success    = Transaction::moneyOut()->where('status',PaymentGatewayConst::STATUSSUCCESS)->sum('request_amount');
        
        // admin profits
        $admin_profits              = Transaction::sum('total_charge');

        // transactions 
        $transactions               = (Transaction::toBase()->count() == 0 ) ? 1 : Transaction::toBase()->count();
        $pending_transactions       = Transaction::where('status',PaymentGatewayConst::STATUSPENDING)->count();
        $success_transactions       = Transaction::where('status',PaymentGatewayConst::STATUSSUCCESS)->count();

        if($pending_transactions == 0 && $success_transactions != 0){
            $percent_transactions = 100;
        }elseif($pending_transactions == 0 && $success_transactions == 0){
         $percent_transactions = 0;
        }else{
           $percent_transactions = ($success_transactions / ($success_transactions + $pending_transactions)) * 100;
        }

        // transaction logs add money
        $logs = Transaction::addMoney()->orderByDesc("id")->paginate(3);

        /************** transaction analytics calculations start ***********/
            $start = strtotime(date('Y-m-01'));
            $end = strtotime(date('Y-m-31'));


            $pending_data  = [];
            $success_data  = [];
            $month_day  = [];

            while ($start <= $end) {
                $start_date = date('Y-m-d', $start);
                
                $pending            = Transaction::where('status', PaymentGatewayConst::STATUSPENDING)->whereDate('created_at',$start_date)->count();
                $success            = Transaction::whereDate('created_at',$start_date)
                                            ->where('status',PaymentGatewayConst::STATUSSUCCESS)
                                            ->count();
                
                $pending_data[]     = $pending;
                $success_data[]     = $success;
                $month_day[]        = date('Y-m-d', $start);
                $start              = strtotime('+1 day',$start);
            }

            $chart_one_data = [
                'pending_data'      => $pending_data,
                'success_data'     => $success_data,
            ];

        /************** transactions analytics calculation end ************************/

        /********************** Growth Calculations Start *****************************/

            $today_profit       = Transaction::whereDate('created_at', Carbon::today())->sum('total_charge') ?? 0;

            $last_week_profit   = Transaction::whereBetween('created_at', [Carbon::now()->subWeek(), Carbon::now()])
                                ->sum('total_charge') ?? 0;

            $last_month_profit  = Transaction::whereBetween('created_at', [Carbon::now()->subMonth()->startOfMonth(),Carbon::now()->subMonth()->endOfMonth()])->sum('total_charge') ?? 0;

            $last_year_profit   = Transaction::whereBetween('created_at', [Carbon::now()->subYear()->startOfYear(), Carbon::now()->subYear()->endOfYear()])->sum('total_charge') ?? 0;
            

            $growth_chart           = [
                'today_profit'      => $today_profit,
                'last_week_profit'  => $last_week_profit,
                'last_month_profit' => $last_month_profit,
                'last_year_profit'  => $last_year_profit
            ];

        /********************** Growth Calculations End *****************************/



        // charts
        $user_chart = [$active_user, $banned_user,$unverified_user,$total_users];

        $data                   = [
            'total_users'                   => $total_users,
            'unverified_user'               => $unverified_user,
            'active_user'                   => $active_user,
            'user_percent'                  => $user_percent,

            'total_ticket'                  => $total_ticket,
            'active_ticket'                 => $active_ticket,
            'pending_ticket'                => $pending_ticket,
            'percent_ticket'                => $percent_ticket,

            'total_user_count'              => User::all()->count(),
            'total_support_ticket_count'    => UserSupportTicket::all()->count(),
            
            'total_add_money'               => $total_add_money,
            'total_add_money_pending'       => $total_add_money_pending,
            'total_add_money_success'       => $total_add_money_success,
            
            'total_money_out'               => $total_money_out,
            'total_money_out_pending'       => $total_money_out_pending,
            'total_money_out_success'       => $total_money_out_success,
            
            'admin_profits'                 => $admin_profits,
            
            'transactions'                  => $transactions,
            'pending_transactions'          => $pending_transactions,
            'success_transactions'          => $success_transactions,
            'percent_transactions'          => $percent_transactions,
            'total_transactions_count'      => Transaction::all()->count(),

            'user_chart_data'               => $user_chart,
            'chart_one_data'                => $chart_one_data,
            'growth_chart'                  => $growth_chart,
            'month_day'                     => $month_day,
        ];

        $months = $this->getAllMonthNames();

        return view('admin.sections.dashboard.index',compact(
            'page_title',
            'data',
            'logs'
        ));
    }
    /**
     * Method for get all months name
     */
    public function getAllMonthNames(){
        $monthNames = collect([]);

        for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++) {
            $monthName = Carbon::createFromDate(null, $monthNumber, null)->format('M');
            $monthNames->push($monthName);
        }

        return $monthNames;
    }

    /**
     * Logout Admin From Dashboard
     * @return view
     */
    public function logout(Request $request) {

        $push_notification_setting = BasicSettingsProvider::get()->push_notification_config;

        if($push_notification_setting) {
            $method = $push_notification_setting->method ?? false;

            if($method == "pusher") {
                $instant_id     = $push_notification_setting->instance_id ?? false;
                $primary_key    = $push_notification_setting->primary_key ?? false;

                if($instant_id && $primary_key) {
                    $pusher_instance = new PushNotifications([
                        "instanceId"    => $instant_id,
                        "secretKey"     => $primary_key,
                    ]);

                    $pusher_instance->deleteUser("".Auth::user()->id."");
                }
            }

        }

        $admin = auth()->user();
        try{
            $admin->update([
                'last_logged_out'   => now(),
                'login_status'      => false,
            ]);
        }catch(Exception $e) {
            // Handle Error
        }

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }


    /**
     * Function for clear admin notification
     */
    public function notificationsClear() {
        $admin = auth()->user();

        if(!$admin) {
            return false;
        }

        try{
            $admin->update([
                'notification_clear_at'     => now(),
            ]);
        }catch(Exception $e) {
            $error = ['error' => ['Something went wrong! Please try again.']];
            return Response::error($error,null,404);
        }

        $success = ['success' => ['Notifications clear successfully!']];
        return Response::success($success,null,200);
    }
}
