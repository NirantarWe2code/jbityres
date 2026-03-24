<?php
/**
 * Cron API Sync Configuration
 * Update these values with your actual API credentials
 * For production: consider adding this file to .gitignore
 */

return [
    // API base URL (without query params)
    'api_base_url' => 'https://wholesale.jbityres.com.au/my-store/api/index.php',

    // Account number for API
    'acc_num' => '12105', #12105

    // Bearer token for Authorization header
    'bearer_token' => 'my_secure_token_123',

    // If API expects token in URL (e.g. ?token=xxx), set: 'token_param' => 'token'
    // Leave null to use Authorization: Bearer header
    'token_param' => 'token',

    // How many days back to fetch (1 = yesterday only)
    'days_back' => 1,
];
