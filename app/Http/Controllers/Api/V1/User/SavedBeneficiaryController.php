<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\Beneficiary;
use App\Models\TransactionMethod;
use App\Support\UserScopedCache;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SavedBeneficiaryController extends Controller
{
    protected int $cacheTtl = 300;

    protected array $types = [
        'bank_transfer',
        'withdrawal',
        'bill_payment',
        'crypto',
        'wallet_transfer',
        'virtual_card',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $type = $request->query('type');
        $search = trim((string) $request->query('search', ''));
        $favorite = $request->query('favorite');
        $cacheKey = 'beneficiaries:list:' . md5(json_encode([
            'type' => $type,
            'search' => $search,
            'favorite' => $favorite,
            'page' => $request->query('page', 1),
        ]));

        $payload = UserScopedCache::remember($this->cacheTags($user->id), $cacheKey, $this->cacheTtl, function () use ($user, $type, $search, $favorite) {
            return $this->queryForUser($user->id, $type, $search, $favorite)
                ->paginate(25)
                ->through(fn (Beneficiary $beneficiary) => $this->present($beneficiary));
        });

        return Response::successResponse('Beneficiaries fetched successfully', $payload);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $methodId = $this->fallbackTransactionMethodId();

        $beneficiary = Beneficiary::create([
            'transaction_method_id' => $methodId,
            'user_id' => $request->user()->id,
            'slug' => (string) Str::uuid(),
            'beneficiary_name' => $validated['beneficiary_name'],
            'transaction_type' => $validated['transaction_type'],
            'details' => $validated['details'] ?? [],
            'is_favorite' => (bool) ($validated['is_favorite'] ?? false),
            'pinned_at' => !empty($validated['is_favorite']) ? now() : null,
            'info' => (object) [
                'account_holder_name' => $validated['beneficiary_name'],
                'beneficiary_subtype' => $validated['transaction_type'],
                'details' => $validated['details'] ?? [],
            ],
        ]);

        $this->flushCache($request->user()->id);

        return Response::successResponse('Beneficiary saved successfully', $this->present($beneficiary), 201);
    }

    public function update(Request $request, int $id)
    {
        $beneficiary = Beneficiary::where('user_id', $request->user()->id)->findOrFail($id);
        $validated = $this->validatePayload($request, true);
        $updates = [];

        foreach (['beneficiary_name', 'transaction_type', 'details'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updates[$field] = $validated[$field];
            }
        }

        if (array_key_exists('is_favorite', $validated)) {
            $updates['is_favorite'] = (bool) $validated['is_favorite'];
            $updates['pinned_at'] = $updates['is_favorite'] ? ($beneficiary->pinned_at ?? now()) : null;
        }

        if (isset($updates['beneficiary_name']) || isset($updates['transaction_type']) || isset($updates['details'])) {
            $updates['info'] = (object) [
                'account_holder_name' => $updates['beneficiary_name'] ?? $beneficiary->beneficiary_name,
                'beneficiary_subtype' => $updates['transaction_type'] ?? $beneficiary->transaction_type,
                'details' => $updates['details'] ?? $beneficiary->details ?? [],
            ];
        }

        $beneficiary->update($updates);
        $this->flushCache($request->user()->id);

        return Response::successResponse('Beneficiary updated successfully', $this->present($beneficiary->refresh()));
    }

    public function destroy(Request $request, int $id)
    {
        $beneficiary = Beneficiary::where('user_id', $request->user()->id)->findOrFail($id);
        $beneficiary->delete();
        $this->flushCache($request->user()->id);

        return Response::successResponse('Beneficiary deleted successfully', []);
    }

    public function byType(Request $request, string $type)
    {
        if (!in_array($type, $this->types, true)) {
            return Response::errorResponse('Invalid beneficiary transaction type.', ['allowed_types' => $this->types], 422);
        }

        $request->query->set('type', $type);
        return $this->index($request);
    }

    protected function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'beneficiary_name' => [$required, 'string', 'max:150'],
            'transaction_type' => [$required, 'string', Rule::in($this->types)],
            'details' => [$partial ? 'sometimes' : 'required', 'array'],
            'is_favorite' => ['sometimes', 'boolean'],
        ]);
    }

    protected function queryForUser(int $userId, ?string $type = null, string $search = '', mixed $favorite = null)
    {
        return Beneficiary::query()
            ->where('user_id', $userId)
            ->when($type, fn ($query) => $query->where('transaction_type', $type))
            ->when($favorite !== null && $favorite !== '', fn ($query) => $query->where('is_favorite', filter_var($favorite, FILTER_VALIDATE_BOOLEAN)))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('beneficiary_name', 'like', "%{$search}%")
                        ->orWhere('transaction_type', 'like', "%{$search}%")
                        ->orWhere('details', 'like', "%{$search}%")
                        ->orWhere('info', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('is_favorite')
            ->orderByDesc('pinned_at')
            ->orderByDesc('id');
    }

    protected function present(Beneficiary $beneficiary): array
    {
        return [
            'id' => $beneficiary->id,
            'beneficiary_name' => $beneficiary->beneficiary_name ?? data_get($beneficiary->info, 'account_holder_name'),
            'transaction_type' => $beneficiary->transaction_type ?? data_get($beneficiary->info, 'beneficiary_subtype'),
            'details' => $beneficiary->details ?? data_get($beneficiary->info, 'details', []),
            'is_favorite' => (bool) $beneficiary->is_favorite,
            'pinned_at' => optional($beneficiary->pinned_at)->toDateTimeString(),
            'created_at' => optional($beneficiary->created_at)->toDateTimeString(),
            'updated_at' => optional($beneficiary->updated_at)->toDateTimeString(),
        ];
    }

    protected function fallbackTransactionMethodId(): int
    {
        $methodId = TransactionMethod::where('slug', 'saved-beneficiary')->value('id')
            ?? TransactionMethod::query()->value('id');

        if ($methodId) {
            return (int) $methodId;
        }

        $method = TransactionMethod::create([
            'name' => 'Saved Beneficiary',
            'slug' => 'saved-beneficiary',
            'status' => true,
        ]);

        return (int) $method->id;
    }

    protected function cacheTags(int $userId): array
    {
        return ['beneficiaries', "user:{$userId}"];
    }

    protected function flushCache(int $userId): void
    {
        UserScopedCache::flush($this->cacheTags($userId));
    }
}
