<?php

declare(strict_types=1);

namespace App\Services\Promo;

use App\Models\Feature;
use App\Models\Promo;
use App\Models\PromoFeature;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PromoService
{
    /*
    |--------------------------------------------------------------------------
    | ADMINISTRATION
    |--------------------------------------------------------------------------
    */

    /**
     * Create a promo and assign its selected features.
     *
     * Status is derived from the configured dates:
     *
     * - Future start date => pending
     * - Currently running  => active
     * - Already expired    => inactive
     *
     * The status supplied by the request is intentionally ignored.
     */
    public function create(
        array $data,
        int $adminId
    ): Promo {
        return DB::transaction(function () use (
            $data,
            $adminId
        ): Promo {
            $this->validateConfiguration($data);

            $featureIds = $this->normaliseFeatureIds(
                $data['features_id']
            );

            $startDate = Carbon::parse(
                (string) $data['start_date']
            );

            $endDate = Carbon::parse(
                (string) $data['end_date']
            );

            /*
             * Lock the selected feature records before checking
             * their availability. This protects against concurrent
             * promo creation/update requests.
             */
            $this->lockFeatures($featureIds);

            /*
             * Check whether any selected feature is already assigned
             * to another promo whose date range overlaps this promo.
             *
             * Pending promos are included because they represent
             * future scheduled usage of the feature.
             */
            $this->ensureFeaturesAreAvailableForPeriod(
                $featureIds,
                $startDate,
                $endDate
            );

            /*
             * Status is always derived from the dates.
             */
            $status = $this->determineStatus(
                $startDate,
                $endDate
            );

            $promo = Promo::query()->create([
                'name' => trim((string) $data['name']),
                'description' => $data['description'] ?? null,
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'expiry_payment_percentage' =>
                    $data['expiry_payment_percentage'] ?? null,
                'created_by' => $adminId,
            ]);

            $this->syncFeatures(
                $promo,
                $featureIds
            );

            return $promo->fresh([
                'features',
            ]);
        });
    }

    /**
     * Update an existing promo.
     *
     * Status is recalculated from the new dates.
     *
     * The request does not control the lifecycle status.
     */
    public function update(
        Promo $promo,
        array $data,
        int $adminId
    ): Promo {
        return DB::transaction(function () use (
            $promo,
            $data,
            $adminId
        ): Promo {
            $this->validateConfiguration($data);

            /*
             * Lock the promo before modifying it.
             */
            $promo = Promo::query()
                ->lockForUpdate()
                ->findOrFail($promo->id);

            $featureIds = $this->normaliseFeatureIds(
                $data['features_id']
            );

            $startDate = Carbon::parse(
                (string) $data['start_date']
            );

            $endDate = Carbon::parse(
                (string) $data['end_date']
            );

            /*
             * Lock selected features before checking assignments.
             */
            $this->lockFeatures($featureIds);

            /*
             * Check for date-range conflicts while excluding
             * the current promo itself.
             */
            $this->ensureFeaturesAreAvailableForPeriod(
                $featureIds,
                $startDate,
                $endDate,
                $promo->id
            );

            /*
             * Status is derived from the new dates.
             */
            $status = $this->determineStatus(
                $startDate,
                $endDate
            );

            /*
             * If the promo was previously inactive, it may have had
             * its feature assignments released. syncFeatures() below
             * restores the selected assignments.
             */
            $promo->update([
                'name' => trim((string) $data['name']),
                'description' => $data['description'] ?? null,
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'expiry_payment_percentage' =>
                    $data['expiry_payment_percentage'] ?? null,
                'updated_by' => $adminId,
            ]);

            $this->syncFeatures(
                $promo,
                $featureIds
            );

            return $promo->fresh([
                'features',
            ]);
        });
    }

    /**
     * Manually change a promo's status.
     *
     * Lifecycle statuses:
     *
     * - pending
     * - active
     * - inactive
     *
     * Status is normally managed automatically from the dates.
     *
     * Manual activation:
     * - cannot happen before start_date
     * - cannot happen after end_date
     * - must pass feature overlap validation
     *
     * Manual pending:
     * - only allowed when start_date is in the future
     *
     * Manual inactive:
     * - always allowed
     * - releases feature assignments
     */
    public function updateStatus(
        Promo $promo,
        string $status,
        int $adminId
    ): Promo {
        if (!in_array(
            $status,
            [
                'pending',
                'active',
                'inactive',
            ],
            true
        )) {
            throw new InvalidArgumentException(
                'Invalid promo status.'
            );
        }

        return DB::transaction(function () use (
            $promo,
            $status,
            $adminId
        ): Promo {
            $promo = Promo::query()
                ->lockForUpdate()
                ->findOrFail($promo->id);

            $now = now();

            /*
             * An expired promo can never be active or pending.
             */
            if (
                $promo->end_date->lt($now) &&
                $status !== 'inactive'
            ) {
                throw new InvalidArgumentException(
                    'An expired promo must remain inactive.'
                );
            }

            /*
             * A future promo must remain pending.
             */
            if (
                $promo->start_date->gt($now) &&
                $status === 'active'
            ) {
                throw new InvalidArgumentException(
                    'A promo with a future start date must remain pending.'
                );
            }

            /*
             * A promo that has already started cannot be moved
             * back to pending.
             */
            if (
                $status === 'pending' &&
                !$promo->start_date->gt($now)
            ) {
                throw new InvalidArgumentException(
                    'A promo whose start date has arrived cannot be changed to pending.'
                );
            }

            /*
             * Activating a promo requires a fresh overlap check.
             */
            if ($status === 'active') {
                $featureIds = $promo->promoFeatures()
                    ->pluck('feature_id')
                    ->map(
                        static fn ($id): int => (int) $id
                    )
                    ->all();

                if ($featureIds === []) {
                    throw new InvalidArgumentException(
                        'A promo must have at least one feature before it can be activated.'
                    );
                }

                $this->lockFeatures($featureIds);

                $this->ensureFeaturesAreAvailableForPeriod(
                    $featureIds,
                    $promo->start_date,
                    $promo->end_date,
                    $promo->id
                );
            }

            $promo->update([
                'status' => $status,
                'updated_by' => $adminId,
            ]);

            /*
             * Once manually deactivated, release its feature
             * assignments.
             */
            if ($status === 'inactive') {
                $this->releaseFeatures($promo);
            }

            return $promo->fresh([
                'features',
            ]);
        });
    }

    /**
     * Delete a promo.
     */
    public function delete(
        Promo $promo,
        int $adminId
    ): void {
        DB::transaction(function () use (
            $promo,
            $adminId
        ): void {
            $promo = Promo::query()
                ->lockForUpdate()
                ->findOrFail($promo->id);

            /*
             * Release all feature assignments.
             */
            $this->releaseFeatures($promo);

            /*
             * Preserve the administrator who deleted the promo.
             */
            $promo->update([
                'deleted_by' => $adminId,
            ]);

            $promo->delete();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | FEATURE MANAGEMENT
    |--------------------------------------------------------------------------
    */

    /**
     * Get all master features.
     */
    public function getSupportedFeatures(): Collection
    {
        return Feature::query()
            ->orderBy('name')
            ->get();
    }

    /**
     * Get features that are currently not assigned to an active promo.
     *
     * This method is useful for displaying the currently available
     * features in the admin UI.
     *
     * NOTE:
     * This does not perform date-specific availability checking.
     * For a specific requested promo period, use
     * ensureFeaturesAreAvailableForPeriod().
     */
    public function getAvailableFeatures(): Collection
    {
        return Feature::query()
            ->whereDoesntHave(
                'promoFeatures',
                function (Builder $query): void {
                    $query->whereHas(
                        'promo',
                        function (Builder $promoQuery): void {
                            $this->applyValidPromoScope(
                                $promoQuery
                            );
                        }
                    );
                }
            )
            ->orderBy('name')
            ->get();
    }

    /**
     * Get features currently available while editing a promo.
     *
     * The current promo's own features are always included.
     */
    public function getAvailableFeaturesForPromo(
        Promo $promo
    ): Collection {
        return Feature::query()
            ->where(function (Builder $query) use ($promo): void {
                /*
                 * Include features already assigned to this promo.
                 */
                $query->whereHas(
                    'promoFeatures',
                    function (Builder $featureQuery) use ($promo): void {
                        $featureQuery->where(
                            'promo_id',
                            $promo->id
                        );
                    }
                );

                /*
                 * Also include features not assigned to another
                 * currently active promo.
                 */
                $query->orWhereDoesntHave(
                    'promoFeatures',
                    function (Builder $featureQuery) use ($promo): void {
                        $featureQuery->whereHas(
                            'promo',
                            function (Builder $promoQuery) use ($promo): void {
                                $this->applyValidPromoScope(
                                    $promoQuery
                                );

                                $promoQuery->where(
                                    'id',
                                    '!=',
                                    $promo->id
                                );
                            }
                        );
                    }
                );
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Replace the features assigned to a promo.
     *
     * @param array<int, int> $featureIds
     */
    protected function syncFeatures(
        Promo $promo,
        array $featureIds
    ): void {
        PromoFeature::query()
            ->where(
                'promo_id',
                $promo->id
            )
            ->delete();

        foreach ($featureIds as $featureId) {
            PromoFeature::query()->create([
                'promo_id' => $promo->id,
                'feature_id' => $featureId,
            ]);
        }
    }

    /**
     * Release all features assigned to a promo.
     */
    protected function releaseFeatures(
        Promo $promo
    ): void {
        PromoFeature::query()
            ->where(
                'promo_id',
                $promo->id
            )
            ->delete();
    }

    /**
     * Lock selected feature records.
     *
     * @param array<int, int> $featureIds
     */
    protected function lockFeatures(
        array $featureIds
    ): void {
        if ($featureIds === []) {
            throw new InvalidArgumentException(
                'At least one feature must be selected.'
            );
        }

        $features = Feature::query()
            ->whereIn(
                'id',
                $featureIds
            )
            ->lockForUpdate()
            ->get();

        if ($features->count() !== count($featureIds)) {
            throw new InvalidArgumentException(
                'One or more selected features do not exist.'
            );
        }
    }

    /**
     * Ensure selected features are available for the requested
     * promo period.
     *
     * A conflict exists when:
     *
     * existing_start <= requested_end
     * AND
     * existing_end >= requested_start
     *
     * Only pending and active promos are considered.
     *
     * Inactive promos are ignored because their feature assignments
     * should already have been released.
     *
     * @param array<int, int> $featureIds
     */
    protected function ensureFeaturesAreAvailableForPeriod(
        array $featureIds,
        Carbon $startDate,
        Carbon $endDate,
        ?int $exceptPromoId = null
    ): void {
        if ($featureIds === []) {
            return;
        }

        $conflicts = PromoFeature::query()
            ->with([
                'feature:id,name',
                'promo:id,name,start_date,end_date,status',
            ])
            ->whereIn(
                'feature_id',
                $featureIds
            )
            ->when(
                $exceptPromoId !== null,
                function (Builder $query) use (
                    $exceptPromoId
                ): void {
                    $query->where(
                        'promo_id',
                        '!=',
                        $exceptPromoId
                    );
                }
            )
            ->whereHas(
                'promo',
                function (Builder $query) use (
                    $startDate,
                    $endDate
                ): void {
                    /*
                     * Date ranges overlap when:
                     *
                     * existing_start <= requested_end
                     * AND
                     * existing_end >= requested_start
                     */
                    $query
                        ->where(
                            'start_date',
                            '<=',
                            $endDate
                        )
                        ->where(
                            'end_date',
                            '>=',
                            $startDate
                        )
                        ->whereIn(
                            'status',
                            [
                                'pending',
                                'active',
                            ]
                        );
                }
            )
            ->get();

        if ($conflicts->isEmpty()) {
            return;
        }

        $messages = $conflicts
            ->map(
                function (PromoFeature $promoFeature): string {
                    $feature = $promoFeature->feature;
                    $promo = $promoFeature->promo;

                    return sprintf(
                        'Feature "%s" is already assigned to promo "%s" from %s to %s.',
                        $feature->name,
                        $promo->name,
                        $promo->start_date->format('d M Y H:i'),
                        $promo->end_date->format('d M Y H:i')
                    );
                }
            )
            ->unique()
            ->values()
            ->all();

        throw new RuntimeException(
            'One or more selected features are not available for the requested period: '
            . implode(' ', $messages)
        );
    }

    /**
     * Apply the definition of a currently valid promo.
     *
     * A promo is currently valid only when:
     *
     * status   = active
     * start    <= now
     * end      >= now
     */
    protected function applyValidPromoScope(
        Builder $query
    ): void {
        $now = now();

        $query
            ->where(
                'status',
                'active'
            )
            ->where(
                'start_date',
                '<=',
                $now
            )
            ->where(
                'end_date',
                '>=',
                $now
            );
    }

    /**
     * Normalize feature IDs from the request.
     *
     * @return array<int, int>
     */
    protected function normaliseFeatureIds(
        array $featureIds
    ): array {
        $normalised = collect($featureIds)
            ->map(
                static fn ($featureId): int => (int) $featureId
            )
            ->unique()
            ->values()
            ->all();

        if ($normalised === []) {
            throw new InvalidArgumentException(
                'At least one feature must be selected.'
            );
        }

        return $normalised;
    }

    /*
    |--------------------------------------------------------------------------
    | PROMO LIFECYCLE
    |--------------------------------------------------------------------------
    */

    /**
     * Determine the correct initial status from the promo dates.
     *
     * Future:
     *   start_date > now => pending
     *
     * Current:
     *   start_date <= now && end_date >= now => active
     *
     * Expired:
     *   end_date < now => inactive
     */
    protected function determineStatus(
        Carbon $startDate,
        Carbon $endDate
    ): string {
        $now = now();

        if ($endDate->lt($now)) {
            return 'inactive';
        }

        if ($startDate->gt($now)) {
            return 'pending';
        }

        return 'active';
    }

    /**
     * Determine whether a promo is currently valid.
     */
    public function isPromoCurrentlyValid(
        Promo $promo
    ): bool {
        if ($promo->status !== 'active') {
            return false;
        }

        $now = now();

        return !$promo->start_date->gt($now)
            && !$promo->end_date->lt($now);
    }

    /**
     * Synchronize all promo statuses.
     *
     * This method should be called by the Laravel scheduler.
     *
     * Processing order:
     *
     * 1. Deactivate expired promos.
     * 2. Release their features.
     * 3. Activate pending promos whose start date has arrived.
     *
     * @return array{
     *     activated: int,
     *     deactivated: int
     * }
     */
    public function synchronizePromoStatuses(): array
    {
        return DB::transaction(
            function (): array {
                $now = now();

                /*
                 * Expired promos are processed first so their
                 * features become available before scheduled
                 * activation is attempted.
                 */
                $deactivated = $this->deactivateExpiredPromos(
                    $now
                );

                $activated = $this->activateScheduledPromos(
                    $now
                );

                return [
                    'activated' => $activated,
                    'deactivated' => $deactivated,
                ];
            }
        );
    }

    /**
     * Activate pending promos whose start date has arrived.
     *
     * A promo can only become active if all its features are still
     * available for its configured date range.
     *
     * If a conflict exists, the promo remains pending and will be
     * retried during the next scheduler execution.
     */
    protected function activateScheduledPromos(
        Carbon $now
    ): int {
        $promos = Promo::query()
            ->where(
                'status',
                'pending'
            )
            ->where(
                'start_date',
                '<=',
                $now
            )
            ->where(
                'end_date',
                '>=',
                $now
            )
            ->lockForUpdate()
            ->get();

        if ($promos->isEmpty()) {
            return 0;
        }

        $activated = 0;

        foreach ($promos as $promo) {
            $featureIds = $promo->promoFeatures()
                ->pluck('feature_id')
                ->map(
                    static fn ($id): int => (int) $id
                )
                ->all();

            /*
             * A promo without features cannot be activated.
             */
            if ($featureIds === []) {
                continue;
            }

            /*
             * Lock the features before performing the conflict check.
             */
            $this->lockFeatures($featureIds);

            try {
                /*
                 * Re-check the complete configured date range.
                 *
                 * This is important because another promo may have
                 * been created after this promo was originally scheduled.
                 */
                $this->ensureFeaturesAreAvailableForPeriod(
                    $featureIds,
                    $promo->start_date,
                    $promo->end_date,
                    $promo->id
                );
            } catch (RuntimeException) {
                /*
                 * Another promo currently occupies one or more
                 * selected features during an overlapping period.
                 *
                 * Leave this promo pending and retry later.
                 */
                continue;
            }

            $promo->update([
                'status' => 'active',
                'updated_at' => $now,
            ]);

            $activated++;
        }

        return $activated;
    }

    /**
     * Deactivate expired active promos.
     *
     * Features belonging to expired promos are released.
     */
    protected function deactivateExpiredPromos(
        Carbon $now
    ): int {
        $promos = Promo::query()
            ->where(
                'status',
                'active'
            )
            ->where(
                'end_date',
                '<',
                $now
            )
            ->lockForUpdate()
            ->get();

        if ($promos->isEmpty()) {
            return 0;
        }

        $promoIds = $promos
            ->pluck('id')
            ->map(
                static fn ($id): int => (int) $id
            )
            ->all();

        /*
         * Mark promos inactive.
         */
        Promo::query()
            ->whereIn(
                'id',
                $promoIds
            )
            ->update([
                'status' => 'inactive',
                'updated_at' => $now,
            ]);

        /*
         * Release their features.
         */
        PromoFeature::query()
            ->whereIn(
                'promo_id',
                $promoIds
            )
            ->delete();

        return count($promoIds);
    }

    /*
    |--------------------------------------------------------------------------
    | REPORTING
    |--------------------------------------------------------------------------
    */

    /**
     * Get general promo statistics.
     *
     * @return array{
     *     total_promos: int,
     *     pending_promos: int,
     *     active_promos: int,
     *     inactive_promos: int,
     *     assigned_features: int
     * }
     */
    public function getStatistics(): array
    {
        return [
            'total_promos' => Promo::query()->count(),

            'pending_promos' => Promo::query()
                ->where(
                    'status',
                    'pending'
                )
                ->count(),

            'active_promos' => Promo::query()
                ->where(
                    'status',
                    'active'
                )
                ->count(),

            'inactive_promos' => Promo::query()
                ->where(
                    'status',
                    'inactive'
                )
                ->count(),

            'assigned_features' => PromoFeature::query()
                ->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    /**
     * Validate promo configuration.
     */
    protected function validateConfiguration(
        array $data
    ): void {
        if (
            !isset($data['name']) ||
            trim((string) $data['name']) === ''
        ) {
            throw new InvalidArgumentException(
                'Promo name is required.'
            );
        }

        if (
            !isset($data['start_date']) ||
            empty($data['start_date'])
        ) {
            throw new InvalidArgumentException(
                'Promo start date is required.'
            );
        }

        if (
            !isset($data['end_date']) ||
            empty($data['end_date'])
        ) {
            throw new InvalidArgumentException(
                'Promo expiry date is required.'
            );
        }

        try {
            $startDate = Carbon::parse(
                (string) $data['start_date']
            );

            $endDate = Carbon::parse(
                (string) $data['end_date']
            );
        } catch (\Throwable) {
            throw new InvalidArgumentException(
                'Invalid promo date configuration.'
            );
        }

        if ($endDate->lt($startDate)) {
            throw new InvalidArgumentException(
                'Promo expiry date cannot be before the start date.'
            );
        }

        /*
         * Do not allow creation/update of an already expired promo.
         */
        if ($endDate->lt(now())) {
            throw new InvalidArgumentException(
                'Promo expiry date cannot be in the past.'
            );
        }

        if (
            !isset($data['features_id']) ||
            !is_array($data['features_id']) ||
            $data['features_id'] === []
        ) {
            throw new InvalidArgumentException(
                'At least one feature must be assigned to the promo.'
            );
        }

        if (
            isset($data['expiry_payment_percentage']) &&
            $data['expiry_payment_percentage'] !== null
        ) {
            if (
                !is_numeric(
                    $data['expiry_payment_percentage']
                )
            ) {
                throw new InvalidArgumentException(
                    'Expiry payment percentage must be numeric.'
                );
            }

            $percentage = (float) $data[
                'expiry_payment_percentage'
            ];

            if ($percentage < 0 || $percentage > 100) {
                throw new InvalidArgumentException(
                    'Expiry payment percentage must be between 0 and 100.'
                );
            }
        }
    }
}