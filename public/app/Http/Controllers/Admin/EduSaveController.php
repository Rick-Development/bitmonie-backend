<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EduSave;
use Illuminate\Http\Request;

class EduSaveController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = "EduSave Logs";
        $logs = EduSave::with('user')->orderBy('id', 'desc')->paginate(10);
        return view('admin.sections.edusave.index', compact('page_title', 'logs'));
    }

    // Add other methods if needed (e.g. show, update status)
}
