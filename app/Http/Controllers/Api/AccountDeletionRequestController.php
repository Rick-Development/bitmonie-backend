<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Http\Requests\StoreAccountDeletionRequest;
use App\Models\AccountDeletionRequest;
use App\Models\Admin\Admin;
use App\Notifications\AccountDeletionRequestSubmitted;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class AccountDeletionRequestController extends Controller
{
    public function store(StoreAccountDeletionRequest $request)
    {
        $validated = $request->validated();

        if ($this->isRecentDuplicate($validated['email'], $validated['phone_number'])) {
            return Response::errorResponse(
                'A deletion request was already submitted recently. Please wait before trying again.',
                [],
                429
            );
        }

        $deletionRequest = AccountDeletionRequest::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
            'reason' => $validated['reason'] ?? null,
            'status' => AccountDeletionRequest::STATUS_PENDING,
            'reference_id' => $this->generateReferenceId(),
            'submitted_ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        $this->notifyRecipients($deletionRequest);

        return Response::successResponse(
            'Account deletion request submitted successfully.',
            [
                'reference_id' => $deletionRequest->reference_id,
                'status' => $deletionRequest->status,
                'submitted_at' => $deletionRequest->created_at,
            ],
            201
        );
    }

    private function isRecentDuplicate(string $email, string $phoneNumber): bool
    {
        return AccountDeletionRequest::where(function ($query) use ($email, $phoneNumber) {
            $query->where('email', $email)
                ->orWhere('phone_number', $phoneNumber);
        })
            ->where('created_at', '>=', now()->subMinutes(config('account_deletion.duplicate_window_minutes')))
            ->exists();
    }

    private function generateReferenceId(): string
    {
        do {
            $referenceId = 'ADR-' . now()->format('Ymd') . '-' . Str::upper(Str::random(10));
        } while (AccountDeletionRequest::where('reference_id', $referenceId)->exists());

        return $referenceId;
    }

    private function notifyRecipients(AccountDeletionRequest $deletionRequest): void
    {
        $recipients = collect(config('account_deletion.notification_emails', []))
            ->filter()
            ->map(fn ($email) => trim($email))
            ->filter()
            ->values();

        if ($recipients->isEmpty()) {
            $recipients = Admin::where('status', true)
                ->whereNotNull('email')
                ->pluck('email')
                ->filter()
                ->values();
        }

        if ($recipients->isEmpty() && config('mail.from.address')) {
            $recipients = collect([config('mail.from.address')]);
        }

        if ($recipients->isEmpty()) {
            Log::warning('Account deletion request notification skipped because no recipients are configured.', [
                'reference_id' => $deletionRequest->reference_id,
            ]);
            return;
        }

        foreach ($recipients->unique() as $recipient) {
            try {
                Notification::route('mail', $recipient)
                    ->notify(new AccountDeletionRequestSubmitted($deletionRequest));
            } catch (\Throwable $exception) {
                Log::error('Failed to send account deletion request notification.', [
                    'reference_id' => $deletionRequest->reference_id,
                    'recipient' => $recipient,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
