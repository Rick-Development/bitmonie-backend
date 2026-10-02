<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Services\StatementService;
use App\Jobs\GenerateStatementJob;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatementController extends Controller
{
    /**
     * Get statement of account transactions with filters.
     */
    public function index(Request $request, StatementService $statementService)
    {
    
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'type' => 'nullable|string' // 'deposit', 'withdrawal', 'savings', 'crypto', 'all'
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        $filters = $request->only(['start_date', 'end_date', 'type']);
        $transactions = $statementService->getUnifiedTransactions(auth()->id(), $filters);

        return Response::success([
            'transactions' => $transactions
        ]);
    }

    /**
     * Export statement as PDF or CSV.
     */
    public function export(Request $request, StatementService $statementService)
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|in:pdf,csv',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'type' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        $filters = $request->only(['start_date', 'end_date', 'type']);
        $format = $request->format;
        $userId = auth()->id();

        if ($format === 'pdf' || $format === 'csv') {
            // Dispatch background job for PDF/CSV generation & email
            GenerateStatementJob::dispatch($userId, $filters, $format);
            
            return Response::success([
                'message' => 'Statement generation initiated. A ' . strtoupper($format) . ' will be sent to your email address shortly.'
            ]);
        }
    }
}
