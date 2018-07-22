<?php

namespace Oxycoder\ApiDoc;

use Illuminate\Support\ServiceProvider;
use Oxycoder\ApiDoc\Commands\UpdateDocumentation;
use Oxycoder\ApiDoc\Commands\GenerateDocumentation;

class ApiDocGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views/', 'apidoc');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'apidoc');

        $this->publishes([
            __DIR__.'/../../resources/lang' => $this->resource_path('lang/vendor/apidoc'),
            __DIR__.'/../../resources/views' => $this->resource_path('views/vendor/apidoc'),
            __DIR__.'/../../resources/assets' => $this->resource_path('views/vendor/apidoc/assets'),
        ]);
    }

    /**
     * Register the API doc commands.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('apidoc.generate', function () {
            return new GenerateDocumentation();
        });
        $this->app->singleton('apidoc.update', function () {
            return new UpdateDocumentation();
        });

        $this->commands([
            'apidoc.generate',
            'apidoc.update',
        ]);
    }

    /**
     * Return a fully qualified path to a given file.
     *
     * @param string $path
     *
     * @return string
     */
    public function resource_path($path = '')
    {
        return app()->basePath().'/resources'.($path ? '/'.$path : $path);
    }
}
