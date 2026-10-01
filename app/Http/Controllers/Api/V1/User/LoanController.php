<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\Loan;
use App\Models\LoanBorrowRequest;
use App\Models\LoanOffer;
use App\Services\LoanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Notifications\User\LoanNotification;

class LoanController extends Controller
{
    protected $loanService;

    public function __construct(LoanService $loanService)
    {
        $this->loanService = $loanService;
    }

    /**
     * Create a lending offer
     */
    public function createLendingOffer(Request $request)
    {
        $payload = $this->prepareLoanPayload($request);
        $supportedAssets = $this->loanService->getSupportedAssets();

        $validator = Validator::make($payload, [
            'asset' => ['required', 'string', Rule::in($supportedAssets)],
            'amount' => 'required|numeric|min:1',
            'duration_days' => 'required|integer|in:30,60,90,180',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $result = $this->loanService->createLendingOffer(
                auth()->user(),
                $payload['asset'],
                (float) $payload['amount'],
                (int) $payload['duration_days']
            );

            // Notify
            auth()->user()->notify(new LoanNotification(
                'Lending Offer',
                (float) $payload['amount'],
                $payload['asset'],
                'Created'
            ));

            return Response::successResponse('Lending offer created successfully', $result, 201);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Cancel a pending lending offer
     */
    public function cancelLendingOffer($offerId)
    {
        try {
            $result = $this->loanService->cancelLendingOffer(auth()->user(), $offerId);

            return Response::successResponse('Lending offer cancelled successfully', $result);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Cancel a pending borrow request
     */
    public function cancelBorrowRequest(Request $request,$requestId)
    {
        
        try {
            $result = $this->loanService->cancelBorrowRequest(auth()->user(), $requestId);

            return Response::successResponse('Borrow request cancelled successfully', $result);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Create a borrow request
     */
    public function createBorrowRequest(Request $request)
    {
        $payload = $this->prepareLoanPayload($request);
        $supportedAssets = $this->loanService->getSupportedAssets();

        $validator = Validator::make($payload, [
            'asset' => ['required', 'string', Rule::in($supportedAssets)],
            'amount' => 'required|numeric|min:1',
            'duration_days' => 'required|integer|in:30,60,90,180',
            'collateral_asset' => ['required', 'string', Rule::in($supportedAssets)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->loanService->createBorrowRequest(
                auth()->user(),
                $payload['asset'],
                (float) $payload['amount'],
                (int) $payload['duration_days'],
                $payload['collateral_asset']
            );

            // Notify
            auth()->user()->notify(new LoanNotification(
                'Borrow Request',
                (float) $payload['amount'],
                $payload['asset'],
                'Created'
            ));

            return Response::successResponse($result['message'], $result['data'], 201);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Repay an active loan
     */
    public function repayLoan($loanId)
    {
        try {
            $result = $this->loanService->repayLoan(auth()->user(), $loanId);

            // Notify
            $loan = Loan::find($loanId);
            if ($loan) {
                auth()->user()->notify(new LoanNotification(
                    'Loan Repayment',
                    $loan->amount,
                    $loan->asset ?? 'N/A',
                    'Completed'
                ));
            }

            return Response::successResponse('Loan repaid successfully', $result);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Get user's lending offers
     */
    public function getMyLendingOffers()
    {
        $offers = LoanOffer::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return Response::successResponse('Lending offers retrieved successfully', $offers);
    }

    /**
     * Get user's borrow requests
     */
    public function getMyBorrowRequests()
    {
        $requests = LoanBorrowRequest::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return Response::successResponse('Borrow requests retrieved successfully', $requests);
    }

    /**
     * Get user's active loans (as borrower)
     */
    public function getMyLoansAsBorrower()
    {
        $loans = Loan::where('borrower_id', auth()->id())
            ->with(['lender'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Response::successResponse('Loans retrieved successfully', $loans);
    }

    /**
     * Get user's active loans (as lender)
     */
    public function getMyLoansAsLender()
    {
        $loans = Loan::where('lender_id', auth()->id())
            ->with(['borrower'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Response::successResponse('Loans retrieved successfully', $loans);
    }

    /**
     * Get loan details
     */
    public function getLoanDetails($loanId)
    {
        $loan = Loan::with(['borrower', 'lender', 'offer', 'request'])
            ->where(function ($query) {
                $query->where('borrower_id', auth()->id())
                    ->orWhere('lender_id', auth()->id());
            })
            ->findOrFail($loanId);

        return Response::successResponse('Loan details retrieved successfully', $loan);
    }

    /**
     * Calculate collateral requirement with interest rate information
     */
    public function calculateCollateral(Request $request)
    {
        $payload = $this->prepareLoanPayload($request);
        $supportedAssets = $this->loanService->getSupportedAssets();

        $validator = Validator::make($payload, [
            'asset' => ['required', 'string', Rule::in($supportedAssets)],
            'amount' => 'required|numeric|min:1',
            'collateral_asset' => ['required', 'string', Rule::in($supportedAssets)],
            'duration_days' => 'required|integer|in:30,60,90,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $collateralAmount = $this->loanService->calculateCollateralAmount(
                (float) $payload['amount'],
                $payload['asset'],
                $payload['collateral_asset']
            );

            // Get dynamic interest rate
            $interestRate = $this->loanService->getInterestRateForCollateral(
                $payload['collateral_asset'],
                (int) $payload['duration_days']
            );

            // Calculate total interest and repayment
            $months = ((int) $payload['duration_days']) / 30;
            $totalInterest = ((float) $payload['amount']) * ($interestRate / 100) * $months;
            $totalRepayment = ((float) $payload['amount']) + $totalInterest;

            return Response::successResponse('Collateral calculated successfully', [
                'loan_amount' => (float) $payload['amount'],
                'loan_asset' => $payload['asset'],
                'collateral_amount' => $collateralAmount,
                'collateral_asset' => $payload['collateral_asset'],
                'collateralization_ratio' => '125%',
                'duration_days' => (int) $payload['duration_days'],
                'interest_rate' => $interestRate,
                'interest_rate_type' => 'monthly',
                'total_interest' => round($totalInterest, 8),
                'total_repayment' => round($totalRepayment, 8),
            ]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Get collateral options with interest rates
     */
    public function getCollateralOptions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'duration_days' => 'nullable|integer|in:30,60,90,180',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation failed', $validator->errors(), 422);
        }

        $durationDays = $request->duration_days ?? 30;

        try {
            $options = $this->loanService->getCollateralOptions($durationDays);

            return Response::successResponse('Collateral options retrieved successfully', [
                'duration_days' => $durationDays,
                'options' => $options,
            ]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Get balance for a specific bond (loan or lending offer)
     */
    public function getBondBalance($loanId)
    {
        try {
            $balance = $this->loanService->getBondBalance(auth()->user(), $loanId);

            return Response::successResponse('Bond balance retrieved successfully', $balance);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Get total asset balances across all activities
     */
    public function getTotalAssetBalances()
    {
        try {
            $balances = $this->loanService->getUserAssetBalances(auth()->user());

            return Response::successResponse('Total balances retrieved successfully', $balances);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    private function prepareLoanPayload(Request $request): array
    {
        $payload = $request->all();

        foreach (['asset', 'collateral_asset'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->loanService->normalizeAssetSymbol($payload[$field]);
            }
        }

        if (array_key_exists('duration_days', $payload)) {
            $payload['duration_days'] = $this->normalizeDurationDays($payload['duration_days']);
        }

        return $payload;
    }

    private function normalizeDurationDays($duration)
    {
        if (is_string($duration)) {
            $trimmedDuration = trim($duration);

            if ($trimmedDuration !== '' && preg_match('/\d+/', $trimmedDuration, $matches)) {
                return (int) $matches[0];
            }
        }

        return $duration;
    }
}
