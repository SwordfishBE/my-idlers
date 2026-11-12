<?php

return [
    // Disable after every existing URL-token client has migrated to Bearer auth.
    'allow_legacy_url_tokens' => env('YABS_ALLOW_LEGACY_URL_TOKENS', true),
];
