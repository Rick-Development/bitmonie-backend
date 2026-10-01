<?php

namespace Tests\Unit;

use App\Http\Helpers\SafeHeaven\AccountHelper;
use App\Http\Helpers\SafeHeaven\VASHelper;
use App\Services\SafeHavenBillService;
use Exception;
use Mockery;
use Tests\TestCase;

class SafeHavenBillServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_maps_bundle_code_to_product_id_for_data_purchases(): void
    {
        $service = $this->makeService();

        $normalized = $service->normalize('data', [
            'network' => 'etisalat',
            'bundleCode' => 'BUNDLE-123',
            'phoneNumber' => '0813 513 9485',
        ]);

        $this->assertSame('9MOBILE', $normalized['network']);
        $this->assertSame('BUNDLE-123', $normalized['productId']);
        $this->assertSame('08135139485', $normalized['phoneNumber']);
    }

    public function test_it_maps_cable_alias_fields_to_safehaven_payload_fields(): void
    {
        $service = $this->makeService();

        $normalized = $service->normalize('cable', [
            'provider' => 'startime',
            'bundleCode' => 'DSTV-PROD',
            'cardNumber' => '4601747485',
        ]);

        $this->assertSame('STARTIMES', $normalized['provider']);
        $this->assertSame('DSTV-PROD', $normalized['productId']);
        $this->assertSame('4601747485', $normalized['smartCardNumber']);
        $this->assertSame('4601747485', $normalized['entityNumber']);
    }

    public function test_it_maps_utility_alias_fields_and_uppercases_vend_type(): void
    {
        $service = $this->makeService();

        $normalized = $service->normalize('utility', [
            'provider' => 'ibadan',
            'meterNumber' => '70005233898',
            'vendType' => 'prepaid',
        ]);

        $this->assertSame('IBEDC', $normalized['provider']);
        $this->assertSame('70005233898', $normalized['entityNumber']);
        $this->assertSame('70005233898', $normalized['meterNumber']);
        $this->assertSame('PREPAID', $normalized['vendType']);
    }

    public function test_it_rejects_data_purchase_payload_without_product_id(): void
    {
        $service = $this->makeService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing required SafeHaven field(s): productId.');

        $service->assertFields('data', [
            'amount' => 1500,
            'phoneNumber' => '08135139485',
            'serviceCategoryId' => '6502eb6e65463b201bf8065f',
        ]);
    }

    protected function makeService(): object
    {
        return new class(
            Mockery::mock(VASHelper::class),
            Mockery::mock(AccountHelper::class)
        ) extends SafeHavenBillService {
            public function normalize(string $type, array $data): array
            {
                return $this->normalizePurchasePayload($type, $data);
            }

            public function assertFields(string $type, array $data): void
            {
                $this->assertRequiredPurchaseFields($type, $data);
            }
        };
    }
}
