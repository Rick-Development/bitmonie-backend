<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class GenerateReportPdf extends Command
{
    protected $signature = 'report:pdf';
    protected $description = 'Generates a PDF report of our session';

    public function handle()
    {
        $markdown = file_get_contents('/home/bitmonie/.gemini/antigravity-ide/brain/99db1220-aaf0-48f5-bc36-9fbdfcd038c2/walkthrough.md');
        $htmlContent = Str::markdown($markdown);
        
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6; margin: 40px; }
                h1 { color: #f9b707; font-size: 24px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
                h2 { color: #444; font-size: 18px; margin-top: 30px; }
                h3 { color: #666; font-size: 16px; margin-top: 20px; }
                ul { margin-bottom: 20px; }
                li { margin-bottom: 8px; }
                code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
                strong { color: #111; }
            </style>
        </head>
        <body>' . $htmlContent . '</body></html>';

        $pdf = Pdf::loadHTML($html);
        $path = public_path('assets/BitMonie_Development_Report.pdf');
        
        $pdf->save($path);
        
        $this->info("PDF Report generated at: " . $path);
    }
}
