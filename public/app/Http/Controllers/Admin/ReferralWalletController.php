<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\WalletWithdrawalRequest;
use App\Services\ReferralWalletService;
use Exception;
use Illuminate\Http\Request;
use RuntimeException;

class ReferralWalletController extends Controller
{
    protected ReferralWalletService $referralWalletService;

    public function __construct(ReferralWalletService $referralWalletService)
    {
        $this->referralWalletService = $referralWalletService;
    }

    public function index(Request $request)
    {
        return $this->renderWithdrawalList($request, null, 'All Referral Withdrawal Requests');
    }

    public function pending(Request $request)
    {
        return $this->renderWithdrawalList($request, 'pending', 'Pending Referral Withdrawal Requests');
    }

    public function completed(Request $request)
    {
        return $this->renderWithdrawalList($request, 'completed', 'Completed Referral Withdrawal Requests');
    }

    public function rejected(Request $request)
    {
        return $this->renderWithdrawalList($request, 'rejected', 'Rejected Referral Withdrawal Requests');
    }

    public function approve(Request $request, int $id)
    {
        $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        try {
            $withdrawal = $this->referralWalletService->approveWithdrawal(
                $id,
                auth()->guard('admin')->user(),
                $request->input('admin_note')
            );

            if ($request->expectsJson()) {
                return Response::successResponse('Withdrawal approved successfully', $this->serializeWithdrawal($withdrawal));
            }

            return back()->with(['success' => ['Withdrawal approved successfully.']]);
        } catch (RuntimeException $e) {
            if ($request->expectsJson()) {
                return Response::errorResponse($e->getMessage(), null, 422);
            }

            return back()->with(['error' => [$e->getMessage()]]);
        } catch (Exception $e) {
            if ($request->expectsJson()) {
                return Response::errorResponse('Unable to approve withdrawal right now.', null, 500);
            }

            return back()->with(['error' => ['Unable to approve withdrawal right now.']]);
        }
    }

    public function reject(Request $request, int $id)
    {
        $request->validate([
            'admin_note' => 'required|string|max:1000',
        ]);

        try {
            $withdrawal = $this->referralWalletService->rejectWithdrawal(
                $id,
                auth()->guard('admin')->user(),
                $request->input('admin_note')
            );

            if ($request->expectsJson()) {
                return Response::successResponse('Withdrawal rejected successfully', $this->serializeWithdrawal($withdrawal));
            }

            return back()->with(['success' => ['Withdrawal rejected successfully.']]);
        } catch (RuntimeException $e) {
            if ($request->expectsJson()) {
                return Response::errorResponse($e->getMessage(), null, 422);
            }

            return back()->with(['error' => [$e->getMessage()]]);
        } catch (Exception $e) {
            if ($request->expectsJson()) {
                return Response::errorResponse('Unable to reject withdrawal right now.', null, 500);
            }

            return back()->with(['error' => ['Unable to reject withdrawal right now.']]);
        }
    }

    protected function renderWithdrawalList(Request $request, ?string $status, string $pageTitle)
    {
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $withdrawals = $this->referralWalletService->getWithdrawalRequests($status, $perPage);

        if ($request->expectsJson()) {
            return Response::successResponse(
                'Withdrawal requests retrieved successfully',
                $withdrawals->through(fn (WalletWithdrawalRequest $withdrawal) => $this->serializeWithdrawal($withdrawal))
            );
        }

        return view('admin.sections.referral-wallet.withdrawals.index', compact('pageTitle', 'withdrawals', 'status'));
    }

    protected function serializeWithdrawal(WalletWithdrawalRequest $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'reference' => $withdrawal->reference,
            'amount' => (float) $withdrawal->amount,
            'currency' => $withdrawal->currency_code,
            'status' => $withdrawal->status,
            'bank_name' => $withdrawal->bank_name,
            'account_number' => $withdrawal->account_number,
            'account_name' => $withdrawal->account_name,
            'admin_note' => $withdrawal->admin_note,
            'requested_at' => optional($withdrawal->created_at)->format('Y-m-d H:i:s'),
            'reviewed_at' => optional($withdrawal->reviewed_at)->format('Y-m-d H:i:s'),
            'user' => $withdrawal->user ? [
                'id' => $withdrawal->user->id,
                'username' => $withdrawal->user->username,
                'fullname' => $withdrawal->user->fullname,
                'email' => $withdrawal->user->email,
            ] : null,
            'reviewer' => $withdrawal->reviewer ? [
                'id' => $withdrawal->reviewer->id,
                'name' => $withdrawal->reviewer->fullname,
            ] : null,
        ];
    }
}
