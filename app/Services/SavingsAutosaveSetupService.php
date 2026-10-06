<?php

namespace App\Services;

use App\Models\AutosavePlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SavingsAutosaveSetupService
{
    public function __construct(protected AutosaveService $autosaveService)
    {
    }

    public function rules(): array
    {
        return [
            'autosave' => ['sometimes', 'nullable', 'array'],
            'autosave.enabled' => ['sometimes', 'boolean'],
            'autosave.name' => ['nullable', 'string', 'max:120'],
            'autosave.mode' => ['exclude_if:autosave.enabled,false', 'required_with:autosave', 'in:scheduled,percentage,both'],
            'autosave.amount' => ['exclude_if:autosave.enabled,false', 'required_if:autosave.mode,scheduled,both', 'nullable', 'numeric', 'min:1'],
            'autosave.percentage' => ['exclude_if:autosave.enabled,false', 'required_if:autosave.mode,percentage,both', 'nullable', 'numeric', 'min:0.01', 'max:100'],
            'autosave.frequency' => ['exclude_if:autosave.enabled,false', 'required_if:autosave.mode,scheduled,both', 'nullable', 'in:daily,weekly,monthly'],
            'autosave.goal_amount' => ['nullable', 'numeric', 'min:1'],
            'autosave.maturity_date' => ['nullable', 'date', 'after:today'],
        ];
    }

    /**
     * Normalize autosave payload (trim strings, convert empty strings to null, format dates).
     */
    public function normalizeConfig(?array $config): array
    {
        if (empty($config)) {
            return [];
        }

        foreach ($config as $key => $value) {
            if (is_string($value)) {
                $trimmed = trim($value);
                $config[$key] = $trimmed === '' ? null : $trimmed;
            }
        }

        if (!empty($config['maturity_date'])) {
            $parsedDate = $this->parseMaturityDate($config['maturity_date']);
            if ($parsedDate !== null) {
                $config['maturity_date'] = $parsedDate;
            }
        }

        return $config;
    }

    /**
     * Parse and format maturity dates safely into Y-m-d.
     */
    public function parseMaturityDate(mixed $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        if ($date instanceof \Carbon\CarbonInterface || $date instanceof \DateTimeInterface) {
            return Carbon::instance($date)->toDateString();
        }

        $dateStr = trim((string) $date);

        // Try explicit common formats
        $formats = [
            'Y-m-d',
            'd/m/Y',
            'd-m-Y',
            'm/d/Y',
            'Y/m/d',
            'Y-m-d H:i:s',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $dateStr);
                if ($parsed !== false) {
                    return $parsed->toDateString();
                }
            } catch (\Throwable) {
                // Continue to next format
            }
        }

        try {
            return Carbon::parse($dateStr)->toDateString();
        } catch (\Throwable) {
            return $dateStr;
        }
    }

    /**
     * Validate autosave configuration semantics.
     */
    public function validateConfig(?array $config): void
    {
        $config = $this->normalizeConfig($config);

        if (empty($config) || (array_key_exists('enabled', $config) && !$this->truthy($config['enabled']))) {
            return;
        }

        $mode = $config['mode'] ?? AutosavePlan::MODE_SCHEDULED;

        if (!in_array($mode, [AutosavePlan::MODE_SCHEDULED, AutosavePlan::MODE_PERCENTAGE, AutosavePlan::MODE_BOTH], true)) {
            throw new \InvalidArgumentException('Invalid AutoSave mode selected.');
        }

        if (in_array($mode, [AutosavePlan::MODE_SCHEDULED, AutosavePlan::MODE_BOTH], true) && empty($config['amount'])) {
            throw new \InvalidArgumentException('AutoSave amount is required for scheduled mode.');
        }

        if (in_array($mode, [AutosavePlan::MODE_PERCENTAGE, AutosavePlan::MODE_BOTH], true) && empty($config['percentage'])) {
            throw new \InvalidArgumentException('AutoSave percentage is required for percentage mode.');
        }

        if (in_array($mode, [AutosavePlan::MODE_SCHEDULED, AutosavePlan::MODE_BOTH], true) && empty($config['frequency'])) {
            throw new \InvalidArgumentException('AutoSave frequency is required for scheduled mode.');
        }

        if (!empty($config['percentage']) && ((float) $config['percentage'] <= 0 || (float) $config['percentage'] > 100)) {
            throw new \InvalidArgumentException('AutoSave percentage must be between 0.01 and 100.');
        }

        if (!empty($config['maturity_date'])) {
            try {
                $parsedMaturity = Carbon::parse($config['maturity_date'])->startOfDay();
                if ($parsedMaturity->isPast() && !$parsedMaturity->isToday()) {
                    throw new \InvalidArgumentException('AutoSave maturity date must be a future date.');
                }
            } catch (\InvalidArgumentException $e) {
                throw $e;
            } catch (\Throwable) {
                throw new \InvalidArgumentException('Invalid AutoSave maturity date format.');
            }
        }
    }

    public function createForTarget(User $user, Model $target, string $targetType, ?array $config = [], array $defaults = []): ?AutosavePlan
    {
        $config = $this->normalizeConfig($config);

        if (array_key_exists('enabled', $config) && !$this->truthy($config['enabled'])) {
            return null;
        }

        if (empty($config)) {
            return null;
        }

        $this->validateConfig($config);

        $mode = $config['mode'] ?? AutosavePlan::MODE_SCHEDULED;
        $maturityDate = $config['maturity_date'] ?? $this->parseMaturityDate($defaults['maturity_date'] ?? null);

        return $this->autosaveService->createPlan($user, [
            'name' => $config['name'] ?? $defaults['name'] ?? $this->defaultName($targetType),
            'mode' => $mode,
            'amount' => $config['amount'] ?? null,
            'percentage' => $config['percentage'] ?? null,
            'frequency' => $config['frequency'] ?? null,
            'goal_amount' => $config['goal_amount'] ?? $defaults['goal_amount'] ?? null,
            'maturity_date' => $maturityDate,
            'metadata' => [
                'target_type' => $targetType,
                'target_model' => $target::class,
                'target_id' => $target->getKey(),
                'target_title' => $defaults['title'] ?? $target->title ?? $this->defaultName($targetType),
                'created_from' => 'savings_option',
            ],
        ]);
    }

    protected function defaultName(string $targetType): string
    {
        return match ($targetType) {
            'edusave' => 'EduSave AutoSave',
            'target_savings' => 'Target Savings AutoSave',
            'flex_savings' => 'Flex Savings AutoSave',
            'safe_lock' => 'SafeLock AutoSave',
            'locked_funds' => 'Locked Funds AutoSave',
            default => 'Savings AutoSave',
        };
    }

    protected function truthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
