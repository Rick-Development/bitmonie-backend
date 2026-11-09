<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the missing CRYPTO_CARD_CREATION_* notification templates used by the
 * async Sudo card-creation flow. Without them the Notify trait finds no
 * template and silently skips the email.
 *
 * Insert-only: existing rows (e.g. admin-edited copies) are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        foreach ($this->templates() as $template) {
            $exists = DB::table('notification_templates')
                ->where('template_key', $template['template_key'])
                ->exists();

            if ($exists) {
                continue;
            }

            $template['status'] = json_encode($template['status']);
            $template['created_at'] = now();
            $template['updated_at'] = now();

            DB::table('notification_templates')->insert($template);
            Cache::forget("notification_template_{$template['template_key']}__0");
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: templates may have been edited by admins.
    }

    private function templates(): array
    {
        return [
[
    'template_key' => 'CRYPTO_CARD_CREATION_PENDING',
    'notify_for' => 0,
    'name' => 'Card Creation Processing',
    'subject' => 'Your Card Request Is Being Processed',
    'email' => '
        Hello [[user]],<br><br>

        We have received your request for a new [[type]] [[brand]] card and it is now being processed.<br><br>

        <strong>Request Details</strong><br>
        Card: [[type]] [[brand]] ([[currency]])<br>
        Charge: [[amount]] USDT<br>
        Reference: [[reference]]<br>
        Status: Processing<br><br>

        We will email you again as soon as your card is ready, or if anything goes wrong.
    ',
    'sms' => 'Your [[type]] card request is being processed. Ref: [[reference]].',
    'push' => 'Your card request is being processed.',
    'in_app' => 'Your [[type]] [[brand]] card request is being processed. Ref: [[reference]].',
    'status' => ['mail' => true, 'sms' => false, 'push' => true, 'in_app' => true],
    'is_transactional' => true,
],
[
    'template_key' => 'CRYPTO_CARD_CREATION_SUCCESS',
    'notify_for' => 0,
    'name' => 'Card Created Successfully',
    'subject' => 'Your Virtual Card Is Ready',
    'email' => '
        Hello [[user]],<br><br>

        Your new [[type]] [[brand]] card has been created successfully and is ready to use.<br><br>

        <strong>Card Details</strong><br>
        Card: ****[[card]]<br>
        Currency: [[currency]]<br>
        Reference: [[reference]]<br>
        Status: Active<br><br>

        You can view and manage your card in the Bitmonie app.
    ',
    'sms' => 'Your [[type]] card ****[[card]] is ready. Ref: [[reference]].',
    'push' => 'Your new card is ready to use.',
    'in_app' => 'Your [[type]] [[brand]] card ****[[card]] has been created successfully.',
    'status' => ['mail' => true, 'sms' => false, 'push' => true, 'in_app' => true],
    'is_transactional' => true,
],
[
    'template_key' => 'CRYPTO_CARD_CREATION_FAILED',
    'notify_for' => 0,
    'name' => 'Card Creation Failed',
    'subject' => 'Your Card Request Could Not Be Completed',
    'email' => '
        Hello [[user]],<br><br>

        Unfortunately, we could not create your [[type]] card.<br><br>

        <strong>Request Details</strong><br>
        Reference: [[reference]]<br>
        Status: Failed<br>
        Reason: [[reason]]<br><br>

        [[refund_message]]<br><br>

        You can try again from the app. If you need help, please contact support with the reference above.
    ',
    'sms' => 'Your card request (Ref: [[reference]]) failed. [[refund_message]]',
    'push' => 'Your card request could not be completed.',
    'in_app' => 'Card creation failed (Ref: [[reference]]). [[refund_message]]',
    'status' => ['mail' => true, 'sms' => false, 'push' => true, 'in_app' => true],
    'is_transactional' => true,
],
        ];
    }
};
