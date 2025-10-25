<?php

namespace SwaggerAuto\Providers;

use Illuminate\Support\ServiceProvider;
use SwaggerAuto\Commands\GenerateDocs;
use SwaggerAuto\Http\Middleware\HandleCors;
use SwaggerAuto\Http\Middleware\DocumentationAuth;
use Illuminate\Support\Facades\Route;
use SwaggerAuto\Storage\StorageManager;

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
                $storageManager = new StorageManager(config('docs-generate.storage.default', 'local'));
                $outputPath = config('docs-generate.output_path');
                $relativePath = $this->getRelativePath($outputPath);

                if (!$storageManager->exists($relativePath)) {
                    return response()->json([
                        'error' => 'API documentation not found. Please run: php artisan docs:generate'
                    ], 404);
                }

                $content = $storageManager->get($relativePath);

                if ($content === null) {
                    return response()->json([
                        'error' => 'Failed to read API documentation from storage'
                    ], 500);
                }

                return response($content, 200, [
                    'Content-Type' => 'application/json'
                ]);
            })->name('docs.json');
        });
    }
    
    protected function getRelativePath(string $fullPath): string
    {
        $storageRoot = config('docs-generate.storage.drivers.local.root', storage_path('app'));
        
        if (str_starts_with($fullPath, $storageRoot)) {
            return ltrim(str_replace($storageRoot, '', $fullPath), '/');
        }
        
        return basename($fullPath);
    }
}

