<?php

return [
    'enabled' => (bool) env('FIDES_ENABLED', false),
    'tenant_connection' => env('FIDES_TENANT_CONNECTION', env('DB_CONNECTION', 'mysql')),
    'platform_connection' => 'platform',
    'session_tenant_key' => 'fides_tenant_id',
    'login_candidates_key' => 'fides_login_candidates',
];
