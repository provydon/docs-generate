<?php

namespace SwaggerAuto\Tests\Feature;

use SwaggerAuto\Tests\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Mockery;

class DocumentationAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up encryption key for testing
        $this->app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        
        Config::set('docs-generate.routes', [
            'enabled' => true,
            'documentation_path' => '/documentation',
            'json_path' => '/documentation.json',
            'middleware' => ['web', 'docs.cors', 'docs.auth']
        ]);
        Config::set('docs-generate.documentation_auth', [
            'enabled' => false,
            'type' => 'none',
            'allowed_emails' => '',
        ]);
        
        // Ensure the service provider is booted to register routes
        $provider = new \SwaggerAuto\Providers\DocsGenerateServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }

    public function test_documentation_page_accessible_when_auth_disabled()
    {
        $response = $this->get('/documentation');
        
        if ($response->getStatusCode() !== 200) {
            $this->fail('Expected 200, got ' . $response->getStatusCode() . '. Response: ' . $response->getContent());
        }
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('swagger', $response->getContent());
    }

    public function test_documentation_page_redirects_to_login_when_auth_required_but_not_logged_in()
    {
        Config::set('docs-generate.documentation_auth', [
            'enabled' => true,
            'type' => 'authenticated',
            'allowed_emails' => '',
        ]);
        
        Auth::shouldReceive('check')->andReturn(false);
        
        $response = $this->get('/documentation');
        
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('login', $response->getTargetUrl());
    }

    public function test_documentation_page_accessible_when_authenticated()
    {
        Config::set('docs-generate.documentation_auth', [
            'enabled' => true,
            'type' => 'authenticated',
            'allowed_emails' => '',
        ]);
        
        Auth::shouldReceive('check')->andReturn(true);
        
        $response = $this->get('/documentation');
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_documentation_page_accessible_when_user_email_allowed()
    {
        Config::set('docs-generate.documentation_auth', [
            'enabled' => true,
            'type' => 'specific_emails',
            'allowed_emails' => 'admin@example.com,user@example.com',
        ]);
        
        $user = Mockery::mock();
        $user->email = 'admin@example.com';
        
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        
        $response = $this->get('/documentation');
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_documentation_page_denied_when_user_email_not_allowed()
    {
        Config::set('docs-generate.documentation_auth', [
            'enabled' => true,
            'type' => 'specific_emails',
            'allowed_emails' => 'admin@example.com,user@example.com',
        ]);
        
        $user = Mockery::mock();
        $user->email = 'other@example.com';
        
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        
        $response = $this->get('/documentation');
        
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_json_endpoint_accessible_when_auth_disabled()
    {
        $response = $this->get('/documentation.json');
        
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    public function test_json_endpoint_redirects_when_auth_required_but_not_logged_in()
    {
        Config::set('docs-generate.documentation_auth', [
            'enabled' => true,
            'type' => 'authenticated',
            'allowed_emails' => '',
        ]);
        
        Auth::shouldReceive('check')->andReturn(false);
        
        $response = $this->get('/documentation.json');
        
        $this->assertEquals(302, $response->getStatusCode());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
