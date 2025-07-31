<?php

namespace Novay\Logify;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire; 
use Novay\Logify\Http\Livewire\LogViewer; 

class LogifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/logify.php', 'logify'
        );
    }

    public function boot(): void
    {
        $this->configureLoggingChannel();
        $this->registerResources();
        $this->registerLivewireComponents();
        $this->publishAssets();
    }

    protected function configureLoggingChannel(): void
    {
        $config = $this->app->get('config');
        $logifyChannelConfig = $config->get('logify.channel');

        if ($logifyChannelConfig) {
            $config->set('logging.channels.' . $logifyChannelConfig['name'], $logifyChannelConfig);
        }
    }

    protected function registerResources(): void
    {
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'logify');
    }

    protected function registerLivewireComponents(): void
    {
        Livewire::component('logify::log-viewer', LogViewer::class);
    }

    protected function publishAssets(): void
    {
        $this->publishes([
            __DIR__.'/../config/logify.php' => config_path('logify.php'),
        ], 'logify-config');
    }
}