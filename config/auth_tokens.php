<?php

return [
    'refresh_ttl_days' => max(30, min(90, (int) env('REFRESH_TOKEN_TTL_DAYS', 60))),
    'require_https' => (bool) env('AUTH_REQUIRE_HTTPS', true),
];
