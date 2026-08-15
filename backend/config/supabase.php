<?php

use Illuminate\Support\Str;

return [
    'url' => rtrim(env('SUPABASE_URL', ''), '/'),
    'service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
    'enabled' => (bool) env('SUPABASE_AUTH_SYNC', false),
];
