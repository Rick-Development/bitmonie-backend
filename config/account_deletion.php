<?php

return [
    'notification_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('ACCOUNT_DELETION_NOTIFY_EMAILS', ''))
    ))),

    'duplicate_window_minutes' => (int) env('ACCOUNT_DELETION_DUPLICATE_WINDOW_MINUTES', 15),
];
