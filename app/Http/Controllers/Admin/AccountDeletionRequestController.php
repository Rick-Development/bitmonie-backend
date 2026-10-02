<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AccountDeletionRequestController extends Controller
{
    public function index(Request $request)
    {
        $page_title = 'Account Deletion Requests';
        $status = $request->query('status');
        $search = $request->query('search');

        $requests = AccountDeletionRequest::query()
            ->when(in_array($status, AccountDeletionRequest::STATUSES, true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->search($search)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.sections.account-deletion-request.index', compact(
            'page_title',
            'requests',
            'status',
            'search'
        ));
    }

    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target' => 'required|integer|exists:account_deletion_requests,id',
            'status' => 'required|string|in:' . implode(',', AccountDeletionRequest::STATUSES),
        ]);

        $validated = $validator->validate();
        $deletionRequest = AccountDeletionRequest::findOrFail($validated['target']);

        $deletionRequest->update([
            'status' => $validated['status'],
            'processed_at' => in_array($validated['status'], [
                AccountDeletionRequest::STATUS_COMPLETED,
                AccountDeletionRequest::STATUS_REJECTED,
            ], true) ? now() : null,
        ]);

        return back()->with(['success' => ['Status updated successfully!']]);
    }
}
