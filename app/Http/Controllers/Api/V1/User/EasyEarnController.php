<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\EasyEarnPlan;
use App\Services\EasyEarnService;
use App\Support\UserScopedCache;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class EasyEarnController extends Controller
{
    public function __construct(protected EasyEarnService $easyEarnService)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $cacheKey = 'easyearn:list:' . md5(json_encode($request->query()));
        $payload = UserScopedCache::remember(['easyearn', "user:{$user->id}"], $cacheKey, 300, function () use ($user, $request) {
            return EasyEarnPlan::where('user_id', $user->id)
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->orderByDesc('id')
                ->paginate(20)
                ->through(fn (EasyEarnPlan $plan) => $this->presentPlan($plan));
        });

        return Response::successResponse('EasyEarn plans fetched successfully', $payload);
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.00000001'],
            'term_days' => ['nullable', 'integer', 'min:1'],
        ]);

        return Response::successResponse('EasyEarn preview fetched successfully', $this->easyEarnService->preview((string) $validated['amount'], $validated['term_days'] ?? null));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.00000001'],
            'term_days' => ['nullable', 'integer', 'min:1'],
            'preview_only' => ['sometimes', 'boolean'],
        ]);

        if (!empty($validated['preview_only'])) {
            return Response::successResponse('EasyEarn preview fetched successfully', $this->easyEarnService->preview((string) $validated['amount'], $validated['term_days'] ?? null));
        }

        try {
            $plan = $this->easyEarnService->createPlan($request->user(), (string) $validated['amount'], $validated['term_days'] ?? null);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        return Response::successResponse('EasyEarn plan created successfully', $this->presentPlan($plan), 201);
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();
        $cacheKey = "easyearn:plan:{$id}";
        $payload = UserScopedCache::remember(['easyearn', "user:{$user->id}", "easyearn-plan:{$id}"], $cacheKey, 300, function () use ($user, $id) {
            $plan = EasyEarnPlan::where('user_id', $user->id)->findOrFail($id);
            return $this->presentPlan($plan);
        });

        return Response::successResponse('EasyEarn plan fetched successfully', $payload);
    }

    public function withdraw(Request $request, int $id)
    {
        $plan = EasyEarnPlan::where('user_id', $request->user()->id)->findOrFail($id);

        try {
            $plan = $this->easyEarnService->withdraw($plan);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['withdraw' => $exception->getMessage()]);
        }

        return Response::successResponse('EasyEarn withdrawal completed successfully', $this->presentPlan($plan));
    }

    public function transactions(Request $request)
    {
        $user = $request->user();
        $cacheKey = 'easyearn:transactions:' . md5(json_encode($request->query()));
        $payload = UserScopedCache::remember(['easyearn', "user:{$user->id}"], $cacheKey, 300, function () use ($user, $request) {
            return $user->easyEarnTransactions()
                ->when($request->query('type'), fn ($query, $type) => $query->where('type', $type))
                ->when($request->query('plan_id'), fn ($query, $planId) => $query->where('easyearn_plan_id', $planId))
                ->orderByDesc('id')
                ->paginate(30);
        });

        return Response::successResponse('EasyEarn transactions fetched successfully', $payload);
    }

    protected function presentPlan(EasyEarnPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'usdt_amount_deposited' => $plan->usdt_amount_deposited,
            'expected_return' => $plan->expected_return,
            'return_multiplier' => $plan->return_multiplier,
            'maturity_date' => optional($plan->maturity_date)->toDateTimeString(),
            'status' => $plan->status,
            'terms' => data_get($plan->metadata, 'terms'),
            'early_withdrawal_enabled' => (bool) data_get($plan->metadata, 'early_withdrawal_enabled', false),
            'created_at' => optional($plan->created_at)->toDateTimeString(),
            'updated_at' => optional($plan->updated_at)->toDateTimeString(),
        ];
    }
    public function terminate(Request $request, int $id)
{
    $plan = EasyEarnPlan::where('user_id', $request->user()->id)
        ->findOrFail($id);

    try {
        $plan = $this->easyEarnService->terminate($plan);
    } catch (RuntimeException $e) {
        throw ValidationException::withMessages([
            'terminate' => $e->getMessage()
        ]);
    }

    return Response::successResponse(
        'EasyEarn plan terminated successfully',
        $this->presentPlan($plan)
    );
}
}
