<?php

namespace App\Console\Commands;

use App\Exceptions\YellowCardApiException;
use App\Services\YellowCard\YellowCardCoverageService;
use Illuminate\Console\Command;

class SyncYellowCardCoverage extends Command
{
    protected $signature = 'yellow-card:sync-coverage {--country=}';

    protected $description = 'Fetch and store Yellow Card supported countries, currencies, and payment channels.';

    public function handle(YellowCardCoverageService $coverageService): int
    {
        try {
            $result = $coverageService->refresh($this->option('country') ?: null);
        } catch (YellowCardApiException $exception) {
            $this->error($exception->getMessage());
            $this->line(json_encode([
                'status_code' => $exception->statusCode(),
                'provider_response' => $exception->response(),
            ], JSON_PRETTY_PRINT));

            return self::FAILURE;
        }

        $this->info(json_encode($result));

        return self::SUCCESS;
    }
}
