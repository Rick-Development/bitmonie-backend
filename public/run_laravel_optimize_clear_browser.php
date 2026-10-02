<?php

declare(strict_types=1);

function detectLaravelBasePath(): ?string
{
    $candidates = [
        __DIR__,
        dirname(__DIR__),
        dirname(__DIR__, 2),
    ];

    foreach ($candidates as $candidate) {
        if (
            is_file($candidate . DIRECTORY_SEPARATOR . 'artisan') &&
            is_file($candidate . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php') &&
            is_file($candidate . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')
        ) {
            return $candidate;
        }
    }

    return null;
}

function requireLaravelCoreClass(string $basePath, string $className, string $relativePath): void
{
    if (class_exists($className, false)) {
        return;
    }

    $fullPath = $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!is_file($fullPath)) {
        throw new RuntimeException(sprintf(
            'Missing required Laravel core file: %s',
            $fullPath
        ));
    }

    require_once $fullPath;

    if (!class_exists($className, false)) {
        throw new RuntimeException(sprintf(
            'Laravel core class %s was not loaded from %s',
            $className,
            $fullPath
        ));
    }
}

$basePath = detectLaravelBasePath();
$hasFramework = $basePath !== null;
$shouldRun = isset($_GET['run']) && $_GET['run'] === '1';
$output = null;
$exitCode = null;
$errorMessage = null;

if ($shouldRun && $hasFramework) {
    try {
        require $basePath . '/vendor/autoload.php';
        requireLaravelCoreClass($basePath, App\Exceptions\Handler::class, 'app/Exceptions/Handler.php');
        requireLaravelCoreClass($basePath, App\Http\Kernel::class, 'app/Http/Kernel.php');
        requireLaravelCoreClass($basePath, App\Console\Kernel::class, 'app/Console/Kernel.php');

        $app = require $basePath . '/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

        $exitCode = $kernel->call('optimize:clear');
        $output = $kernel->output();
    } catch (Throwable $throwable) {
        $exitCode = 1;
        $errorMessage = $throwable->getMessage();
    }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel Cache Clear Runner</title>
    <style>
        body {
            margin: 0;
            padding: 32px 18px;
            background: #f5f7fb;
            color: #18202a;
            font: 16px/1.5 Arial, sans-serif;
        }
        .card {
            max-width: 860px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .head {
            padding: 24px 28px;
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            color: #fff;
        }
        .body {
            padding: 28px;
        }
        .btn {
            display: inline-block;
            padding: 12px 18px;
            border-radius: 10px;
            background: #1d4ed8;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
        }
        .btn:hover {
            background: #1e40af;
        }
        .meta {
            margin: 0 0 18px;
            padding: 16px;
            border-radius: 12px;
            background: #eef6ff;
        }
        .warn {
            background: #fff7ed;
        }
        .error {
            background: #fef2f2;
            color: #991b1b;
        }
        pre {
            margin: 0;
            padding: 16px;
            overflow: auto;
            border-radius: 12px;
            background: #0f172a;
            color: #e2e8f0;
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="head">
            <h1 style="margin:0;font-size:28px;">Laravel Cache Clear Runner</h1>
            <p style="margin:8px 0 0;">This page only runs <code style="color:#dbeafe;">php artisan optimize:clear</code>.</p>
        </div>
        <div class="body">
            <?php if (!$hasFramework): ?>
                <div class="meta error">
                    <strong>Laravel project not found.</strong><br>
                    Upload this file into the Laravel project root or the <code>public</code> folder.
                </div>
            <?php else: ?>
                <div class="meta">
                    <strong>Laravel Base Path:</strong> <?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?><br>
                    <strong>Command:</strong> php artisan optimize:clear
                </div>

                <?php if (!$shouldRun): ?>
                    <p>Use the button below to clear cached Laravel files once.</p>
                    <p><a class="btn" href="?run=1">Run Cache Clear</a></p>
                <?php else: ?>
                    <div class="meta <?= $exitCode === 0 ? '' : 'error' ?>">
                        <strong>Exit Code:</strong> <?= (int) $exitCode ?><br>
                        <?php if ($errorMessage !== null): ?>
                            <strong>Error:</strong> <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                        <?php else: ?>
                            <strong>Status:</strong> <?= $exitCode === 0 ? 'Cache clear completed successfully.' : 'Cache clear failed.' ?>
                        <?php endif; ?>
                    </div>

                    <pre><?= htmlspecialchars((string) $output, ENT_QUOTES, 'UTF-8') ?></pre>

                    <div class="meta warn" style="margin-top:18px;">
                        Delete this file from the server after use.
                    </div>

                    <p style="margin-top:18px;"><a class="btn" href="?">Back</a></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
