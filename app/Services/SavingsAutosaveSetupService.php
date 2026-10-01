<?php

namespace App\Services;

use App\Models\AutosavePlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SavingsAutosaveSetupService
{
    public function __construct(protected AutosaveService $autosaveService)
    {
    }

    public function rules(): array
    {
        return [
            'autosave' => ['sometimes', 'array'],
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

    public function createForTarget(User $user, Model $target, string $targetType, ?array $config = [], array $defaults = []): ?AutosavePlan
    {
        $config = $config ?: [];

        if (array_key_exists('enabled', $config) && !$this->truthy($config['enabled'])) {
            return null;
        }

        if (empty($config)) {
            return null;
        }

        $mode = $config['mode'] ?? AutosavePlan::MODE_SCHEDULED;

        if (in_array($mode, [AutosavePlan::MODE_SCHEDULED, AutosavePlan::MODE_BOTH], true) && empty($config['amount'])) {
            throw new \InvalidArgumentException('AutoSave amount is required for scheduled mode.');
        }

        if (in_array($mode, [AutosavePlan::MODE_PERCENTAGE, AutosavePlan::MODE_BOTH], true) && empty($config['percentage'])) {
            throw new \InvalidArgumentException('AutoSave percentage is required for percentage mode.');
        }

        if (in_array($mode, [AutosavePlan::MODE_SCHEDULED, AutosavePlan::MODE_BOTH], true) && empty($config['frequency'])) {
            throw new \InvalidArgumentException('AutoSave frequency is required for scheduled mode.');
        }

        return $this->autosaveService->createPlan($user, [
            'name' => $config['name'] ?? $defaults['name'] ?? $this->defaultName($targetType),
            'mode' => $mode,
            'amount' => $config['amount'] ?? null,
            'percentage' => $config['percentage'] ?? null,
            'frequency' => $config['frequency'] ?? null,
            'goal_amount' => $config['goal_amount'] ?? $defaults['goal_amount'] ?? null,
            'maturity_date' => $config['maturity_date'] ?? $defaults['maturity_date'] ?? null,
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
