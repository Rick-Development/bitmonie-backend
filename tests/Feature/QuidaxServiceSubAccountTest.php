<?php

namespace Tests\Feature;

use App\Services\QuidaxService;
use Tests\TestCase;

class QuidaxServiceSubAccountTest extends TestCase
{
    protected function service(): QuidaxService
    {
        return $this->app->make(QuidaxService::class);
    }

    public function test_specific_missing_sub_account_message_is_treated_as_missing(): void
    {
        $service = $this->service();

        $this->assertTrue($service->isSubAccountMissingResponse([
            'status' => 'error',
            'message' => "The sub-account with id: abc-123 doesn't exist",
        ]));

        $this->assertTrue($service->isSubAccountMissingResponse([
            'status' => 'error',
            'message' => 'Sub-account not found',
        ]));

        $this->assertTrue($service->isSubAccountMissingResponse([
            'status' => 'error',
            'message' => 'This subaccount has been deleted',
        ]));
    }

    public function test_generic_wallet_not_found_is_not_treated_as_missing_sub_account(): void
    {
        $service = $this->service();

        $this->assertFalse($service->isSubAccountMissingResponse([
            'status' => 'error',
            'message' => 'Wallet not found',
        ]));

        $this->assertFalse($service->isSubAccountMissingResponse([
            'status' => 'error',
            'message' => 'The resource was not found',
        ]));

        $this->assertFalse($service->isSubAccountMissingResponse([
            'status' => 'error',
            'message' => "The wallet doesn't exist",
        ]));

        $this->assertFalse($service->isSubAccountMissingResponse([
            'message' => '',
        ]));
    }
}
