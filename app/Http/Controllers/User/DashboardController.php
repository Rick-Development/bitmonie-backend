<?php
namespace App\Http\Controllers\User;
use Exception;
use Carbon\Carbon;
use App\Models\UserWallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Constants\PaymentGatewayConst;

class DashboardController extends Controller
{
    public function index()
    {
        $page_title = "Dashboard";

        $wallet = UserWallet::auth()->first();

        $transactions = Transaction::auth()->get();

        $transaction_count = Transaction::auth()->count();
        $complete_transaction_count = Transaction::auth()->complete()->count();
        $pending_transaction_count = Transaction::auth()->pending()->count();

        $total_add_money = $transactions->where('type', PaymentGatewayConst::TYPEADDMONEY)->sum('request_amount');
        $total_money_out = $transactions->where('type', PaymentGatewayConst::TYPEMONEYOUT)->sum('request_amount');
        $fund_transfer = $transactions->where('user_id', Auth::id())->whereIn('type', [PaymentGatewayConst::TYPE_OWN_BANK_TRANSFER, PaymentGatewayConst::TYPE_OTHER_BANK_TRANSFER, PaymentGatewayConst::TYPE_MOBILE_WALLET_TRANSFER])->sum('request_amount');

        $fund_received = Transaction::where('receiver_id', Auth::id())->whereIn('type', [PaymentGatewayConst::TYPE_OWN_BANK_TRANSFER, PaymentGatewayConst::TYPE_OTHER_BANK_TRANSFER, PaymentGatewayConst::TYPE_MOBILE_WALLET_TRANSFER])->sum('request_amount');

        try{
            $start = Carbon::createFromDate(Carbon::now()->year, 1, 1);
            $end = Carbon::createFromDate(Carbon::now()->year, 12, 31);
            $transaction_chart = [];
            $transaction_month = [];

            while ($start->lessThanOrEqualTo($end)) {
                $start_date = $start->startOfMonth()->toDateString();
                $end_date = $start->endOfMonth()->toDateString();

                $transaction_total = Transaction::where(function ($query) {
                                            $query->where('user_id', Auth::id())
                                                  ->orWhere('receiver_id', Auth::id());
                                        })
                                        ->whereDate('created_at', '>=', $start_date)
                                        ->whereDate('created_at', '<=', $end_date)
                                        ->sum('request_amount');

                $transaction_data[] = number_format($transaction_total, 2, '.', '');
                $transaction_month[] = $start->format('F');

                $start->addMonth();
            }

            $transaction_chart = [
                'transaction_data' => $transaction_data,
                'transaction_month' => $transaction_month,
            ];
        }catch (Exception $e) {
            $transaction_chart = [
                'transaction_data' => [],
                'transaction_month' => [],
            ];
        }


        $transactions = Transaction::auth()->orderByDesc("id")->latest()->take(3)->get();

        return view('user.dashboard',compact(
            "page_title",
            "wallet",
            "total_add_money",
            "total_money_out",
            "fund_transfer",
            "fund_received",
            "transaction_count",
            "complete_transaction_count",
            "pending_transaction_count",
            'transaction_chart',
            'transactions'
        ));
    }

    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('user.login');
    }

    public function deleteAccount(Request $request) {
        $user = auth()->user();
        try{
            $user->status = 0;
            $user->save();
            Auth::logout();
            return redirect()->route('index')->with(['success' => ['Your account deleted successfully!']]);
        }catch(Exception $e) {
            return back()->with(['error' => ['Something Went Wrong! Please Try Again.']]);
        }
    }
    /**
     * Method for check pin
     */
    public function checkPin(Request $request){
        $pin = $request->pin;
        $user = auth()->user();
        if($pin != $user->pin_code){
            $data = 0;
            return response($data);
        }else{
            $data = 1;
            return response( $data);
        }
    }
}
