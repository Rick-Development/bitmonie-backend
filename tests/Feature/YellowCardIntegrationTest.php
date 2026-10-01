<?php

namespace Tests\Feature;

use App\Services\YellowCard\YellowCardClient;
use App\Services\YellowCard\YellowCardCoverageService;
use App\Services\YellowCard\YellowCardSigner;
use Mockery;
use Tests\TestCase;

class YellowCardIntegrationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_hmac_signature_matches_yellow_card_message_format(): void
    {
        $signer = new YellowCardSigner();
        $timestamp = '2022-01-11T15:48:37.424Z';
        $path = '/payment';
        $method = 'POST';
        $body = '{"amount":100}';
        $secret = 'test-secret';

        $this->assertSame('TUu+Wcaq0iRCzeGZpqil8DRAX814+1qBwk7ySd4cRfE=', $signer->bodyHash($body));
        $this->assertSame(
            '2022-01-11T15:48:37.424Z/paymentPOSTTUu+Wcaq0iRCzeGZpqil8DRAX814+1qBwk7ySd4cRfE=',
            $signer->message($timestamp, $path, $method, $body)
        );
        $this->assertSame('Y1rjLlMvyGSzdurzpaabcEMe/ekd9zPmgzVr5uEeluU=', $signer->sign($timestamp, $path, $method, $secret, $body));
        $this->assertSame('YcHmacV1 api-key:Y1rjLlMvyGSzdurzpaabcEMe/ekd9zPmgzVr5uEeluU=', $signer->authorizationHeader('api-key', $signer->sign($timestamp, $path, $method, $secret, $body)));
    }

    public function test_coverage_service_extracts_and_normalizes_channel_payloads(): void
    {
        $service = new YellowCardCoverageService(Mockery::mock(YellowCardClient::class));

        $channels = $service->channelsFromResponse([
            'data' => [
                [
                    'id' => 'channel-1',
                    'country' => 'NG',
                    'countryName' => 'Nigeria',
                    'currency' => 'NGN',
                    'channelType' => 'bank',
                    'rampType' => 'send',
                    'status' => 'active',
                    'minAmount' => '100',
                    'maxAmount' => '1000000',
                ],
            ],
        ]);

        $this->assertCount(1, $channels);

        $normalized = $service->normalizeChannel($channels[0], now());

        $this->assertSame('channel-1', $normalized['channel_id']);
        $this->assertSame('NG', $normalized['country_code']);
        $this->assertSame('Nigeria', $normalized['country_name']);
        $this->assertSame('NGN', $normalized['currency_code']);
        $this->assertSame('bank', $normalized['channel_type']);
        $this->assertSame('send', $normalized['ramp_type']);
        $this->assertSame('Bank Transfer', $normalized['payment_method']);
        $this->assertSame('active', $normalized['status']);
    }
}
