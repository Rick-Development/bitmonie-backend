<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AutosavePlan;
use App\Models\AutosaveTransaction;
use App\Services\AutosaveService;
use App\Support\UserScopedCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AutosaveController extends Controller
{
    public function index(Request $request)
    {
        $page_title = 'AutoSave Plans';
        $plans = $this->query($request)->paginate(25)->withQueryString();
        $totalBalance = AutosavePlan::sum('balance');

        return view('admin.sections.autosave.index', compact('page_title', 'plans', 'totalBalance'));
    }

    public function show(int $id)
    {
        $page_title = 'AutoSave Plan Details';
        $plan = AutosavePlan::with('user')->findOrFail($id);
        $transactions = $plan->transactions()->orderByDesc('id')->paginate(30);

        return view('admin.sections.autosave.show', compact('page_title', 'plan', 'transactions'));
    }

    public function update(Request $request, AutosaveService $autosaveService, int $id)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'paused', 'cancelled'])],
        ]);

        $plan = AutosavePlan::findOrFail($id);
        $plan = $autosaveService->updatePlan($plan, $validated);
        $this->flushUserCache($plan);

        return back()->with(['success' => ['AutoSave plan updated successfully.']]);
    }

    public function destroy(AutosaveService $autosaveService, int $id)
    {
        $plan = AutosavePlan::findOrFail($id);
        $plan = $autosaveService->cancelPlan($plan);
        $this->flushUserCache($plan);

        return redirect()->route('admin.autosave.index')->with(['success' => ['AutoSave plan cancelled successfully.']]);
    }

    public function exportPlans(Request $request)
    {
        $filename = 'autosave-plans-' . now()->format('Ymd-His') . '.csv';
        $plans = $this->query($request)->get();

        return response()->streamDownload(function () use ($plans) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'User', 'Email', 'Name', 'Mode', 'Status', 'Amount', 'Percentage', 'Frequency', 'Balance', 'Created At']);

            foreach ($plans as $plan) {
                fputcsv($handle, [
                    $plan->id,
                    optional($plan->user)->fullname,
                    optional($plan->user)->email,
                    $plan->name,
                    $plan->mode,
                    $plan->status,
                    $plan->amount,
                    $plan->percentage,
                    $plan->frequency,
                    $plan->balance,
                    optional($plan->created_at)->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportTransactions(Request $request)
    {
        $filename = 'autosave-transactions-' . now()->format('Ymd-His') . '.csv';
        $transactions = AutosaveTransaction::with(['plan', 'user'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('plan_id'), fn ($query, $planId) => $query->where('autosave_plan_id', $planId))
            ->orderByDesc('id')
            ->get();

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Plan', 'User', 'Type', 'Status', 'Amount', 'Reference', 'Failure Reason', 'Created At']);

            foreach ($transactions as $transaction) {
                fputcsv($handle, [
                    $transaction->id,
                    optional($transaction->plan)->name,
                    optional($transaction->user)->email,
                    $transaction->type,
                    $transaction->status,
                    $transaction->amount,
                    $transaction->reference,
                    $transaction->failure_reason,
                    optional($transaction->created_at)->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function query(Request $request)
    {
        return AutosavePlan::with('user')
            ->when($request->query('user'), function ($query, $user) {
                $query->whereHas('user', function ($userQuery) use ($user) {
                    $userQuery->where('email', 'like', "%{$user}%")
                        ->orWhere('firstname', 'like', "%{$user}%")
                        ->orWhere('lastname', 'like', "%{$user}%")
                        ->orWhere('username', 'like', "%{$user}%");
                });
            })
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('mode'), fn ($query, $mode) => $query->where('mode', $mode))
            ->when($request->query('from'), fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($request->query('to'), fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('id');
    }

    protected function flushUserCache(AutosavePlan $plan): void
    {
        UserScopedCache::flush(['autosave', "user:{$plan->user_id}"]);
        UserScopedCache::flush(['autosave', "user:{$plan->user_id}", "autosave-plan:{$plan->id}"]);
    }
}
