<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Services\TransactionHistoryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;

class StatementController extends Controller
{
    /**
     * Method for get the statement data
     */

public function index(Request $request, TransactionHistoryService $historyService)
{
    $perPage = (int) $request->query('per_page', 20);

    $rows = $historyService->forUser(
        $request->user(),
        $request->query()
    );

    $transactions = $historyService->paginateRows(
        $rows,
        $request->query(),
        $perPage
    );

    return Response::successResponse(
        'Statement data fetched successfully.',
        [
            'transactions' => $transactions->through(function ($row) {
                $transactionType = strtolower(
                    (string) ($row['transaction_type'] ?? '')
                );

                $details = is_array($row['details'] ?? null) ? $row['details'] : [];
                $product = $details['product'] ?? null;
                $kind = $details['kind'] ?? $transactionType;

                $type = match (true) {
                    str_starts_with($transactionType, 'flexi_'),
                    str_starts_with($transactionType, 'flex_'),
                    in_array($transactionType, ['flexi', 'flex', 'flex_savings'], true)
                        => \App\Services\Savings\SavingsCatalog::statementType('FLEXI', $kind ?: $transactionType),

                    str_starts_with($transactionType, 'auto_'),
                    in_array($transactionType, ['auto', 'autosave', 'autosave_percentage', 'autosave_scheduled'], true)
                        => \App\Services\Savings\SavingsCatalog::statementType('AUTO', $kind ?: $transactionType),

                    str_starts_with($transactionType, 'target_'),
                    in_array($transactionType, ['target', 'target_savings'], true)
                        => \App\Services\Savings\SavingsCatalog::statementType('TARGET', $kind ?: $transactionType),

                    str_starts_with($transactionType, 'safelock_'),
                    str_starts_with($transactionType, 'safe_lock'),
                    in_array($transactionType, ['safelock', 'safe_lock'], true)
                        => \App\Services\Savings\SavingsCatalog::statementType('SAFELOCK', $kind ?: $transactionType),

                    str_starts_with($transactionType, 'edusave'),
                    in_array($transactionType, ['edusave', 'edu_save'], true)
                        => \App\Services\Savings\SavingsCatalog::statementType('EDUSAVE', $kind ?: $transactionType),

                    str_contains($transactionType, 'easyearn'),
                    str_starts_with($transactionType, 'usdt_')
                        => \App\Services\Savings\SavingsCatalog::statementType('USDT_EASYEARN', $kind ?: $transactionType),

                    $transactionType === 'savings'
                        => \App\Services\Savings\SavingsCatalog::statementType($product, $kind),

                    $transactionType === 'airtime' => 'Airtime Purchase',
                    $transactionType === 'utility' => 'Utility Payment',
                    $transactionType === 'data' => 'Data Purchase',
                    $transactionType === 'on_ramp' => 'Crypto Purchase',
                    $transactionType === 'off_ramp' => 'Crypto Sale',
                    $transactionType === 'wallet' => 'Wallet Transfer',
                    $transactionType === 'own-bank-transfer' => 'Bank Transfer',

                    default => $product
                        ? \App\Services\Savings\SavingsCatalog::statementType($product, $kind)
                        : ucwords(str_replace(['_', '-'], ' ', $transactionType)),
                };

                $description = $row['description']
                    ?: ($details['narration'] ?? null)
                    ?: ($details['description'] ?? null)
                    ?: $type;

                $status = strtolower(
                    (string) ($row['status'] ?? '')
                );

                $formattedStatus = match ($status) {
                    'successful' => 'Successful',
                    'pending' => 'Pending',
                    'processing' => 'Processing',
                    'failed' => 'Failed',
                    'cancelled',
                    'canceled' => 'Cancelled',

                    default => ucwords(
                        str_replace(
                            ['_', '-'],
                            ' ',
                            $status
                        )
                    ),
                };

                return [
                    'reference' => $row['transaction_id_reference'] ?? null,

                    'type' => $type,

                    'description' => $description,

                    'amount' => number_format(
                        (float) ($row['amount'] ?? 0),
                        2,
                        '.',
                        ''
                    ),

                    'currency' => $row['currency'] ?? null,

                    'direction' => $row['direction'] ?? null,

                    'status' => $formattedStatus,

                    'date' => $row['transaction_date_time'] ?? null,

                    'fee' => number_format(
                        (float) ($row['fees'] ?? 0),
                        2,
                        '.',
                        ''
                    ),

                    'balance_after' => isset($row['balance_after'])
                        ? number_format(
                            (float) $row['balance_after'],
                            2,
                            '.',
                            ''
                        )
                        : null,

                    'details' => $row['details'] ?? null,
                ];
            }),

            'summary' => $historyService->summary($rows),

            'filters' => $this->documentedFilters(),
        ]
    );

    // return Response::successResponse('Statement data fetched successfully.', [
        //     'transactions' => $transactions,
        //     'summary' => $historyService->summary($rows),
        //     'filters' => $this->documentedFilters(),
        // ]);
    
}

        

    public function export(Request $request, TransactionHistoryService $historyService)
    {
        $format = strtolower((string) $request->query('format', 'pdf'));
        $transactions = $historyService->forUser($request->user(), $request->query());
        $summary = $historyService->summary($transactions);
        $filename = 'bitmonie-statement-' . now()->format('Ymd-His');

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($transactions) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, [
                    'source_table',
                    'source_id',
                    'transaction_id_reference',
                    'amount',
                    'currency',
                    'status',
                    'transaction_date_time',
                    'user_sender_id',
                    'recipient_id',
                    'transaction_type',
                    'direction',
                    'fees',
                    'balance_after',
                    'wallet_address',
                    'transaction_hash',
                    'description',
                ]);

                foreach ($transactions as $row) {
                    fputcsv($handle, [
                        $row['source_table'],
                        $row['source_id'],
                        $row['transaction_id_reference'],
                        $row['amount'],
                        $row['currency'],
                        $row['status'],
                        $row['transaction_date_time'],
                        $row['user_sender_id'],
                        $row['recipient_id'],
                        $row['transaction_type'],
                        $row['direction'],
                        $row['fees'],
                        $row['balance_after'],
                        $row['wallet_address'],
                        $row['transaction_hash'],
                        $row['description'],
                    ]);
                }

                fclose($handle);
            }, $filename . '.csv', ['Content-Type' => 'text/csv']);
        }

        if ($format !== 'pdf') {
            return Response::errorResponse('Unsupported export format. Use pdf or csv.', null, 422);
        }

        $pdf = Pdf::loadView('user.sections.pdf.statement-ledger', [
            'user' => $request->user(),
            'transactions' => $transactions,
            'summary' => $summary,
            'filters' => $request->query(),
        ])->setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);

        return $pdf->download($filename . '.pdf');
    }

    public function receipt(Request $request, TransactionHistoryService $historyService, string $source, string $id)
    {
        $transaction = $historyService->findForReceipt($request->user(), $source, $id);

        if (!$transaction) {
            return Response::errorResponse('Transaction not found.', null, 404);
        }

        if ($transaction['status'] !== 'successful') {
            return Response::errorResponse('Receipt is only available for successful transactions.', [
                'status' => $transaction['status'],
            ], 422);
        }

        if (strtolower((string) $request->query('format')) === 'json') {
            return Response::successResponse('Transaction receipt fetched successfully.', [
                'receipt' => $transaction,
            ]);
        }

        $pdf = Pdf::loadView('user.sections.pdf.transaction-receipt', [
            'user' => $request->user(),
            'transaction' => $transaction,
        ])->setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);

        return $pdf->download('receipt-' . $transaction['transaction_id_reference'] . '.pdf');
    }

    protected function documentedFilters(): array
    {
        return [
            'from_date' => 'YYYY-MM-DD',
            'to_date' => 'YYYY-MM-DD',
            'type' => 'transaction type/category, e.g. deposit, withdrawal, transfer, flex_savings',
            'status' => 'successful|pending|processing|failed|cancelled',
            'currency' => 'NGN|USD|USDT|USDC etc.',
            'direction' => 'credit|debit',
            'source' => 'all|order_transactions|transactions|ramp_transactions|graph_transactions|savings_transactions|autosave_transactions',
            'search' => 'reference, source id, hash, or description',
            'format' => 'pdf|csv on export endpoint',
        ];
    }

    
}
