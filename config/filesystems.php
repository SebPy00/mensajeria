<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],

        's4' => [
            'driver' => 'local',
            'root' => storage_path('Sepsa/APP-PROD/bin/Siedi/outbox-set-lote'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        's5' => [
            'driver' => 'local',
            'root' => storage_path('app/archivos'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        's6' => [
            'driver' => 'local',
            'root' => storage_path('ftp60/Base_Vinanzas/Gestiones'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        's7' => [
            'driver' => 'local',
            'root' => storage_path('reportes'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        's8' => [
             'driver' => 'local',
             'root' => storage_path('bancop/respuesta_sigesa'),
             'url' => env('APP_URL').'/storage',
             'visibility' => 'public',
        ],

	    's9' => [
                'driver' => 'local',
                'root' => storage_path('ftp60/Base_AlarmasPy'),
                'url' => env('APP_URL').'/storage',
                'visibility' => 'public',
            ],

	    's10' => [
	        'driver' => 'local',
	        'root' => storage_path('vinanzas'),
	        'url' => env('APP_URL').'/storage',
 	        'visibility' => 'public',
	    ],

        's11' => [
	        'driver' => 'local',
	        'root' => storage_path('ftp60/Base_RuralCobranzas'),
	        'url' => env('APP_URL').'/storage',
 	        'visibility' => 'public',
	    ],

        's12' => [
	        'driver' => 'local',
	        'root' => storage_path('ftp60/Base_CPH'),
	        'url' => env('APP_URL').'/storage',
 	        'visibility' => 'public',
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
