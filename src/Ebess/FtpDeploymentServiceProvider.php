<?php namespace Ebess;

use Ebess\Console\DeployServerCommand;
use Illuminate\Support\ServiceProvider;

class FtpDeploymentServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__ . '/../config/ftp-deployment.php';
        $this->publishes([
            $configPath => base_path('config/ftp-deployment.php')
        ], 'ftp-deployment');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $configPath = __DIR__ . '/../config/ftp-deployment.php';
        $this->mergeConfigFrom($configPath, 'ftp-deployment');

        $this->app['command.ftp-deployment.server'] = $this->app->share(function ($app) {
            return new DeployServerCommand($app['config']['ftp-deployment'], $app['files'], $app['filesystem']);
        });

        $this->commands('command.ftp-deployment.server');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('command.ftp-deployment.server');
    }

}
