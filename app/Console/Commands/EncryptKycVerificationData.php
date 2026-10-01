<?php

namespace App\Console\Commands;

use App\Models\KycVerification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class EncryptKycVerificationData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'kyc:encrypt-data
                            {--limit=100 : Maximum number of records to process}';

    /**
     * The console command description.
     */
    protected $description = 'Encrypt unencrypted KYC identity data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        Log::info('KYC encryption command started.', [
            'limit' => $limit,
        ]);

        $this->info("Starting KYC encryption process. Limit: {$limit}");

        $records = KycVerification::query()
            ->where('identity_encrypted', false)
            ->whereNotNull('data')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        Log::info('KYC encryption records queried.', [
            'records_found' => $records->count(),
            'limit' => $limit,
        ]);

        $this->info("Records found: {$records->count()}");

        if ($records->isEmpty()) {
            Log::info('KYC encryption completed: no unencrypted records found.');

            $this->info('No unencrypted KYC data found.');

            return self::SUCCESS;
        }

        $encrypted = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($records as $record) {
            try {
                Log::debug('Processing KYC verification.', [
                    'kyc_verification_id' => $record->id,
                    'identity_encrypted' => (bool) $record->identity_encrypted,
                    'data_present' => !empty($record->data),
                    'data_type' => gettype($record->data),
                ]);

                if ($record->identity_encrypted) {
                    Log::debug('KYC verification already encrypted. Skipping.', [
                        'kyc_verification_id' => $record->id,
                    ]);

                    $skipped++;

                    continue;
                }

                $data = $record->data;

                if (!is_array($data)) {
                    throw new \RuntimeException(
                        "KYC data for record #{$record->id} is not an array."
                    );
                }

                Log::debug('KYC data retrieved for encryption.', [
                    'kyc_verification_id' => $record->id,
                    'data_type' => gettype($data),
                    'data_keys_count' => count($data),
                ]);

                $identityKey = null;

                if (!empty($data['bvn'])) {
                    $identityKey = 'bvn';
                } elseif (!empty($data['nin'])) {
                    $identityKey = 'nin';
                }

                if (!$identityKey) {
                    Log::warning(
                        'Skipping KYC verification because no supported identity field was found.',
                        [
                            'kyc_verification_id' => $record->id,
                            'data_keys_count' => count($data),
                        ]
                    );

                    $this->warn(
                        "Skipping KYC #{$record->id}: no BVN or NIN found."
                    );

                    $skipped++;

                    continue;
                }

                Log::debug('Supported identity field found.', [
                    'kyc_verification_id' => $record->id,
                    'identity_key' => $identityKey,
                ]);

                $originalIdentity = $data[$identityKey];

                $encryptedIdentity = Crypt::encryptString(
                    (string) $originalIdentity
                );

                Log::debug('Identity encryption completed.', [
                    'kyc_verification_id' => $record->id,
                    'identity_key' => $identityKey,
                ]);

                $decryptedIdentity = Crypt::decryptString(
                    $encryptedIdentity
                );

                $identityVerified = $decryptedIdentity === (string) $originalIdentity;

                Log::debug('Identity encryption verification completed.', [
                    'kyc_verification_id' => $record->id,
                    'identity_key' => $identityKey,
                    'verification_passed' => $identityVerified,
                ]);

                if (!$identityVerified) {
                    throw new \RuntimeException(
                        "Encrypted identity verification failed for KYC #{$record->id}."
                    );
                }

                $data[$identityKey] = $encryptedIdentity;

                $record->data = $data;
                $record->identity_encrypted = true;

                if (!$record->saveQuietly()) {
                    throw new \RuntimeException(
                        "Failed to save KYC verification #{$record->id}."
                    );
                }

                $encrypted++;

                Log::info('KYC identity encrypted successfully.', [
                    'kyc_verification_id' => $record->id,
                    'identity_key' => $identityKey,
                    'identity_encrypted' => true,
                ]);

                $this->info(
                    "Encrypted {$identityKey} for KYC verification #{$record->id}."
                );

            } catch (\Throwable $e) {
                $failed++;

                Log::error('Failed to encrypt KYC identity data.', [
                    'kyc_verification_id' => $record->id,
                    'exception' => get_class($e),
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                $this->error(
                    "Failed to encrypt KYC #{$record->id}: {$e->getMessage()}"
                );
            }
        }

        Log::info('KYC encryption command completed.', [
            'records_found' => $records->count(),
            'encrypted' => $encrypted,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);

        $this->newLine();

        $this->info("Encrypted: {$encrypted}");
        $this->info("Skipped: {$skipped}");
        $this->info("Failed: {$failed}");

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}