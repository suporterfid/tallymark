<?php

return [
    // The integration is inert until an operator explicitly enables and configures it.
    'enabled' => filter_var(env('TASKCONNECT_ENABLED', false), FILTER_VALIDATE_BOOL),
    'base_url' => env('TASKCONNECT_BASE_URL', ''),
    'api_key' => env('TASKCONNECT_API_KEY', ''),
    'tenant_id' => env('TASKCONNECT_TENANT_ID', ''),
    'environment_id' => env('TASKCONNECT_ENVIRONMENT_ID', ''),
    'goal_conversion_url' => env('TASKCONNECT_GOAL_CONVERSION_URL', ''),
    'run_url_template' => env('TASKCONNECT_RUN_URL_TEMPLATE', ''),
];
