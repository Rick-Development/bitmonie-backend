<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\AutosavePlan;
use App\Services\AutosaveService;
use App\Support\UserScopedCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AutosaveController extends Controller
{
    public function __construct(protected AutosaveService $autosaveService)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $cacheKey = 'autosave:list:' . md5(json_encode($request->query()));
        $payload = UserScopedCache::remember($this->cacheTags($user->id), $cacheKey, 300, function () use ($user, $request) {
            return AutosavePlan::withCount('transactions')
                ->where('user_id', $user->id)
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->when($request->query('mode'), fn ($query, $mode) => $query->where('mode', $mode))
                ->orderByDesc('id')
                ->paginate(20)
                ->through(fn (AutosavePlan $plan) => $this->presentPlan($plan));
        });

        return Response::successResponse('AutoSave plans fetched successfully', $payload);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePlan($request);
        $plan = $this->autosaveService->createPlan($request->user(), $validated);
        $this->flushCache($request->user()->id);

        return Response::successResponse('AutoSave plan created successfully', $this->presentPlan($plan), 201);
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();
        $cacheKey = "autosave:plan:{$id}";
        $payload = UserScopedCache::remember($this->cacheTags($user->id, $id), $cacheKey, 300, function () use ($user, $id) {
            $plan = AutosavePlan::withCount('transactions')->where('user_id', $user->id)->findOrFail($id);
            return $this->presentPlan($plan);
        });

        return Response::successResponse('AutoSave plan fetched successfully', $payload);
    }

    public function update(Request $request, int $id)
    {
        $plan = AutosavePlan::where('user_id', $request->user()->id)->findOrFail($id);
        $validated = $this->validatePlan($request, true);
        $plan = $this->autosaveService->updatePlan($plan, $validated);
        $this->flushCache($request->user()->id, $plan->id);

        return Response::successResponse('AutoSave plan updated successfully', $this->presentPlan($plan));
    }

    public function destroy(Request $request, int $id)
    {
        $plan = AutosavePlan::where('user_id', $request->user()->id)->findOrFail($id);
        $plan = $this->autosaveService->cancelPlan($plan);
        $this->flushCache($request->user()->id, $plan->id);

        return Response::successResponse('AutoSave plan cancelled successfully', $this->presentPlan($plan));
    }

    public function transactions(Request $request, int $id)
    {
        $user = $request->user();
        $cacheKey = 'autosave:transactions:' . $id . ':' . md5(json_encode($request->query()));
        $payload = UserScopedCache::remember($this->cacheTags($user->id, $id), $cacheKey, 300, function () use ($user, $id) {
            $plan = AutosavePlan::where('user_id', $user->id)->findOrFail($id);
            return $plan->transactions()
                ->orderByDesc('id')
                ->paginate(30);
        });

        return Response::successResponse('AutoSave transactions fetched successfully', $payload);
    }

    protected function validatePlan(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:150'],
            'mode' => [$required, Rule::in(['scheduled', 'percentage', 'both'])],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'required_if:mode,scheduled,both'],
            'percentage' => ['nullable', 'numeric', 'min:0.01', 'max:100', 'required_if:mode,percentage,both'],
            'frequency' => ['nullable', Rule::in(['daily', 'weekly', 'monthly']), 'required_if:mode,scheduled,both'],
            'goal_amount' => ['nullable', 'numeric', 'min:0.01'],
            'maturity_date' => ['nullable', 'date', 'after:today'],
            'status' => ['sometimes', Rule::in(['active', 'paused', 'cancelled'])],
        ]);
    }

    protected function presentPlan(AutosavePlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'mode' => $plan->mode,
            'amount' => $plan->amount,
            'percentage' => $plan->percentage,
            'frequency' => $plan->frequency,
            'goal_amount' => $plan->goal_amount,
            'maturity_date' => optional($plan->maturity_date)->toDateString(),
            'status' => $plan->status,
            'balance' => $plan->balance,
            'next_due_at' => optional($plan->next_due_at)->toDateTimeString(),
            'last_deducted_at' => optional($plan->last_deducted_at)->toDateTimeString(),
            'transactions_count' => $plan->transactions_count ?? null,
            'created_at' => optional($plan->created_at)->toDateTimeString(),
            'updated_at' => optional($plan->updated_at)->toDateTimeString(),
        ];
    }

    protected function cacheTags(int $userId, ?int $planId = null): array
    {
        $tags = ['autosave', "user:{$userId}"];
        if ($planId) {
            $tags[] = "autosave-plan:{$planId}";
        }

        return $tags;
    }

    protected function flushCache(int $userId, ?int $planId = null): void
    {
        UserScopedCache::flush($this->cacheTags($userId));

        if ($planId) {
            UserScopedCache::flush($this->cacheTags($userId, $planId));
        }
    }
    public function active(Request $request){
        $user = $request->user();
        $cacheKey = 'autosave:list:' . md5(json_encode($request->query()));
        $payload = UserScopedCache::remember($this->cacheTags($user->id), $cacheKey, 300, function () use ($user, $request) {
            return AutosavePlan::withCount('transactions')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->paginate(20);
        });

        return Response::successResponse('AutoSave plans fetched successfully', $payload);

    }
}
