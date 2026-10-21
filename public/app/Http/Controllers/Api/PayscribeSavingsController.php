<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\SavingsAccount;
use App\Models\UserWallet;
use App\Services\Savings\Money;
use App\Services\Savings\SavingsException;
use App\Services\Savings\SavingsManager;
use App\Services\Savings\SavingsPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Adapter controller for legacy Payscribe Savings routes (/api/user/payscribe/...)
 * seamlessly bridging them to the Unified Savings Ledger system.
 */
class PayscribeSavingsController extends Controller
{
    public function __construct(private readonly SavingsManager $savings)
    {
    }

    /**
     * Map a unified SavingsAccount model to the legacy payload structure
     * expected by older frontend clients while preserving new unified fields.
     */
    private function formatLegacyTarget(SavingsAccount $account): array
    {
        return [
            'id' => $account->id,
            'plan_id' => (string) $account->id,
            'user_id' => $account->user_id,
            'savings_id' => $account->id,
            'target_title' => $account->title,
            'title' => $account->title,
            'target_amount' => Money::norm($account->target_amount ?? '0.00'),
            'balance' => Money::norm($account->balance ?? '0.00'),
            'available_balance' => Money::norm($account->availableBalance()),
            'status' => strtolower($account->status ?? 'active'),
            'currency' => $account->currency ?? 'NGN',
            'start_date' => $account->start_date?->toDateString(),
            'deadline' => $account->deadline?->toDateString(),
            'created_at' => $account->created_at?->toIso8601String(),
            'updated_at' => $account->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Resolve account by identifier (supports numeric account ID or title)
     */
    private function resolveTargetAccount($user, string|int $identifier): SavingsAccount
    {
        if (is_numeric($identifier)) {
            $account = SavingsAccount::where('user_id', $user->id)
                ->where('product', SavingsAccount::TARGET)
                ->where('id', (int) $identifier)
                ->first();

            if ($account) {
                return $account;
            }
        }

        // Search by title or fallback legacy match
        $account = SavingsAccount::where('user_id', $user->id)
            ->where('product', SavingsAccount::TARGET)
            ->where(function ($q) use ($identifier) {
                $q->where('title', $identifier)
                  ->orWhereRaw('LOWER(title) = ?', [strtolower((string) $identifier)])
                  ->orWhere('legacy_ref', 'target:' . $identifier);
            })
            ->first();

        if (!$account) {
            throw SavingsException::notFound('Target savings account');
        }

        return $account;
    }

    /**
     * Create Savings Goal
     * POST /api/user/payscribe/create-savings
     */
    public function createSavings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'target_title' => 'required_without:title|string|max:120',
            'title' => 'sometimes|string|max:120',
            'target_amount' => 'required|numeric|min:1',
            'amount' => 'sometimes|numeric|min:0', // initial deposit amount if supplied
            'deadline' => 'sometimes|nullable|date',
            'description' => 'sometimes|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();
        $title = trim((string) ($request->target_title ?: $request->title));
        $targetAmount = Money::parse($request->target_amount, 'target_amount');

        // Check if an active target with same title already exists
        $existing = SavingsAccount::where('user_id', $user->id)
            ->where('product', SavingsAccount::TARGET)
            ->whereIn('status', [SavingsAccount::ACTIVE, SavingsAccount::PAUSED])
            ->whereRaw('LOWER(title) = ?', [strtolower($title)])
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'failed',
                'message' => 'You already have an active savings target with this title.',
                'data' => $this->formatLegacyTarget($existing),
            ], 409);
        }

        try {
            $created = $this->savings->createTarget($user, [
                'title' => $title,
                'target_amount' => $targetAmount,
                'description' => $request->description,
                'deadline' => $request->deadline,
            ]);

            $account = $created['account'];

            // Optional: If initial funding amount was sent, contribute immediately from wallet
            $initialDeposit = $request->amount ? Money::parse($request->amount, 'amount') : '0.00';
            if (Money::cmp($initialDeposit, '0.00') > 0) {
                $idempotencyKey = (string) ($request->header('Idempotency-Key') ?: ('legacy-init-' . Str::uuid()));
                $this->savings->contribute($user, $account->id, $initialDeposit, 'wallet', $idempotencyKey);
                $account = $account->refresh();
            }

            return Response::success('Savings account created successfully.', $this->formatLegacyTarget($account), 201);
        } catch (SavingsException $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus);
        } catch (\Throwable $e) {
            Log::error('legacy_savings.create_failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Deposit into Target Savings
     * POST /api/user/payscribe/deposit-savings
     */
    public function depositSavings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required',
            'amount' => 'required|numeric|min:50',
            'narration' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();
        $amount = Money::parse($request->amount, 'amount');

        try {
            $account = $this->resolveTargetAccount($user, $request->plan_id);

            // Execute deposit via unified SavingsManager / SavingsLedger
            $idempotencyKey = (string) ($request->header('Idempotency-Key') ?: ('legacy-dep-' . Str::uuid()));
            $result = $this->savings->contribute($user, $account->id, $amount, 'wallet', $idempotencyKey);

            $account = $account->refresh();
            $wallet = UserWallet::where('user_id', $user->id)
                ->where('currency_code', config('savings.currency', 'NGN'))
                ->first();

            // Total target savings for this user across all target accounts
            $totalTargetSavings = SavingsAccount::where('user_id', $user->id)
                ->where('product', SavingsAccount::TARGET)
                ->sum('balance');

            return Response::success('Savings top-up successful', [
                'wallet_balance' => $wallet ? (string) $wallet->balance : '0.00',
                'savings' => $this->formatLegacyTarget($account),
                'total_savings' => Money::norm((string) $totalTargetSavings),
                'transaction' => isset($result['entry']) ? SavingsPresenter::entry($result['entry']) : null,
            ], 200);
        } catch (SavingsException $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus);
        } catch (\Throwable $e) {
            Log::error('legacy_savings.deposit_failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Withdraw from Target Savings
     * POST /api/user/payscribe/withdraw-savings
     */
    public function withdrawFromSavings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required',
            'amount' => 'required|numeric|min:50',
            'narration' => 'sometimes|nullable|string',
            'acknowledge_fee' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();
        $amount = Money::parse($request->amount, 'amount');

        try {
            $account = $this->resolveTargetAccount($user, $request->plan_id);

            $idempotencyKey = (string) ($request->header('Idempotency-Key') ?: ('legacy-wdr-' . Str::uuid()));

            // If account is completed or requested full withdrawal upon maturity/closure
            $result = $this->savings->withdraw($user, $account->id, $amount, $idempotencyKey);
            $account = $account->refresh();

            $totalTargetSavings = SavingsAccount::where('user_id', $user->id)
                ->where('product', SavingsAccount::TARGET)
                ->sum('balance');

            return Response::success('Withdrawal successful', [
                'details' => $this->formatLegacyTarget($account),
                'total_savings' => Money::norm((string) $totalTargetSavings),
                'transaction' => isset($result['entry']) ? SavingsPresenter::entry($result['entry']) : null,
            ], 200);
        } catch (SavingsException $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus);
        } catch (\Throwable $e) {
            Log::error('legacy_savings.withdraw_failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * List user savings targets
     * GET /api/user/payscribe/list-savings
     */
    public function listUserSavings(): JsonResponse
    {
        $user = auth()->user();

        $accounts = SavingsAccount::where('user_id', $user->id)
            ->where('product', SavingsAccount::TARGET)
            ->orderBy('id', 'desc')
            ->get();

        if ($accounts->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No savings account found.',
                'data' => [],
            ], 200);
        }

        $formatted = $accounts->map(fn ($acc) => $this->formatLegacyTarget($acc))->values()->all();
        $totalSavings = SavingsAccount::where('user_id', $user->id)
            ->where('product', SavingsAccount::TARGET)
            ->sum('balance');

        return Response::success('Savings accounts retrieved successfully.', [
            'details' => $formatted,
            'total_savings' => Money::norm((string) $totalSavings),
        ]);
    }
}