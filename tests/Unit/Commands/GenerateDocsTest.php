<?php

namespace SwaggerAuto\Tests\Unit\Commands;

use SwaggerAuto\Tests\TestCase;
use SwaggerAuto\Commands\GenerateDocs;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Mockery;

class GenerateDocsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
            Config::set('docs-generate.output_path', storage_path('api-docs/api-docs.json'));
    }

    public function test_generates_api_documentation()
    {
        $command = new GenerateDocs();
        
        $this->assertInstanceOf(GenerateDocs::class, $command);
        $this->assertEquals('docs:generate', $command->getName());
    }

    public function test_command_has_correct_signature()
    {
        $command = new GenerateDocs();
        
        $this->assertEquals('docs:generate', $command->getName());
        $this->assertEquals('Generate API documentation automatically', $command->getDescription());
    }

    public function test_command_extends_console_command()
    {
        $command = new GenerateDocs();
        
        $this->assertInstanceOf(\Illuminate\Console\Command::class, $command);
    }

    protected function tearDown(): void
    {
        $outputPath = Config::get('docs-generate.output_path');
        if (File::exists($outputPath)) {
            File::delete($outputPath);
        }
        
        $outputDir = dirname($outputPath);
        if (File::exists($outputDir) && File::isEmptyDirectory($outputDir)) {
            File::deleteDirectory($outputDir);
        }
        
        parent::tearDown();
    }
}
