<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GraphTransaction;
use App\Models\GraphWallet;
use Illuminate\Http\Request;

class GraphController extends Controller
{
    /**
     * Display a listing of Graph Wallets.
     */
    public function wallets()
    {
        $page_title = "Graph USD Wallets";
        $wallets = GraphWallet::with('user')->orderByDesc('id')->paginate(15);
        
        return view('admin.sections.graph.wallets', compact('page_title', 'wallets'));
    }

    /**
     * Display a listing of Graph Transactions.
     */
    public function transactions()
    {
        $page_title = "Graph Transactions";
        $logs = GraphTransaction::with('user')->orderByDesc('created_at')->paginate(15);

        return view('admin.sections.graph.transactions', compact('page_title', 'logs'));
    }

    /**
     * Display Transaction Details.
     */
    public function transactionDetails($id)
    {
        $page_title = "Transaction Details";
        $transaction = GraphTransaction::with('user')->findOrFail($id);

        return view('admin.sections.graph.details', compact('page_title', 'transaction'));
    }
}
