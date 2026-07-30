<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // "bucket"/"url" deliberately omitted here — MediaStorage::disk()
        // injects them per "purpose" at runtime (see 'media.buckets' below),
        // so one set of AWS credentials can address multiple buckets.
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // Cloudflare R2 — S3-API-compatible, so it uses the same "s3" driver
        // under a separate disk name/credentials. "bucket"/"url" injected per
        // purpose at runtime, same reasoning as the "s3" disk above.
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Media Library — Multi-Bucket Storage
    |--------------------------------------------------------------------------
    |
    | Uploaded files are grouped by "purpose" (whatsapp_media, exports, ...)
    | and each purpose can live in its own bucket — e.g. one bucket for all
    | WhatsApp template media, a separate one for exported CSVs — while
    | sharing the same provider credentials above. Adding a new purpose later
    | is just a new env var + one entry below, no code change (see
    | App\Services\Media\MediaStorage).
    |
    | "provider" picks which disk actually stores files: "public" (local,
    | zero-setup dev default), "r2" (Cloudflare), or "s3" (Amazon).
    |
    */

    'media' => [
        'provider' => env('MEDIA_STORAGE_PROVIDER', 'public'),

        'buckets' => [
            'whatsapp_media' => [
                'r2' => ['bucket' => env('R2_BUCKET_WHATSAPP_MEDIA'), 'url' => env('R2_URL_WHATSAPP_MEDIA')],
                's3' => ['bucket' => env('AWS_BUCKET_WHATSAPP_MEDIA'), 'url' => env('AWS_URL_WHATSAPP_MEDIA')],
            ],
            'exports' => [
                'r2' => ['bucket' => env('R2_BUCKET_EXPORTS'), 'url' => env('R2_URL_EXPORTS')],
                's3' => ['bucket' => env('AWS_BUCKET_EXPORTS'), 'url' => env('AWS_URL_EXPORTS')],
            ],
            'form_uploads' => [
                'r2' => ['bucket' => env('R2_BUCKET_FORM_UPLOAD'), 'url' => env('R2_URL_FORM_UPLOAD')],
                's3' => ['bucket' => env('AWS_BUCKET_FORM_UPLOAD'), 'url' => env('AWS_URL_FORM_UPLOAD')],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
