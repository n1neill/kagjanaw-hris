<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Storage
    |--------------------------------------------------------------------------
    |
    | Backups are zip archives on this disk (storage/app/backups). Each holds
    | a SQL dump of the database, every file on the uploads disk (201
    | documents, leave attachments) and a manifest. Copy them to external
    | storage regularly: a backup on the same server does not survive a
    | disk failure.
    |
    */

    'disk' => 'backups',

    'files_disk' => 'local',

    /*
    |--------------------------------------------------------------------------
    | Tables Left Out
    |--------------------------------------------------------------------------
    |
    | Sessions, cache and queue tables are neither backed up nor replaced on
    | restore, so the administrator stays signed in while restoring.
    |
    */

    'excluded_tables' => [
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | The largest backup the administrator may upload, in megabytes. PHP's
    | upload_max_filesize and post_max_size must allow it too.
    |
    */

    'max_upload_mb' => (int) env('BACKUP_MAX_UPLOAD_MB', 512),

];
