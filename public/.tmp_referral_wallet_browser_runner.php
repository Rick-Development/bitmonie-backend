<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

$token = 'iwudede';

if (!isset($_GET['token']) || !hash_equals($token, (string) $_GET['token'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Forbidden\n";
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);

$commands = [
    'migrate --force',
    'optimize:clear',
    'route:clear',
    'view:clear',
];

echo "Starting referral wallet deployment commands...\n\n";

foreach ($commands as $command) {
    echo ">>> php artisan {$command}\n";
    $exitCode = $kernel->call($command);
    echo $kernel->output();
    echo "Exit code: {$exitCode}\n\n";
}

$kernel->terminate($app['request'], new \Symfony\Component\HttpFoundation\Response());

echo "Done.\n";
echo "Delete this file immediately after it runs.\n";