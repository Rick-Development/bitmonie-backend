<?php

namespace Tests\Feature\CryptoCards;

use App\Jobs\InitiateQuidaxCardFundingJob;
use App\Jobs\ProcessSudoCardCreationJob;
use App\Jobs\SendNotificationJob;
use App\Mail\UserEmail;
use App\Models\User;
use App\Services\CryptoCardService;
use App\Services\SudoCardService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Mockery;

class CardCreationNotificationsTest extends CryptoCardTestCase
{
    protected function mockSudo(?array $response = null, ?\Throwable $throw = null): void
    {
        $sudo = Mockery::mock(SudoCardService::class);
        $exp = $sudo->shouldReceive('createCard');
        $throw ? $exp->andThrow($throw) : $exp->andReturn($response);
        $this->app->instance(SudoCardService::class, $sudo);
    }

    protected function sudoSuccess(): array
    {
        return ['success' => true, 'statusCode' => 200, 'data' => [
            '_id' => 'crd_test_1', 'maskedPan' => '506099******4321', 'status' => 'active',
            'type' => 'virtual', 'brand' => 'Verve', 'currency' => 'NGN',
            'expiryMonth' => '12', 'expiryYear' => '2029',
        ]];
    }

    protected function dispatchedKeys(): array
    {
        $keys = [];
        Bus::assertDispatched(SendNotificationJob::class, function ($job) use (&$keys) {
            $r = new \ReflectionProperty($job, 'templateKey');
            $r->setAccessible(true);
            $keys[] = $r->getValue($job);

            return true;
        });

        return $keys;
    }

    protected function paramsFor(string $key): array
    {
        $found = null;
        Bus::assertDispatched(SendNotificationJob::class, function ($job) use ($key, &$found) {
            $k = new \ReflectionProperty($job, 'templateKey');
            $k->setAccessible(true);
            if ($k->getValue($job) !== $key) {
                return false;
            }
            $p = new \ReflectionProperty($job, 'params');
            $p->setAccessible(true);
            $found = $p->getValue($job);

            return true;
        });

        return $found;
    }

    public function test_success_queues_card_ready_email(): void
    {
        Bus::fake([SendNotificationJob::class]);
        $this->mockSudo($this->sudoSuccess());
        $user = $this->makeUser();
        [$card, $tx, $escrow] = $this->makePendingCard($user, 5.0);

        $result = app(CryptoCardService::class)->finalizePendingCardCreation($card, $tx, $escrow);

        $this->assertTrue($result['success']);
        $this->assertSame('active', $card->fresh()->card_status);
        $this->assertSame(['CRYPTO_CARD_CREATION_SUCCESS'], $this->dispatchedKeys());
        $params = $this->paramsFor('CRYPTO_CARD_CREATION_SUCCESS');
        $this->assertSame('4321', $params['card']);
        $this->assertSame((string) $tx->id, $params['reference']);
    }

    public function test_sudo_rejection_fails_refunds_and_emails_once(): void
    {
        Bus::fake([SendNotificationJob::class]);
        $this->mockSudo(['success' => false, 'statusCode' => 400, 'message' => 'Insufficient settlement balance']);
        $user = $this->makeUser('0');
        [$card, $tx, $escrow] = $this->makePendingCard($user, 5.0);

        $result = app(CryptoCardService::class)->finalizePendingCardCreation($card, $tx, $escrow);
        // A retry / duplicate webhook must not refund or email again.
        app(CryptoCardService::class)->failPendingCardCreation($card->fresh(), $tx->fresh(), $escrow->fresh(), 'dup');

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $card->fresh()->card_status);
        $this->assertSame('failed', $tx->fresh()->transaction_status);
        $this->assertSame('reversed', $escrow->fresh()->status);
        $this->assertEqualsWithDelta(5.0, $this->usdtBalance($user), 0.0001);
        $this->assertSame(['CRYPTO_CARD_CREATION_FAILED'], $this->dispatchedKeys());
        $params = $this->paramsFor('CRYPTO_CARD_CREATION_FAILED');
        $this->assertSame(CryptoCardService::REFUND_REFUNDED, $params['refund_status']);
        $this->assertStringContainsString('has been refunded', $params['refund_message']);
        $this->assertSame('Insufficient settlement balance', $params['reason']);
    }

    public function test_failure_without_charge_says_nothing_was_deducted(): void
    {
        Bus::fake([SendNotificationJob::class]);
        $user = $this->makeUser('0');
        [$card, $tx, $escrow] = $this->makePendingCard($user, 0.0);

        app(CryptoCardService::class)->failPendingCardCreation($card, $tx, $escrow, 'boom');

        $params = $this->paramsFor('CRYPTO_CARD_CREATION_FAILED');
        $this->assertSame(CryptoCardService::REFUND_NOT_CHARGED, $params['refund_status']);
        $this->assertSame(0.0, $this->usdtBalance($user));
    }

    public function test_zero_fee_job_unexpected_sudo_error_fails_card_and_emails(): void
    {
        Bus::fake([SendNotificationJob::class]);
        $this->mockSudo(null, new \RuntimeException('cURL error 28: timed out'));
        $user = $this->makeUser('0');
        [$card, $tx, $escrow] = $this->makePendingCard($user, 0.0, 'pending');

        $job = new ProcessSudoCardCreationJob($user->id, $card->id, $tx->id, $escrow->id, ['usdt_amount' => 0]);
        try {
            $job->handle(app(CryptoCardService::class));
            $this->fail('Expected the job to rethrow for the queue.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('timed out', $e->getMessage());
        }

        $this->assertSame('failed', $card->fresh()->card_status);
        $this->assertSame(['CRYPTO_CARD_CREATION_FAILED'], $this->dispatchedKeys());
        // Raw transport errors are not shown to the customer.
        $this->assertSame('The card provider could not complete the request.', $this->paramsFor('CRYPTO_CARD_CREATION_FAILED')['reason']);

        // Queue retry is a no-op: card is terminal, no second email.
        $job->handle(app(CryptoCardService::class));
        $this->assertCount(1, $this->dispatchedKeys());
    }

    public function test_offramp_initiation_exhausted_fails_card_creation_with_creation_email(): void
    {
        Bus::fake([SendNotificationJob::class]);
        $user = $this->makeUser('0');
        [$card, $tx, $escrow] = $this->makePendingCard($user, 5.0);

        $job = new InitiateQuidaxCardFundingJob($user->id, $card->id, 5.0, $tx->id, $escrow->id, [
            'purpose' => 'card_creation',
            'charge' => data_get($tx->metadata, 'charge'),
        ]);
        $m = new \ReflectionMethod($job, 'markFailed');
        $m->setAccessible(true);
        $m->invoke($job, $tx, $escrow, $user, $card, 'Quidax rejected request');

        $this->assertSame('failed', $card->fresh()->card_status);
        $this->assertSame('failed', $tx->fresh()->transaction_status);
        $this->assertEqualsWithDelta(5.0, $this->usdtBalance($user), 0.0001);
        $this->assertSame(['CRYPTO_CARD_CREATION_FAILED'], $this->dispatchedKeys());
    }

    public function test_real_mail_pipeline_renders_template_and_queues_user_email(): void
    {
        Mail::fake();
        $this->mockSudo($this->sudoSuccess());
        $user = $this->makeUser();
        [$card, $tx, $escrow] = $this->makePendingCard($user, 5.0);

        app(CryptoCardService::class)->finalizePendingCardCreation($card, $tx, $escrow);

        Mail::assertQueued(UserEmail::class, function (UserEmail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_mail_transport_failure_does_not_break_card_flow(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        $this->mockSudo($this->sudoSuccess());
        $user = $this->makeUser();
        [$card, $tx, $escrow] = $this->makePendingCard($user, 5.0);

        $result = app(CryptoCardService::class)->finalizePendingCardCreation($card, $tx, $escrow);

        $this->assertTrue($result['success']);
        $this->assertSame('active', $card->fresh()->card_status);
        $this->assertSame('approved', $tx->fresh()->transaction_status);
    }

    public function test_settled_offramp_then_sudo_exception_refunds_and_emails_without_rethrow(): void
    {
        Bus::fake([SendNotificationJob::class]);
        $this->mockSudo(null, new \RuntimeException('Sudo 502'));
        $quidax = Mockery::mock(\App\Services\QuidaxService::class);
        $quidax->shouldReceive('releaseFromEscrow')->once()->andReturn(['status' => 'success']);
        $this->app->instance(\App\Services\QuidaxService::class, $quidax);
        $user = $this->makeUser('0');
        $user->forceFill(['quidax_id' => 'qdx_test'])->save();
        [$card, $tx, $escrow] = $this->makePendingCard($user, 5.0);

        $job = new \App\Jobs\ProcessQuidaxOfframpForCardFunding($tx->id, [], ['status' => 'done'], 'ramp.offramp.completed', 'MREF-1');
        $m = new \ReflectionMethod($job, 'completeCardCreation');
        $m->setAccessible(true);
        $m->invoke($job, $tx, $escrow);

        $this->assertSame('failed', $card->fresh()->card_status);
        $this->assertSame(['CRYPTO_CARD_CREATION_FAILED'], $this->dispatchedKeys());
        $this->assertSame(CryptoCardService::REFUND_REFUNDED, $this->paramsFor('CRYPTO_CARD_CREATION_FAILED')['refund_status']);
        // escrow_deducted=true forces a Quidax escrow release; QuidaxService is mocked so nothing leaves the box.
    }
}
