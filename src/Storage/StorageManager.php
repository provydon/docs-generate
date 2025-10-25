<?php

namespace SwaggerAuto\Storage;

use SwaggerAuto\Storage\LocalStorage;
use SwaggerAuto\Storage\S3Storage;
use SwaggerAuto\Storage\FtpStorage;

class StorageManager
{
    protected array $drivers = [];
    protected string $defaultDriver;
    
    public function __construct(string $defaultDriver = 'local')
    {
        $this->defaultDriver = $defaultDriver;
    }
    
    public function driver(string $name = null): StorageInterface
    {
        $name = $name ?: $this->defaultDriver;
        
        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }
        
        return $this->drivers[$name];
    }
    
    protected function createDriver(string $name): StorageInterface
    {
        $config = config("docs-generate.storage.drivers.{$name}", []);
        
        switch ($name) {
            case 'local':
                return new LocalStorage($config);
                
            case 's3':
                return new S3Storage($config);
                
            case 'ftp':
                return new FtpStorage($config);
                
            default:
                throw new \InvalidArgumentException("Storage driver [{$name}] not supported.");
        }
    }
    
    public function put(string $path, string $content, string $driver = null): bool
    {
        return $this->driver($driver)->put($path, $content);
    }
    
    public function get(string $path, string $driver = null): ?string
    {
        return $this->driver($driver)->get($path);
    }
    
    public function exists(string $path, string $driver = null): bool
    {
        return $this->driver($driver)->exists($path);
    }
    
    public function delete(string $path, string $driver = null): bool
    {
        return $this->driver($driver)->delete($path);
    }
    
    public function url(string $path, string $driver = null): string
    {
        return $this->driver($driver)->url($path);
    }
}
