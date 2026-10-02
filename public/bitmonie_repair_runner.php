<?php
/**
 * One-time Bitmonie repair runner.
 * DELETE THIS FILE IMMEDIATELY AFTER USE.
 */

$TOKEN = 'chidozie';

$projectRoot = '/home/bitmonie/api';
$php = '/usr/local/apps/php84/bin/php';
$composer = '/home/bitmonie/composer';
$zipFile = $projectRoot . '/bitmonie_reserved_withdrawal_fix_20260429.zip';

if (!isset($_GET['token']) || $_GET['token'] !== $TOKEN) {
    http_response_code(403);
    exit('Forbidden');
}

$action = $_GET['action'] ?? 'menu';

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function runCommand($command, $projectRoot) {
    $fullCommand = 'cd ' . escapeshellarg($projectRoot) . ' && ' . $command . ' 2>&1';

    echo "<h3>" . h($command) . "</h3>";
    echo "<pre>";

    $output = [];
    $code = 0;
    exec($fullCommand, $output, $code);

    echo h(implode("\n", $output));
    echo "\n\nExit code: " . h($code);
    echo "</pre>";

    return $code;
}

function extractZip($zipFile, $projectRoot) {
    echo "<h3>Extract patch zip</h3><pre>";

    if (!file_exists($zipFile)) {
        echo h("Zip not found: {$zipFile}");
        echo "</pre>";
        return false;
    }

    if (!class_exists('ZipArchive')) {
        echo "ZipArchive PHP extension is not available.\n";
        echo "Use terminal unzip instead.\n";
        echo "</pre>";
        return false;
    }

    $zip = new ZipArchive();

    if ($zip->open($zipFile) !== true) {
        echo h("Could not open zip: {$zipFile}");
        echo "</pre>";
        return false;
    }

    $ok = $zip->extractTo($projectRoot);
    $zip->close();

    echo $ok ? "Extracted successfully.\n" : "Extract failed.\n";
    echo "</pre>";

    return $ok;
}

function listSuspiciousFiles($projectRoot) {
    echo "<h3>Suspicious root files</h3><pre>";

    $matches = [];
    foreach (scandir($projectRoot) as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $projectRoot . '/' . $file;

        if (is_file($path) && preg_match('/gmail|CryptoDeposit|tine/i', $file)) {
            $matches[] = $file;
        }
    }

    if (!$matches) {
        echo "No suspicious root files found.\n";
    } else {
        foreach ($matches as $file) {
            echo h($file) . "\n";
        }

        echo "\nTo delete these, open:\n";
        echo "?token=YOUR_TOKEN&action=delete_suspicious&confirm=DELETE\n";
    }

    echo "</pre>";

    return $matches;
}

function deleteSuspiciousFiles($projectRoot) {
    echo "<h3>Delete suspicious root files</h3><pre>";

    if (($_GET['confirm'] ?? '') !== 'DELETE') {
        echo "Missing confirm=DELETE. Nothing deleted.\n";
        echo "</pre>";
        return;
    }

    foreach (scandir($projectRoot) as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $projectRoot . '/' . $file;

        if (is_file($path) && preg_match('/gmail|CryptoDeposit|tine/i', $file)) {
            echo "Deleting: " . h($file) . "\n";
            unlink($path);
        }
    }

    echo "Done.\n";
    echo "</pre>";
}

echo "<!doctype html><html><head><meta charset='utf-8'><title>Bitmonie Repair Runner</title></head><body>";
echo "<h1>Bitmonie Repair Runner</h1>";
echo "<p><strong>Delete this file after use.</strong></p>";

if ($action === 'menu') {
    echo "<ul>";
    echo "<li><a href='?token=" . h($TOKEN) . "&action=deploy'>Deploy patch zip + clear cache</a></li>";
    echo "<li><a href='?token=" . h($TOKEN) . "&action=dry_run'>Dry-run repair all coins</a></li>";
    echo "<li><a href='?token=" . h($TOKEN) . "&action=repair'>Repair all coins</a></li>";
    echo "<li><a href='?token=" . h($TOKEN) . "&action=dry_run_usdt'>Dry-run repair USDT only</a></li>";
    echo "<li><a href='?token=" . h($TOKEN) . "&action=repair_usdt'>Repair USDT only</a></li>";
    echo "<li><a href='?token=" . h($TOKEN) . "&action=cron'>Show crontab</a></li>";
    echo "<li><a href='?token=" . h($TOKEN) . "&action=suspicious'>Check suspicious root files</a></li>";
    echo "</ul>";
}

if ($action === 'deploy') {
    extractZip($zipFile, $projectRoot);
    runCommand($php . ' ' . escapeshellarg($composer) . ' dump-autoload', $projectRoot);
    runCommand($php . ' artisan optimize:clear', $projectRoot);
    runCommand($php . ' artisan queue:restart', $projectRoot);
}

if ($action === 'dry_run') {
    runCommand($php . ' artisan wallet:repair-reserved --dry-run', $projectRoot);
}

if ($action === 'repair') {
    runCommand($php . ' artisan wallet:repair-reserved', $projectRoot);
}

if ($action === 'dry_run_usdt') {
    runCommand($php . ' artisan wallet:repair-reserved --currency=usdt --dry-run', $projectRoot);
}

if ($action === 'repair_usdt') {
    runCommand($php . ' artisan wallet:repair-reserved --currency=usdt', $projectRoot);
}

if ($action === 'cron') {
    runCommand('crontab -l', $projectRoot);
}

if ($action === 'suspicious') {
    listSuspiciousFiles($projectRoot);
}

if ($action === 'delete_suspicious') {
    deleteSuspiciousFiles($projectRoot);
}

echo "</body></html>";