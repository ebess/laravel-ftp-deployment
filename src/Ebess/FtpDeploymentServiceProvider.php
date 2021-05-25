<?php

namespace Ebess;

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
        $this->registerConfig();
        $this->registerCommands();
    }

    protected function registerConfig()
    {
        $this->mergeConfigFrom($this->configPath(), 'ftp-deployment');
    }

    protected function registerCommands()
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands(
                [
                        DeployServerCommand::class,
                ]
        );
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom($this->configPath(), 'ftp-deployment');

        $this->app->singleton(
                DeployServerCommand::class,
                function ($app) {
                    return new DeployServerCommand(
                            config('ftp-deployment'),
                            $app['files'],
                            $app['filesystem']
                    );
                }
        );
    }

    /**
     * Set the config path
     *
     * @return string
     */
    protected function configPath()
    {
        return __DIR__ . '/../config/ftp-deployment.php';
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['command.ftp-deployment.server'];
    }
}
