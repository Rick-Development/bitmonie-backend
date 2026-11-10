<?php

namespace Tests\Feature;

use App\Models\RampTransaction;
use App\Models\WebhookEventLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QuidaxRampWebhookForwardTest extends TestCase
{
    private string $secret = 'test-ramp-webhook-secret';

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
        Config::set('services.quidax_ramp.webhook_secret', $this->secret);
        Config::set('services.quidax_ramp.webhook_forward_url', '');
    }

    protected function signedCall(string $payload, array $extraHeaders = [])
    {
        $headers = array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAMP_SIGNATURE' => hash_hmac('sha256', $payload, $this->secret),
        ], $extraHeaders);

        return $this->call(
            'POST',
            '/api/quidax/ramp/webhook',
            [],
            [],
            [],
            $headers,
            $payload
        );
    }

    public function test_unmatched_webhook_is_forwarded_when_url_is_configured(): void
    {
        $forwardUrl = 'https://sandbox.example.test/api/quidax/ramp/webhook';
        Config::set('services.quidax_ramp.webhook_forward_url', $forwardUrl);

        Http::fake([
            $forwardUrl => Http::response(['status' => 'success'], 200),
        ]);

        $payload = json_encode([
            'event' => 'buy_transaction.successful',
            'data' => ['merchant_reference' => 'ONRAMP_106_MISSING'],
        ]);

        $this->signedCall($payload)
            ->assertOk()
            ->assertJsonPath('status', 'success');

        Http::assertSent(function ($request) use ($forwardUrl, $payload) {
            return $request->url() === $forwardUrl
                && $request->body() === $payload
                && $request->hasHeader('X-Ramp-Signature')
                && $request->header('X-Bitmonie-Webhook-Forward')[0] === '1';
        });

        $this->assertStringContainsString(
            'forwarded to',
            (string) WebhookEventLog::first()->failure_reason
        );
    }

    public function test_forward_is_skipped_when_loop_guard_header_is_present(): void
    {
        Config::set('services.quidax_ramp.webhook_forward_url', 'https://sandbox.example.test/api/quidax/ramp/webhook');
        Http::fake();

        $payload = json_encode([
            'event' => 'buy_transaction.successful',
            'data' => ['merchant_reference' => 'ONRAMP_106_MISSING'],
        ]);

        $this->signedCall($payload, ['HTTP_X_BITMONIE_WEBHOOK_FORWARD' => '1'])
            ->assertOk()
            ->assertJsonPath('status', 'ignored');

        Http::assertNothingSent();
    }

    public function test_local_transaction_is_not_forwarded(): void
    {
        Config::set('services.quidax_ramp.webhook_forward_url', 'https://sandbox.example.test/api/quidax/ramp/webhook');
        Http::fake();

        RampTransaction::query()->create([
            'user_id' => 1,
            'type' => 'on_ramp',
            'merchant_reference' => 'ONRAMP_1_LOCAL',
            'status' => 'confirmed',
        ]);

        $payload = json_encode([
            'event' => 'buy_transaction.successful',
            'data' => ['merchant_reference' => 'ONRAMP_1_LOCAL'],
        ]);

        $response = $this->signedCall($payload);
        $this->assertNotEquals('forwarded', $response->json('status'));
        $this->assertTrue(in_array($response->json('status'), ['success', 'error_logged', 'reconciled'], true), (string) $response->getContent());
        Http::assertNothingSent();
    }

    public function test_forward_is_disabled_when_url_is_empty(): void
    {
        Config::set('services.quidax_ramp.webhook_forward_url', '');
        Http::fake();

        $payload = json_encode([
            'event' => 'buy_transaction.successful',
            'data' => ['merchant_reference' => 'ONRAMP_MISSING'],
        ]);

        $this->signedCall($payload)
            ->assertOk()
            ->assertJsonPath('status', 'ignored');

        Http::assertNothingSent();
    }
}
