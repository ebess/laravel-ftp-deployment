<?php

return [

    /**
     * include paths to deployment
     */
    'includes' => [
        'app',
        'bootstrap',
        'config',
        'public',
        'resources/views',
        'resources/lang',
        'storage/app',
        'storage/logs',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'vendor'
    ],

    // exclude paths from deploying
    'excludes' => [
        'storage/app/*',
        'storage/logs/*',
        'storage/framework/cache/*',
        'storage/framework/sessions/*',
        'storage/framework/views/*'
    ],

    // endpoints
    'servers' => [

        'server-name' => [
            'config'        => 'config-name',
            'disk'          => 'cloud-disk',
            'deploy-url'    => 'web-hook-url',
            'uploads'       => [
                // path_src => path_dst
            ]
        ]

    ]

];