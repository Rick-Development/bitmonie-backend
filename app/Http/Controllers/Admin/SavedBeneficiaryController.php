<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Support\UserScopedCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SavedBeneficiaryController extends Controller
{
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
        $page_title = 'Saved Beneficiaries';
        $beneficiaries = $this->query($request)->paginate(25)->withQueryString();
        $types = $this->types;

        return view('admin.sections.beneficiaries.index', compact('page_title', 'beneficiaries', 'types'));
    }

    public function show(int $id)
    {
        $page_title = 'Beneficiary Details';
        $beneficiary = Beneficiary::with('user')->findOrFail($id);
        $types = $this->types;

        return view('admin.sections.beneficiaries.show', compact('page_title', 'beneficiary', 'types'));
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'beneficiary_name' => ['required', 'string', 'max:150'],
            'transaction_type' => ['required', 'string', Rule::in($this->types)],
            'details' => ['nullable', 'string'],
            'is_favorite' => ['nullable', 'boolean'],
        ]);

        $beneficiary = Beneficiary::findOrFail($id);
        $details = $this->decodeJson($validated['details'] ?? '{}');
        $beneficiary->update([
            'beneficiary_name' => $validated['beneficiary_name'],
            'transaction_type' => $validated['transaction_type'],
            'details' => $details,
            'is_favorite' => (bool) ($validated['is_favorite'] ?? false),
            'pinned_at' => !empty($validated['is_favorite']) ? ($beneficiary->pinned_at ?? now()) : null,
            'info' => (object) [
                'account_holder_name' => $validated['beneficiary_name'],
                'beneficiary_subtype' => $validated['transaction_type'],
                'details' => $details,
            ],
        ]);

        $this->flushUserCache($beneficiary->user_id);

        return back()->with(['success' => ['Beneficiary updated successfully.']]);
    }

    public function destroy(int $id)
    {
        $beneficiary = Beneficiary::findOrFail($id);
        $userId = $beneficiary->user_id;
        $beneficiary->delete();
        $this->flushUserCache($userId);

        return redirect()->route('admin.beneficiaries.index')->with(['success' => ['Beneficiary deleted successfully.']]);
    }

    public function export(Request $request)
    {
        $filename = 'beneficiaries-' . now()->format('Ymd-His') . '.csv';
        $beneficiaries = $this->query($request)->get();

        return response()->streamDownload(function () use ($beneficiaries) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'User', 'Email', 'Name', 'Type', 'Favorite', 'Details', 'Created At']);

            foreach ($beneficiaries as $beneficiary) {
                fputcsv($handle, [
                    $beneficiary->id,
                    optional($beneficiary->user)->fullname,
                    optional($beneficiary->user)->email,
                    $beneficiary->beneficiary_name,
                    $beneficiary->transaction_type,
                    $beneficiary->is_favorite ? 'Yes' : 'No',
                    json_encode($beneficiary->details ?? data_get($beneficiary->info, 'details', [])),
                    optional($beneficiary->created_at)->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function query(Request $request)
    {
        return Beneficiary::with('user')
            ->when($request->query('user'), function ($query, $user) {
                $query->whereHas('user', function ($userQuery) use ($user) {
                    $userQuery->where('email', 'like', "%{$user}%")
                        ->orWhere('firstname', 'like', "%{$user}%")
                        ->orWhere('lastname', 'like', "%{$user}%")
                        ->orWhere('username', 'like', "%{$user}%");
                });
            })
            ->when($request->query('transaction_type'), fn ($query, $type) => $query->where('transaction_type', $type))
            ->when($request->query('from'), fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($request->query('to'), fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('beneficiary_name', 'like', "%{$search}%")
                        ->orWhere('transaction_type', 'like', "%{$search}%")
                        ->orWhere('details', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id');
    }

    protected function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);
        abort_if(!is_array($decoded), 422, 'Details must be valid JSON.');

        return $decoded;
    }

    protected function flushUserCache(?int $userId): void
    {
        if ($userId) {
            UserScopedCache::flush(['beneficiaries', "user:{$userId}"]);
        }
    }
}
