<?php

declare(strict_types=1);

namespace App\Http\Controllers\Docs;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ApiDocsController extends Controller
{
    /**
     * Serve OpenAPI JSON specification directly.
     */
    public function json(): Response
    {
        $path = resource_path('docs/openapi.json');

        if (file_exists($path)) {
            $content = file_get_contents($path);
            return response($content, 200, [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Cache-Control' => 'no-cache, private',
            ]);
        }

        return response()->json([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Bitmonie / Cryptomart API Reference',
                'version' => '1.0.0',
            ],
            'paths' => new \stdClass(),
        ], 200);
    }

    /**
     * Serve Interactive API Documentation UI (Scalar) with embedded spec.
     */
    public function ui(): Response
    {
        $title = 'Bitmonie / Cryptomart API Reference';
        $path = resource_path('docs/openapi.json');
        
        $specJson = '{}';
        if (file_exists($path)) {
            $specJson = file_get_contents($path);
        }

        $config = json_encode([
            'theme' => 'purple',
            'layout' => 'modern',
            'showSidebar' => true,
            'proxyUrl' => 'https://proxy.scalar.com',
        ], JSON_UNESCAPED_SLASHES);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: #0f172a;
        }
    </style>
</head>
<body>
    <script
        id="api-reference"
        type="application/json"
        data-configuration='{$config}'>
        {$specJson}
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
</body>
</html>
HTML;

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache, private',
        ]);
    }
}
