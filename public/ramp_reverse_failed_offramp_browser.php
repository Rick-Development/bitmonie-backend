<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

const RAMP_REVERSE_MERCHANT_REFERENCE = 'OFFRAMP_108_8WYnvd4XvT';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function renderPage(string $title, string $body): void
{
    header('Content-Type: text/html; charset=UTF-8');

    echo '<!DOCTYPE html>';
    echo '<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . h($title) . '</title>';
    echo '<style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;color:#111827;margin:0;padding:24px}
        .card{max-width:760px;margin:0 auto;background:#fff;border:1px solid #dbe2ea;border-radius:12px;padding:24px;box-shadow:0 10px 30px rgba(15,23,42,.08)}
        h1{font-size:24px;margin:0 0 12px}
        p{line-height:1.55}
        code,pre{background:#0f172a;color:#e2e8f0;border-radius:8px}
        code{padding:2px 6px}
        pre{padding:16px;overflow:auto;white-space:pre-wrap;word-break:break-word}
        .actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:16px}
        .btn{display:inline-block;padding:12px 16px;border-radius:8px;text-decoration:none;border:0;cursor:pointer;font-weight:700}
        .btn-primary{background:#0f766e;color:#fff}
        .btn-secondary{background:#1d4ed8;color:#fff}
        .btn-light{background:#e5e7eb;color:#111827}
        input{padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;min-width:180px}
        form{margin:0}
        .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
        .muted{color:#475569}
    </style></head><body><div class="card">';
    echo $body;
    echo '</div></body></html>';
    exit;
}

$run = isset($_GET['run']) && $_GET['run'] === '1';

if (!$run) {
    $body = '<h1>Off-Ramp Reversal Runner</h1>'
        . '<p>This page is locked to merchant reference <code>' . h(RAMP_REVERSE_MERCHANT_REFERENCE) . '</code>.</p>'
        . '<p class="muted">Use the first button to run the recorded amount, or use the second form to send an override such as <code>215</code>.</p>'
        . '<div class="actions">'
        . '<form method="get"><input type="hidden" name="run" value="1"><button class="btn btn-primary" type="submit">Run Recorded Amount</button></form>'
        . '</div>'
        . '<form method="get" style="margin-top:16px">'
        . '<input type="hidden" name="run" value="1">'
        . '<div class="row">'
        . '<input type="text" name="amount" value="215" placeholder="Override amount">'
        . '<button class="btn btn-secondary" type="submit">Run With Override</button>'
        . '</div></form>'
        . '<p style="margin-top:16px"><a class="btn btn-light" href="?run=1&amount=215">Quick Run: 215 Override</a></p>';

    renderPage('Off-Ramp Reversal Runner', $body);
}

function findLaravelBasePath(string $startDirectory): ?string
{
    $candidates = [];
    $current = rtrim($startDirectory, DIRECTORY_SEPARATOR);

    for ($depth = 0; $depth <= 4; $depth++) {
        if ($current === '') {
            break;
        }

        $candidates[] = $current;

        $parent = dirname($current);
        if ($parent === $current) {
            break;
        }

        $current = $parent;
    }

    foreach ($candidates as $candidate) {
        $autoload = $candidate . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        $bootstrap = $candidate . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

        if (is_file($autoload) && is_file($bootstrap)) {
            return $candidate;
        }
    }

    return null;
}

$amount = trim((string) ($_GET['amount'] ?? ''));

if ($amount !== '' && !preg_match('/^\d+(?:\.\d+)?$/', $amount)) {
    renderPage('Invalid Amount', '<h1>Invalid Amount</h1><p>Amount must be numeric.</p><p><a class="btn btn-light" href="' . h($_SERVER['PHP_SELF'] ?? 'ramp_reverse_failed_offramp_browser.php') . '">Back</a></p>');
}

try {
    $basePath = findLaravelBasePath(__DIR__);

    if ($basePath === null) {
        throw new RuntimeException('Could not locate Laravel base path. Put this file inside the project root or public folder.');
    }

    require $basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

    $app = require $basePath . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    $parameters = [
        'merchant_reference' => RAMP_REVERSE_MERCHANT_REFERENCE,
    ];

    if ($amount !== '') {
        $parameters['--amount'] = $amount;
    }

    $exitCode = $kernel->call('ramp:reverse-failed-offramp', $parameters);
    $output = $kernel->output();

    $body = '<h1>Command Result</h1>'
        . '<p><strong>Merchant Reference:</strong> <code>' . h(RAMP_REVERSE_MERCHANT_REFERENCE) . '</code></p>'
        . '<p><strong>Laravel Base Path:</strong> <code>' . h($basePath) . '</code></p>'
        . '<p><strong>Amount Override:</strong> <code>' . h($amount === '' ? 'none' : $amount) . '</code></p>'
        . '<p><strong>Exit Code:</strong> <code>' . h((string) $exitCode) . '</code></p>'
        . '<pre>' . h($output) . '</pre>'
        . '<p class="muted">Delete this file from the server after use.</p>'
        . '<p><a class="btn btn-light" href="' . h($_SERVER['PHP_SELF'] ?? 'ramp_reverse_failed_offramp_browser.php') . '">Back</a></p>';

    renderPage('Command Result', $body);
} catch (Throwable $e) {
    $body = '<h1>Runner Error</h1>'
        . '<p>The browser runner could not execute the Laravel command.</p>'
        . '<p><strong>Error:</strong> <code>' . h($e->getMessage()) . '</code></p>'
        . '<p><strong>File:</strong> <code>' . h($e->getFile()) . ':' . h((string) $e->getLine()) . '</code></p>'
        . '<p class="muted">This usually means the file was uploaded in a location where it could not find <code>vendor/autoload.php</code> and <code>bootstrap/app.php</code>.</p>'
        . '<p><a class="btn btn-light" href="' . h($_SERVER['PHP_SELF'] ?? 'ramp_reverse_failed_offramp_browser.php') . '">Back</a></p>';

    renderPage('Runner Error', $body);
}
