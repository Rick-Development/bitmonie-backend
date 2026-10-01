<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionWallet;
use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Models\ReferralCommissionSetting;
use App\Models\User;
use App\Services\SharedReferralCommissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReferralCommissionController extends Controller
{
    public function index(Request $request)
    {
        $page_title = 'Shared Referral Commissions';
        $commissions = $this->query($request)->paginate(30)->withQueryString();
        $stats = [
            'credited' => ReferralCommission::where('status', ReferralCommission::STATUS_CREDITED)->sum('commission_amount'),
            'pending' => ReferralCommission::where('status', ReferralCommission::STATUS_PENDING)->sum('commission_amount'),
            'flagged' => ReferralCommission::where('status', ReferralCommission::STATUS_FLAGGED)->sum('commission_amount'),
        ];

        return view('admin.sections.referral-commissions.index', compact('page_title', 'commissions', 'stats'));
    }

    public function show(int $id)
    {
        $page_title = 'Referral Commission Details';
        $commission = ReferralCommission::with(['referrer', 'referred'])->findOrFail($id);

        return view('admin.sections.referral-commissions.show', compact('page_title', 'commission'));
    }

    public function update(Request $request, SharedReferralCommissionService $service, int $id)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'flag'])],
        ]);

        $commission = ReferralCommission::findOrFail($id);

        if ($validated['action'] === 'approve') {
            $service->approve($commission);
            return back()->with(['success' => ['Commission approved successfully.']]);
        }

        $service->flag($commission);
        return back()->with(['success' => ['Commission flagged successfully.']]);
    }

    public function settings()
    {
        $page_title = 'Referral Commission Settings';
        $settings = ReferralCommissionSetting::current();

        return view('admin.sections.referral-commissions.settings', compact('page_title', 'settings'));
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'commission_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'auto_credit' => ['sometimes', 'boolean'],
        ]);

        ReferralCommissionSetting::current()->update([
            'commission_rate_percent' => $validated['commission_rate_percent'],
            'auto_credit' => (bool) ($validated['auto_credit'] ?? false),
        ]);

        return back()->with(['success' => ['Referral commission settings updated successfully.']]);
    }

    public function tree(int $id)
    {
        $page_title = 'Referral Tree';
        $user = User::findOrFail($id);
        $tree = $this->treeFor($user);

        return view('admin.sections.referral-commissions.tree', compact('page_title', 'user', 'tree'));
    }

    public function export(Request $request)
    {
        $filename = 'shared-referral-commissions-' . now()->format('Ymd-His') . '.csv';
        $commissions = $this->query($request)->get();

        return response()->streamDownload(function () use ($commissions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Referrer', 'Referred', 'Transaction ID', 'Type', 'Currency', 'Platform Fee', 'Commission', 'Status', 'Created At']);
            foreach ($commissions as $commission) {
                fputcsv($handle, [
                    $commission->id,
                    optional($commission->referrer)->email,
                    optional($commission->referred)->email,
                    $commission->transaction_id,
                    $commission->transaction_type,
                    $commission->currency_code,
                    $commission->platform_fee_amount,
                    $commission->commission_amount,
                    $commission->status,
                    optional($commission->created_at)->toDateTimeString(),
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function query(Request $request)
    {
        return ReferralCommission::with(['referrer', 'referred'])
            ->when($request->query('referrer'), function ($query, $value) {
                $query->whereHas('referrer', fn ($user) => $user->where('email', 'like', "%{$value}%")->orWhere('username', 'like', "%{$value}%"));
            })
            ->when($request->query('referred'), function ($query, $value) {
                $query->whereHas('referred', fn ($user) => $user->where('email', 'like', "%{$value}%")->orWhere('username', 'like', "%{$value}%"));
            })
            ->when($request->query('transaction_type'), fn ($query, $type) => $query->where('transaction_type', $type))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('from'), fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($request->query('to'), fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('id');
    }

    protected function treeFor(User $user, int $depth = 0): array
    {
        if ($depth >= 5) {
            return [];
        }

        return Referral::with('referred')
            ->where('referrer_id', $user->id)
            ->get()
            ->map(function (Referral $referral) use ($depth) {
                return [
                    'user' => $referral->referred,
                    'status' => $referral->status,
                    'children' => $referral->referred ? $this->treeFor($referral->referred, $depth + 1) : [],
                ];
            })
            ->all();
    }
}
