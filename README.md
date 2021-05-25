# Laravel 8 - FTP Deployment with WebHook

### Installation

```bash
composer require zach2825/laravel-ftp-deployment
php artisan vendor:publish
```

### Setup

Create a filesystem disk to deploy to in **config/filesystem.php**

```php
        'example' => [
            'driver'   => 'ftp',
            'host'     => env('DEV_FTP_HOST'),
            'username' => env('DEV_FTP_USER'),
            'password' => env('DEV_FTP_PASS'),

            // Optional FTP Settings
            // 'port'     => 21,
            // 'root'     => env('DEV_FTP_ROOT', '/var/www/html/example'),
            // 'passive'  => true,
            // 'ssl'      => true,
            // 'timeout'  => 30,
        ],
```

Adjust which files should be deployed and hooks in **config/ftp-deployment.php**

### Deploy to server

```bash
php artisan deploy:server <servername> <--refresh=0> <--debug=1>
```

Use refresh to refresh the database migrations and run the seeders. If flag not set, only migration will be run.
