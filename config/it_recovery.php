<?php

return [
    /*
     * Default email for `php artisan lspdms:it-recovery:provision` when no
     * email argument is given. The password is never configured here.
     */
    'email' => env('IT_RECOVERY_EMAIL'),

    /*
     * Optional comma-separated list of client IPs allowed to open
     * /it-recovery (for example "127.0.0.1,::1"). Empty allows any IP.
     */
    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('IT_RECOVERY_ALLOWED_IPS', ''))
    ))),
];
