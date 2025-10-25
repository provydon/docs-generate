<?php

namespace SwaggerAuto\Storage;

class LocalStorage extends AbstractStorage
{
    public function put(string $path, string $content): bool
    {
        $fullPath = $this->getFullPath($path);
        
        if (!$this->ensureDirectory($fullPath)) {
            return false;
        }
        
        return file_put_contents($fullPath, $content) !== false;
    }
    
    public function get(string $path): ?string
    {
        $fullPath = $this->getFullPath($path);
        
        if (!file_exists($fullPath)) {
            return null;
        }
        
        $content = file_get_contents($fullPath);
        
        return $content !== false ? $content : null;
    }
    
    public function exists(string $path): bool
    {
        return file_exists($this->getFullPath($path));
    }
    
    public function delete(string $path): bool
    {
        $fullPath = $this->getFullPath($path);
        
        if (!file_exists($fullPath)) {
            return true;
        }
        
        return unlink($fullPath);
    }
    
    public function url(string $path): string
    {
        $baseUrl = $this->getConfig('base_url', url('/'));
        $publicPath = $this->getConfig('public_path', 'storage');
        
        return rtrim($baseUrl, '/') . '/' . ltrim($publicPath, '/') . '/' . ltrim($path, '/');
    }
    
    protected function getFullPath(string $path): string
    {
        $root = $this->getConfig('root', storage_path('app'));
        
        return rtrim($root, '/') . '/' . ltrim($path, '/');
    }
}
