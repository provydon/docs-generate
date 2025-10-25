<?php

namespace SwaggerAuto\Tests\Unit\Storage;

use SwaggerAuto\Storage\StorageManager;
use SwaggerAuto\Tests\TestCase;

class StorageManagerTest extends TestCase
{
    public function test_creates_local_driver_by_default()
    {
        $manager = new StorageManager();
        $driver = $manager->driver();

        $this->assertInstanceOf(\SwaggerAuto\Storage\LocalStorage::class, $driver);
    }

    public function test_creates_local_driver_explicitly()
    {
        $manager = new StorageManager();
        $driver = $manager->driver('local');

        $this->assertInstanceOf(\SwaggerAuto\Storage\LocalStorage::class, $driver);
    }

    public function test_throws_exception_for_unsupported_driver()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Storage driver [unsupported] not supported.');

        $manager = new StorageManager();
        $manager->driver('unsupported');
    }

    public function test_delegates_methods_to_driver()
    {
        $manager = new StorageManager();
        
        $this->assertTrue($manager->put('test.json', '{"test": "data"}'));
        $this->assertTrue($manager->exists('test.json'));
        $this->assertEquals('{"test": "data"}', $manager->get('test.json'));
        $this->assertTrue($manager->delete('test.json'));
    }
}
