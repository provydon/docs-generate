<?php

namespace SwaggerAuto\Storage;

abstract class AbstractStorage implements StorageInterface
{
    protected array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
    
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
    
    protected function ensureDirectory(string $path): bool
    {
        $directory = dirname($path);
        
        if (!is_dir($directory)) {
            return mkdir($directory, 0755, true);
        }
        
        return true;
    }
}
