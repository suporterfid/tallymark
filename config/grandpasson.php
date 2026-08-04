<?php

return [
    'outbound_enabled' => filter_var(env('GRANDPASSON_OUTBOUND_ENABLED', false), FILTER_VALIDATE_BOOL),
    'inbound_enabled' => filter_var(env('GRANDPASSON_INBOUND_ENABLED', false), FILTER_VALIDATE_BOOL),

    'base_url' => env('GRANDPASSON_BASE_URL', ''),

    'browser_client_id' => env('GRANDPASSON_BROWSER_CLIENT_ID', ''),
    'browser_client_secret' => env('GRANDPASSON_BROWSER_CLIENT_SECRET', ''),
    'redirect_uri' => env('GRANDPASSON_REDIRECT_URI', ''),
    'login_state_ttl_seconds' => (int) env('GRANDPASSON_LOGIN_STATE_TTL_SECONDS', 600),

    'machine_client_id' => env('GRANDPASSON_MACHINE_CLIENT_ID', ''),
    'machine_client_secret' => env('GRANDPASSON_MACHINE_CLIENT_SECRET', ''),
    'introspect_url' => env('GRANDPASSON_INTROSPECT_URL', env('GRANDPASSON_BASE_URL')
        ? rtrim((string) env('GRANDPASSON_BASE_URL'), '/').'/oauth/introspect'
        : ''),
    'introspection_cache_seconds' => (int) env('GRANDPASSON_INTROSPECTION_CACHE_SECONDS', 30),

    'read_scope' => env('GRANDPASSON_READ_SCOPE', 'analytics:read'),
    'write_scope' => env('GRANDPASSON_WRITE_SCOPE', 'analytics:write'),
    'callback_scope' => env('GRANDPASSON_CALLBACK_SCOPE', 'analytics:callback'),

    /* @var array<string, string> group slug => TallyMark tenant role */
    'group_role_map' => [],
];
