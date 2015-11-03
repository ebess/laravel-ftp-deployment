# Laravel 5 - FTP Deployment with WebHook

### Installation
```bash
composer require ebess/ftp-deployment
php artisan vendor:publish
```

### Setup
Create a filesystem disk to deploy to in **config/filesystem.php**
```php
    'disks' => [
      // ...
      'deployment' => [
        'driver'    => 'ftp',
        'host'      => 'ftp.server.org',
        'port'      => 21,
        'username'  => 'ftp-user',
        'password'  => 'ftp-password',
        'passive'   => true,
        'root'      => '/'
      ],
      // ...
    ]
```
Adjust which files should be deployed and hooks in **config/ftp-deployment.php**
### Deploy to server
```bash
php artisan deploy:server <servername> <--refresh=0> <--debug=1>
```
