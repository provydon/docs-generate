<?php

namespace SwaggerAuto\Tests\Unit\Storage;

use SwaggerAuto\Storage\LocalStorage;
use SwaggerAuto\Tests\TestCase;

class LocalStorageTest extends TestCase
{
    protected LocalStorage $storage;
    protected string $testPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testPath = sys_get_temp_dir() . '/docs-generate-test';
        $this->storage = new LocalStorage([
            'root' => $this->testPath,
            'base_url' => 'http://localhost',
            'public_path' => 'storage',
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testPath)) {
            $this->rrmdir($this->testPath);
        }
        
        parent::tearDown();
    }

    public function test_put_and_get_content()
    {
        $path = 'test/api-docs.json';
        $content = '{"test": "data"}';

        $this->assertTrue($this->storage->put($path, $content));
        $this->assertTrue($this->storage->exists($path));
        $this->assertEquals($content, $this->storage->get($path));
    }

    public function test_put_creates_directory()
    {
        $path = 'nested/deep/api-docs.json';
        $content = '{"test": "data"}';

        $this->assertTrue($this->storage->put($path, $content));
        $this->assertTrue($this->storage->exists($path));
    }

    public function test_get_returns_null_for_nonexistent_file()
    {
        $this->assertNull($this->storage->get('nonexistent.json'));
    }

    public function test_exists_returns_false_for_nonexistent_file()
    {
        $this->assertFalse($this->storage->exists('nonexistent.json'));
    }

    public function test_delete_removes_file()
    {
        $path = 'test/api-docs.json';
        $content = '{"test": "data"}';

        $this->storage->put($path, $content);
        $this->assertTrue($this->storage->exists($path));

        $this->assertTrue($this->storage->delete($path));
        $this->assertFalse($this->storage->exists($path));
    }

    public function test_url_generation()
    {
        $path = 'test/api-docs.json';
        $expectedUrl = 'http://localhost/storage/test/api-docs.json';

        $this->assertEquals($expectedUrl, $this->storage->url($path));
    }

    private function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->rrmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
