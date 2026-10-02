<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EasyEarnPlan;
use App\Models\EasyEarnSetting;
use App\Models\EasyEarnTransaction;
use App\Services\EasyEarnService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class EasyEarnController extends Controller
{
    public function index(Request $request)
    {
        $page_title = 'EasyEarn Plans';
        $plans = $this->query($request)->paginate(25)->withQueryString();
        $totalLocked = EasyEarnPlan::where('status', EasyEarnPlan::STATUS_ACTIVE)->sum('usdt_amount_deposited');

        return view('admin.sections.easyearn.index', compact('page_title', 'plans', 'totalLocked'));
    }

    public function show(int $id)
    {
        $page_title = 'EasyEarn Plan Details';
        $plan = EasyEarnPlan::with('user')->findOrFail($id);
        $transactions = $plan->transactions()->orderByDesc('id')->paginate(30);

        return view('admin.sections.easyearn.show', compact('page_title', 'plan', 'transactions'));
    }

    public function update(Request $request, EasyEarnService $easyEarnService, int $id)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['trigger_payout', 'mark_matured', 'cancel'])],
        ]);

        $plan = EasyEarnPlan::findOrFail($id);

        try {
            if ($validated['action'] === 'trigger_payout') {
                $easyEarnService->withdraw($plan, true);
                return back()->with(['success' => ['EasyEarn payout triggered successfully.']]);
            }

            if ($validated['action'] === 'mark_matured') {
                $plan->update(['status' => EasyEarnPlan::STATUS_MATURED, 'matured_at' => now()]);
                $easyEarnService->flushCache($plan);
                return back()->with(['success' => ['EasyEarn plan marked as matured.']]);
            }

            $easyEarnService->cancel($plan);
            return back()->with(['success' => ['EasyEarn plan cancelled successfully.']]);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['easyearn' => $exception->getMessage()]);
        }
    }

    public function destroy(EasyEarnService $easyEarnService, int $id)
    {
        $plan = EasyEarnPlan::findOrFail($id);

        try {
            $easyEarnService->cancel($plan);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['easyearn' => $exception->getMessage()]);
        }

        return redirect()->route('admin.easyearn.index')->with(['success' => ['EasyEarn plan cancelled successfully.']]);
    }

    public function settings()
    {
        $page_title = 'EasyEarn Settings';
        $settings = EasyEarnSetting::current();

        return view('admin.sections.easyearn.settings', compact('page_title', 'settings'));
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'return_multiplier' => ['required', 'numeric', 'min:1'],
            'term_days' => ['required', 'integer', 'min:1'],
            'early_withdrawal_enabled' => ['sometimes', 'boolean'],
            'early_withdrawal_penalty_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'terms' => ['nullable', 'string'],
        ]);

        $settings = EasyEarnSetting::current();
        $settings->update([
            'return_multiplier' => $validated['return_multiplier'],
            'term_days' => $validated['term_days'],
            'early_withdrawal_enabled' => (bool) ($validated['early_withdrawal_enabled'] ?? false),
            'early_withdrawal_penalty_percent' => $validated['early_withdrawal_penalty_percent'],
            'terms' => $validated['terms'] ?? null,
        ]);

        return back()->with(['success' => ['EasyEarn settings updated successfully.']]);
    }

    public function exportPlans(Request $request)
    {
        $filename = 'easyearn-plans-' . now()->format('Ymd-His') . '.csv';
        $plans = $this->query($request)->get();

        return response()->streamDownload(function () use ($plans) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'User', 'Email', 'Deposited', 'Expected Return', 'Multiplier', 'Status', 'Maturity Date', 'Created At']);
            foreach ($plans as $plan) {
                fputcsv($handle, [$plan->id, optional($plan->user)->fullname, optional($plan->user)->email, $plan->usdt_amount_deposited, $plan->expected_return, $plan->return_multiplier, $plan->status, optional($plan->maturity_date)->toDateTimeString(), optional($plan->created_at)->toDateTimeString()]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportTransactions(Request $request)
    {
        $filename = 'easyearn-transactions-' . now()->format('Ymd-His') . '.csv';
        $transactions = EasyEarnTransaction::with(['plan', 'user'])->orderByDesc('id')->get();

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Plan ID', 'User', 'Type', 'Status', 'Amount', 'Reference', 'Created At']);
            foreach ($transactions as $transaction) {
                fputcsv($handle, [$transaction->id, $transaction->easyearn_plan_id, optional($transaction->user)->email, $transaction->type, $transaction->status, $transaction->amount, $transaction->reference, optional($transaction->created_at)->toDateTimeString()]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function query(Request $request)
    {
        return EasyEarnPlan::with('user')
            ->when($request->query('user'), function ($query, $user) {
                $query->whereHas('user', function ($userQuery) use ($user) {
                    $userQuery->where('email', 'like', "%{$user}%")
                        ->orWhere('firstname', 'like', "%{$user}%")
                        ->orWhere('lastname', 'like', "%{$user}%")
                        ->orWhere('username', 'like', "%{$user}%");
                });
            })
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('from'), fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($request->query('to'), fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('id');
    }
}
