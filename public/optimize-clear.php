<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

$token = 'cmart-clear-20260406-b84d2a61';
$providedToken = (string) ($_GET['token'] ?? '');

if ($providedToken === '' || !hash_equals($token, $providedToken)) {
    http_response_code(403);
    echo "Forbidden.\n";
    echo "Use the exact token in the URL, then delete this file immediately.\n";
    exit;
}

$projectRoot = realpath(__DIR__ . '/..');
$artisan = $projectRoot ? $projectRoot . DIRECTORY_SEPARATOR . 'artisan' : null;

if (!$projectRoot || !$artisan || !is_file($artisan)) {
    http_response_code(500);
    echo "Could not find the Laravel artisan file.\n";
    exit;
}

$phpCandidates = array_values(array_unique(array_filter([
    PHP_BINARY,
    'php',
    '/usr/bin/php',
    '/usr/local/bin/php',
    '/opt/cpanel/ea-php82/root/usr/bin/php',
    '/opt/cpanel/ea-php81/root/usr/bin/php',
    '/opt/cpanel/ea-php80/root/usr/bin/php',
])));

$lastOutput = [];
$lastExitCode = 127;
$lastCommand = '';

foreach ($phpCandidates as $phpBinary) {
    $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($artisan) . ' optimize:clear 2>&1';
    $output = [];
    $exitCode = 127;

    exec($command, $output, $exitCode);

    $lastCommand = $command;
    $lastOutput = $output;
    $lastExitCode = $exitCode;

    if ($exitCode === 0) {
        echo "Laravel optimize:clear completed successfully.\n\n";
        echo "Command:\n{$command}\n\n";
        echo "Output:\n" . implode("\n", $output) . "\n";
        echo "\nDelete public/optimize-clear.php from the server now.\n";
        exit;
    }
}

http_response_code(500);
echo "Failed to run Laravel optimize:clear.\n\n";
echo "Last command tried:\n{$lastCommand}\n\n";
echo "Exit code: {$lastExitCode}\n\n";
echo "Output:\n" . implode("\n", $lastOutput) . "\n";