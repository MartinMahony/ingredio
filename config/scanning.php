<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Extraction Driver
    |--------------------------------------------------------------------------
    |
    | The AI provider used to extract structured recipe data from uploaded
    | screenshots, PDFs, photos, or fetched URLs. Built provider-agnostic so
    | the driver can be swapped without touching the extraction pipeline.
    |
    */

    'driver' => env('SCAN_DRIVER', 'gemini'),

    'model' => env('SCAN_MODEL', 'gemini-flash-latest'),

    /*
    |--------------------------------------------------------------------------
    | Gemini Connection
    |--------------------------------------------------------------------------
    */

    'gemini' => [
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 2048),
    ],

    /*
    |--------------------------------------------------------------------------
    | Source File Retention
    |--------------------------------------------------------------------------
    |
    | When false, uploaded source files are deleted once extraction succeeds
    | (privacy-first, zero storage bloat). Set true to retain originals for
    | re-processing or auditing.
    |
    */

    'keep_source' => (bool) env('SCAN_KEEP_SOURCE', false),

    /*
    |--------------------------------------------------------------------------
    | Upload Constraints
    |--------------------------------------------------------------------------
    */

    'max_upload_kb' => (int) env('SCAN_MAX_UPLOAD_KB', 20480),

    'allowed_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],

    /*
    |--------------------------------------------------------------------------
    | Transient Storage Disk
    |--------------------------------------------------------------------------
    |
    | Disk used to hold source files while a scan is being processed.
    |
    */

    'disk' => env('SCAN_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Paste-a-URL Fetching
    |--------------------------------------------------------------------------
    |
    | Constraints for fetching a recipe page pasted by the user. Fetching is
    | SSRF-guarded: only http/https URLs resolving to public IP addresses are
    | allowed, redirects are re-validated on every hop, and responses over
    | max_bytes are rejected.
    |
    */

    'url' => [
        'timeout' => (int) env('SCAN_URL_TIMEOUT', 15),
        'connect_timeout' => (int) env('SCAN_URL_CONNECT_TIMEOUT', 5),
        'max_bytes' => (int) env('SCAN_URL_MAX_BYTES', 3_000_000),
        'max_redirects' => (int) env('SCAN_URL_MAX_REDIRECTS', 3),
        'user_agent' => env('SCAN_URL_USER_AGENT', 'IngredioBot/1.0 (+recipe import)'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scan Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Per-user caps on how many scans (file or URL) can be submitted, to guard
    | against runaway Gemini usage/cost. These are today's free-tier defaults;
    | if usage tiers are introduced later, these could become per-plan values.
    |
    */

    'rate_limit' => [
        'per_minute' => (int) env('SCAN_RATE_LIMIT_PER_MINUTE', 5),
        'per_day' => (int) env('SCAN_RATE_LIMIT_PER_DAY', 20),
    ],

];
