<?php

namespace Tests\Feature;

use App\Models\WebhookEventLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QuidaxRampWebhookSignatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::dropAllTables();

        Schema::create('webhook_event_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->nullable();
            $table->string('endpoint')->nullable();
            $table->string('event_name')->nullable();
            $table->string('event_id')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('provider_status')->nullable();
            $table->string('internal_status')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('payload')->nullable();
            $table->longText('raw_payload')->nullable();
            $table->json('headers')->nullable();
            $table->string('processing_status')->default('received');
            $table->unsignedSmallInteger('http_status')->default(200);
            $table->boolean('duplicate')->default(false);
            $table->unsignedBigInteger('duplicate_of_id')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ramp_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type')->nullable();
            $table->string('merchant_reference')->nullable();
            $table->string('status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        $this->withoutMiddleware();
    }

    public function test_invalid_signature_is_rejected_with_401_and_audit_row(): void
    {
        Config::set('services.quidax_ramp.webhook_secret', 'test-ramp-webhook-secret');

        $payload = json_encode([
            'event' => 'buy_transaction.successful',
            'data' => ['merchant_reference' => 'ONRAMP_1_TEST'],
        ]);

        $response = $this->call(
            'POST',
            '/api/webhook/quidax-ramp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_RAMP_SIGNATURE' => 'deadbeef',
            ],
            $payload
        );

        $response->assertStatus(401)
            ->assertJsonPath('status', 'invalid_signature');

        $this->assertDatabaseHas('webhook_event_logs', [
            'provider' => 'quidax_ramp',
            'processing_status' => 'failed',
            'http_status' => 401,
        ]);

        $this->assertStringContainsString(
            'signature verification failed',
            (string) WebhookEventLog::first()->failure_reason
        );
    }

    public function test_missing_webhook_secret_is_rejected_with_401_and_audit_row(): void
    {
        Config::set('services.quidax_ramp.webhook_secret', '');

        $payload = json_encode([
            'event' => 'buy_transaction.successful',
            'data' => ['merchant_reference' => 'ONRAMP_1_TEST'],
        ]);

        $response = $this->call(
            'POST',
            '/api/webhook/quidax-ramp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_RAMP_SIGNATURE' => hash_hmac('sha256', $payload, 'anything'),
            ],
            $payload
        );

        $response->assertStatus(401)
            ->assertJsonPath('status', 'missing_secret');

        $this->assertDatabaseHas('webhook_event_logs', [
            'provider' => 'quidax_ramp',
            'processing_status' => 'failed',
            'http_status' => 401,
        ]);

        $this->assertStringContainsString(
            'no signing secret is configured',
            (string) WebhookEventLog::first()->failure_reason
        );
    }

    public function test_valid_signature_is_accepted_when_no_local_transaction_exists(): void
    {
        $secret = 'test-ramp-webhook-secret';
        Config::set('services.quidax_ramp.webhook_secret', $secret);

        $payload = json_encode([
            'event' => 'buy_transaction.successful',
            'data' => ['merchant_reference' => 'ONRAMP_MISSING'],
        ]);

        $response = $this->call(
            'POST',
            '/api/webhook/quidax-ramp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_RAMP_SIGNATURE' => hash_hmac('sha256', $payload, $secret),
            ],
            $payload
        );

        $response->assertOk()
            ->assertJsonPath('status', 'ignored');
    }
}
