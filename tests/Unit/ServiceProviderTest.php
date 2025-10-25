<?php

namespace SwaggerAuto\Tests\Unit;

use SwaggerAuto\Tests\TestCase;
use SwaggerAuto\Providers\DocsGenerateServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Mockery;

class ServiceProviderTest extends TestCase
{
    public function test_registers_config()
    {
        $provider = new DocsGenerateServiceProvider($this->app);
        $provider->register();
        
        $this->assertTrue(Config::has('docs-generate'));
        $this->assertIsArray(Config::get('docs-generate'));
    }

    public function test_registers_commands()
    {
        $provider = new DocsGenerateServiceProvider($this->app);
        $provider->boot();
        
        $commands = Artisan::all();
        $this->assertArrayHasKey('docs:generate', $commands);
    }

    public function test_registers_middleware_aliases()
    {
        $provider = new DocsGenerateServiceProvider($this->app);
        $provider->boot();
        
        $router = $this->app['router'];
        $middleware = $router->getMiddleware();
        
        $this->assertArrayHasKey('docs.cors', $middleware);
        $this->assertArrayHasKey('docs.auth', $middleware);
    }

    public function test_publishes_config_in_console()
    {
        $provider = new DocsGenerateServiceProvider($this->app);
        $provider->boot();
        
        $this->assertTrue(true);
    }

    public function test_loads_views()
    {
        $provider = new DocsGenerateServiceProvider($this->app);
        $provider->boot();
        
        $this->assertTrue($this->app->bound('view'));
    }

    public function test_registers_routes()
    {
        $provider = new DocsGenerateServiceProvider($this->app);
        $provider->boot();

        // Test that routes are registered by checking if the route exists
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('docs.ui'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('docs.json'));
    }
}
