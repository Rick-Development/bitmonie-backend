<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Services\Promo\PromoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PromoController extends Controller
{
    public function __construct(
        protected PromoService $promoService
    ) {
    }

    /**
     * Display promo listing.
     */
  public function index(): View
{
    $page_title = 'Promo';

    $features = $this->promoService->getSupportedFeatures();

    $promos = Promo::query()
        ->with('features')
        ->latest()
        ->paginate(20);

    return view(
        'admin.sections.promo.index',
        compact('page_title', 'promos', 'features')
    );
}

    /**
     * Show create promo page.
     */
    public function create(): View
    {
        $page_title = 'Create Promo';

        $features = $this->promoService->getAvailableFeatures();

        return view(
            'admin.sections.promo.create',
            compact('page_title', 'features')
        );
    }

    /**
     * Store a newly created promo.
     *
     * Promo status is derived by PromoService from the
     * start_date and end_date values.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'start_date' => [
                'required',
                'date',
            ],

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            'features_id' => [
                'required',
                'array',
                'min:1',
            ],

            'features_id.*' => [
                'required',
                'integer',
                'distinct',
                'exists:features,id',
            ],

            'expiry_payment_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
        ]);

        try {
            $this->promoService->create(
                $data,
                (int) auth()->guard('admin')->id()
            );

            return redirect()
                ->route('admin.promo.index')
                ->with([
                    'success' => [
                        'Promo created successfully.',
                    ],
                ]);
        } catch (Throwable $e) {
            return back()
                ->withInput()
                ->with([
                    'error' => [
                        $e->getMessage(),
                    ],
                ]);
        }
    }

    /**
     * Show promo details.
     */
    public function show(int $id): View
    {
        $page_title = 'Promo Details';

        $promo = Promo::query()
            ->with('features')
            ->findOrFail($id);

        return view(
            'admin.sections.promo.details',
            compact('page_title', 'promo')
        );
    }

    /**
     * Show edit promo page.
     */
    public function edit(int $id): View
    {
        $page_title = 'Edit Promo';

        $promo = Promo::query()
            ->with('features')
            ->findOrFail($id);

        $features = $this->promoService->getAvailableFeaturesForPromo(
            $promo
        );

        return view(
            'admin.sections.promo.edit',
            compact('page_title', 'promo', 'features')
        );
    }

    /**
     * Update promo.
     *
     * Promo status is derived by PromoService from the
     * updated start_date and end_date values.
     */
    public function update(
        Request $request,
        int $id
    ): RedirectResponse {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'start_date' => [
                'required',
                'date',
            ],

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            'features_id' => [
                'required',
                'array',
                'min:1',
            ],

            'features_id.*' => [
                'required',
                'integer',
                'distinct',
                'exists:features,id',
            ],

            'expiry_payment_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
        ]);

        $promo = Promo::query()->findOrFail($id);

        try {
            $this->promoService->update(
                $promo,
                $data,
                (int) auth()->guard('admin')->id()
            );

            return redirect()
                ->route('admin.promo.index')
                ->with([
                    'success' => [
                        'Promo updated successfully.',
                    ],
                ]);
        } catch (Throwable $e) {
            return back()
                ->withInput()
                ->with([
                    'error' => [
                        $e->getMessage(),
                    ],
                ]);
        }
    }

    /**
     * Manually activate or deactivate a promo.
     *
     * PromoService remains responsible for enforcing all
     * date and feature-overlap rules.
     */
    public function statusUpdate(
        Request $request,
        int $id
    ): RedirectResponse {
        $data = $request->validate([
            'status' => [
                'required',
                'in:active,inactive',
            ],
        ]);

        $promo = Promo::query()->findOrFail($id);

        try {
            $this->promoService->updateStatus(
                $promo,
                $data['status'],
                (int) auth()->guard('admin')->id()
            );

            return back()->with([
                'success' => [
                    'Promo status updated successfully.',
                ],
            ]);
        } catch (Throwable $e) {
            return back()->with([
                'error' => [
                    $e->getMessage(),
                ],
            ]);
        }
    }

    /**
     * Delete promo.
     */
    public function destroy(int $id): RedirectResponse
    {
        $promo = Promo::query()->findOrFail($id);

        try {
            $this->promoService->delete(
                $promo,
                (int) auth()->guard('admin')->id()
            );

            return back()->with([
                'success' => [
                    'Promo deleted successfully.',
                ],
            ]);
        } catch (Throwable $e) {
            return back()->with([
                'error' => [
                    $e->getMessage(),
                ],
            ]);
        }
    }

    /**
     * Search promos.
     */
    public function search(Request $request): View
    {
        $data = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],

            'status' => [
                'nullable',
                'in:pending,active,inactive',
            ],

            'feature' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $page_title = 'Promo';

        $query = Promo::query();

        if (!empty($data['search'])) {
            $search = $data['search'];

            $query->where(function ($query) use ($search): void {
                $query
                    ->where(
                        'name',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'description',
                        'like',
                        '%' . $search . '%'
                    );
            });
        }

        if (!empty($data['status'])) {
            $query->where(
                'status',
                $data['status']
            );
        }

        if (!empty($data['feature'])) {
            $feature = $data['feature'];

            $query->whereHas(
                'features',
                function ($query) use ($feature): void {
                    $query->where(
                        'name',
                        'like',
                        '%' . $feature . '%'
                    );
                }
            );
        }

        $promos = $query
            ->with('features')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view(
            'admin.sections.promo.index',
            compact('page_title', 'promos')
        );
    }

    /**
     * Promo statistics.
     */
    public function statistics(): View
    {
        $page_title = 'Promo Statistics';

        $statistics = $this->promoService->getStatistics();

        return view(
            'admin.sections.promo.statistics',
            compact('page_title', 'statistics')
        );
    }

    /**
     * List supported promo features.
     */
    public function features(): View
    {
        $page_title = 'Promo Features';

        $features = $this->promoService->getSupportedFeatures();

        return view(
            'admin.sections.promo.features',
            compact('page_title', 'features')
        );
    }
}