<?php
/**
 * Cron API Sync Configuration
 * Copy this file to cron_config.php and update the values
 * DO NOT commit cron_config.php if it contains real credentials
 */

return [
    // API base URL (without query params)
    'api_base_url' => 'https://abc.com/my-store/api/index.php',

    // Account number for API
    'acc_num' => '12345',

    // Bearer token for Authorization header
    'bearer_token' => 'my_secure_token_123',

    // If API expects token in URL (?token=xxx), set: 'token_param' => 'token'
    // Leave null/commented to use Authorization: Bearer header
    // 'token_param' => 'token',

    // How many days back to fetch (1 = yesterday only)
    'days_back' => 1,
];
