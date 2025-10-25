<?php

namespace SwaggerAuto\Providers;

use Illuminate\Support\ServiceProvider;
use SwaggerAuto\Commands\GenerateDocs;
use SwaggerAuto\Http\Middleware\HandleCors;
use SwaggerAuto\Http\Middleware\DocumentationAuth;
use Illuminate\Support\Facades\Route;

class DocsGenerateServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/docs-generate.php', 'docs-generate');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
                $this->commands([
                    GenerateDocs::class,
                ]);

            $this->publishes([
                __DIR__.'/../config/docs-generate.php' => config_path('docs-generate.php'),
            ], 'docs-generate');

            $this->publishes([
                __DIR__.'/../../resources/views' => resource_path('views/vendor/docs-generate'),
            ], 'docs-generate');
        }

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'docs-generate');

        $this->app['router']->aliasMiddleware('docs.cors', HandleCors::class);
        $this->app['router']->aliasMiddleware('docs.auth', DocumentationAuth::class);
        
        $this->registerRoutes();
    }

    protected function registerRoutes()
    {
        $routesConfig = config('docs-generate.routes');

        if (!$routesConfig['enabled']) {
            return;
        }

        $documentationPath = $routesConfig['documentation_path'];
        $jsonPath = $routesConfig['json_path'];
        $middleware = $routesConfig['middleware'];

        Route::middleware($middleware)->group(function () use ($documentationPath, $jsonPath) {
            Route::get($documentationPath, function () {
                return view('docs-generate::api-ui');
            })->name('docs.ui');

            Route::get($jsonPath, function () {
                $path = config('docs-generate.output_path');

                if (!file_exists($path)) {
                    return response()->json([
                        'error' => 'API documentation not found. Please run: php artisan docs:generate'
                    ], 404);
                }

                $content = file_get_contents($path);

                return response($content, 200, [
                    'Content-Type' => 'application/json'
                ]);
            })->name('docs.json');
        });
    }
}

