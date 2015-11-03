<?php

return [

    /**
     * command to run before starting the deployment
     */
    'before' => [
    ],

    /**
     * run following commands and remote system after deployment
     *
     * remember to use the command line command for php if it is not standard!
     */
    'remote' => [
    ],

    /**
     * don't delete when purging
     *
     * only in the /public folder
     */
    'purge_excludes' => [
    ],

    /**
     * include paths to deployment
     */
    'includes' => [
        'artisan',
        'server.php',
        'app',
        'bootstrap',
        'config',
        'public',
        'database',
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
            'php-cli'       => 'php',
            'deploy-url'    => 'web-hook-url',
            'uploads'       => [
                // path_src => path_dst
            ]
        ]

    ]

];