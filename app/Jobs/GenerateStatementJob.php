<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\StatementMail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\User;
use App\Services\StatementService;
use Carbon\Carbon;

class GenerateStatementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId;
    public $filters;
    public $format;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($userId, $filters, $format = 'pdf')
    {
        $this->userId = $userId;
        $this->filters = $filters;
        $this->format = strtolower($format);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(StatementService $statementService)
    {
        $user = User::find($this->userId);
        if (!$user) return;

        // Fetch unified transactions
        $transactions = $statementService->getUnifiedTransactions($this->userId, $this->filters);

        // Format dates for display
        $startDate = 'N/A';
        $endDate = 'N/A';

        if (isset($this->filters['start_date'])) {
            $startDate = Carbon::parse($this->filters['start_date'])->format('Y-m-d');
        } elseif (count($transactions) > 0) {
            // Find the earliest date in the transactions array
            $dates = array_column($transactions, 'date');
            $startDate = Carbon::parse(min($dates))->format('Y-m-d');
        }

        if (isset($this->filters['end_date'])) {
            $endDate = Carbon::parse($this->filters['end_date'])->format('Y-m-d');
        } elseif (count($transactions) > 0) {
            // Find the latest date in the transactions array
            $dates = array_column($transactions, 'date');
            $endDate = Carbon::parse(max($dates))->format('Y-m-d');
        }

        $dateRange = "{$startDate} to {$endDate}";

        $fileName = 'statements/Statement_' . $user->id . '_' . time() . '.' . $this->format;
        Storage::disk('local')->put($fileName, ''); // Ensure file/dir exists
        $filePath = storage_path('app/' . $fileName);

        if ($this->format === 'csv') {
            $file = fopen($filePath, 'w');
            fputcsv($file, ['Date', 'Type', 'Narration', 'TXID', 'Status', 'Amount']);
            foreach ($transactions as $tx) {
                fputcsv($file, [
                    $tx['date'],
                    $tx['type'],
                    $tx['narration'],
                    $tx['txid'],
                    $tx['status'],
                    number_format($tx['amount'], 2, '.', '')
                ]);
            }
            fclose($file);
        } else {
            // Calculate statistics
            $totalTrxCount = count($transactions);
            $totalVolume = array_reduce($transactions, function($carry, $item) {
                return $carry + (float)$item['amount'];
            }, 0);

            // Generate PDF
            $pdf = Pdf::loadView('statements.pdf', [
                'user' => $user,
                'transactions' => $transactions,
                'filters' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'stats' => [
                    'total_trx_count' => $totalTrxCount,
                    'total_volume' => $totalVolume,
                ]
            ]);
            
            // Save PDF temporarily to send via email
            file_put_contents($filePath, $pdf->output());
        }

        // Send Email
        Mail::to($user->email)->send(new StatementMail($user, $filePath, $dateRange, $this->format));

        // Clean up file if needed or keep for history
        // unlink($pdfPath);
    }
}
