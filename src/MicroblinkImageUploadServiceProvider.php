<?php

namespace Microblink\IdImageUpload;

use Illuminate\Support\ServiceProvider;
use Microblink\IdImageUpload\Services\ImageUploadService;

class MicroblinkImageUploadServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/microblink.php',
            'microblink'
        );

        // Register the main service as singleton
        $this->app->singleton('microblink-uploader', function ($app) {
            return new ImageUploadService(
                config('microblink')
            );
        });

        // Bind the service class
        $this->app->singleton(ImageUploadService::class, function ($app) {
            return $app->make('microblink-uploader');
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish config
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/microblink.php' => config_path('microblink.php'),
            ], 'microblink-config');

            $this->publishes([
                __DIR__ . '/../routes/api.php' => base_path('routes/microblink.php'),
            ], 'microblink-routes');
        }

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            'microblink-uploader',
            ImageUploadService::class,
        ];
    }
}
