<?php

namespace App\Jobs;

use App\Models\KycVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class EncryptKycVerificationData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of attempts before the job is considered failed.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        KycVerification::query()
            ->where('identity_encrypted', false)
            ->whereNotNull('data')
            ->where('data', '!=', '')
            ->chunkById(100, function ($records) {

                foreach ($records as $record) {
                    try {
                        /*
                         * Prevent processing the same record if another
                         * process has already encrypted it.
                         */
                        if ($record->identity_encrypted) {
                            continue;
                        }

                        $data = $record->data;

                        /*
                         * Make sure the existing data is valid JSON
                         * before encrypting it.
                         */
                        $decoded = json_decode($data, true);

                        if (json_last_error() !== JSON_ERROR_NONE) {
                            Log::warning(
                                'Skipping KYC verification with invalid JSON data.',
                                [
                                    'kyc_verification_id' => $record->id,
                                ]
                            );

                            continue;
                        }

                        /*
                         * Encrypt the complete data payload.
                         */
                        $encryptedData = Crypt::encryptString($data);

                        /*
                         * Update the data and encryption flag together.
                         */
                        $record->data = $encryptedData;
                        $record->identity_encrypted = true;

                        $record->saveQuietly();

                    } catch (\Throwable $e) {

                        /*
                         * Do not mark the record as encrypted if anything
                         * goes wrong.
                         */
                        Log::error(
                            'Failed to encrypt KYC verification data.',
                            [
                                'kyc_verification_id' => $record->id,
                                'error' => $e->getMessage(),
                            ]
                        );
                    }
                }
            });
    }
}
