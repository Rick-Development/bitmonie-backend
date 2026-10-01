<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Savings;
use Illuminate\Http\Request;

class SavingsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = "Savings Logs";
        $logs = Savings::with('user')->orderBy('id', 'desc')->paginate(10);
        return view('admin.sections.savings.index', compact('page_title', 'logs'));
    }
}
