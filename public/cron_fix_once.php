<?php

$token = 'bitmonie-cron-setup-8294729';

if (!isset($_GET['token']) || !hash_equals($token, $_GET['token'])) {
    http_response_code(403);
    exit('Invalid token');
}

$projectPath = '/home/bitmonie/api';
$phpPath = '/usr/local/apps/php84/bin/php';

$scheduleLine = '* * * * * cd /home/bitmonie/api && /usr/local/apps/php84/bin/php artisan schedule:run >> /dev/null 2>&1';

$action = $_GET['action'] ?? 'install';

echo "<pre>";

if ($action === 'install') {
    $current = shell_exec('crontab -l 2>/dev/null') ?: '';

    if (strpos($current, 'artisan schedule:run') === false) {
        $newCron = rtrim($current) . PHP_EOL . $scheduleLine . PHP_EOL;

        $tmp = tempnam(sys_get_temp_dir(), 'cron_');
        file_put_contents($tmp, $newCron);

        exec('crontab ' . escapeshellarg($tmp) . ' 2>&1', $output, $code);
        unlink($tmp);

        echo $code === 0 ? "Cron installed successfully.\n\n" : "Cron install failed.\n\n";
        echo implode("\n", $output);
    } else {
        echo "Scheduler cron already exists.\n\n";
    }

    echo "Current crontab:\n";
    echo shell_exec('crontab -l 2>&1');
    exit;
}

if ($action === 'test') {
    echo shell_exec("cd {$projectPath} && {$phpPath} artisan schedule:run 2>&1");
    exit;
}

if ($action === 'delete') {
    @unlink(__FILE__);
    echo "Deleted cron_fix_once.php";
    exit;
}

echo "Unknown action.";