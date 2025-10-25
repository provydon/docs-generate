<?php

namespace SwaggerAuto\Storage;

interface StorageInterface
{
    public function put(string $path, string $content): bool;
    
    public function get(string $path): ?string;
    
    public function exists(string $path): bool;
    
    public function delete(string $path): bool;
    
    public function url(string $path): string;
}
