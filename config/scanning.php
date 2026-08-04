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

    'model' => env('SCAN_MODEL', 'gemini-2.0-flash'),

    /*
    |--------------------------------------------------------------------------
    | Gemini Connection
    |--------------------------------------------------------------------------
    */

    'gemini' => [
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
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

];
