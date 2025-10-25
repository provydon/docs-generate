<?php

namespace SwaggerAuto\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use SwaggerAuto\Providers\DocsGenerateServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            DocsGenerateServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('docs-generate', require __DIR__ . '/../src/config/docs-generate.php');
    }
}
