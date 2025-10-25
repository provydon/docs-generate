<?php

namespace SwaggerAuto\Storage;

class FtpStorage extends AbstractStorage
{
    protected $connection;
    
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        
        $this->connect();
    }
    
    protected function connect(): bool
    {
        $host = $this->getConfig('host');
        $port = $this->getConfig('port', 21);
        $timeout = $this->getConfig('timeout', 90);
        
        $this->connection = ftp_connect($host, $port, $timeout);
        
        if (!$this->connection) {
            return false;
        }
        
        $username = $this->getConfig('username');
        $password = $this->getConfig('password');
        
        if (!ftp_login($this->connection, $username, $password)) {
            ftp_close($this->connection);
            return false;
        }
        
        if ($this->getConfig('passive', true)) {
            ftp_pasv($this->connection, true);
        }
        
        return true;
    }
    
    public function put(string $path, string $content): bool
    {
        if (!$this->connection) {
            return false;
        }
        
        $fullPath = $this->getFullPath($path);
        $directory = dirname($fullPath);
        
        $this->ensureRemoteDirectory($directory);
        
        $tempFile = tmpfile();
        fwrite($tempFile, $content);
        rewind($tempFile);
        
        $result = ftp_fput($this->connection, $fullPath, $tempFile, FTP_BINARY);
        
        fclose($tempFile);
        
        return $result;
    }
    
    public function get(string $path): ?string
    {
        if (!$this->connection) {
            return null;
        }
        
        $fullPath = $this->getFullPath($path);
        
        if (!$this->exists($path)) {
            return null;
        }
        
        $tempFile = tmpfile();
        
        if (!ftp_fget($this->connection, $tempFile, $fullPath, FTP_BINARY)) {
            fclose($tempFile);
            return null;
        }
        
        rewind($tempFile);
        $content = stream_get_contents($tempFile);
        fclose($tempFile);
        
        return $content;
    }
    
    public function exists(string $path): bool
    {
        if (!$this->connection) {
            return false;
        }
        
        $fullPath = $this->getFullPath($path);
        $directory = dirname($fullPath);
        $filename = basename($fullPath);
        
        $files = ftp_nlist($this->connection, $directory);
        
        if (!$files) {
            return false;
        }
        
        foreach ($files as $file) {
            if (basename($file) === $filename) {
                return true;
            }
        }
        
        return false;
    }
    
    public function delete(string $path): bool
    {
        if (!$this->connection) {
            return false;
        }
        
        $fullPath = $this->getFullPath($path);
        
        return ftp_delete($this->connection, $fullPath);
    }
    
    public function url(string $path): string
    {
        $baseUrl = $this->getConfig('base_url');
        
        if (!$baseUrl) {
            $protocol = $this->getConfig('ssl', false) ? 'https' : 'http';
            $host = $this->getConfig('host');
            $baseUrl = $protocol . '://' . $host;
        }
        
        return rtrim($baseUrl, '/') . '/' . ltrim($this->getFullPath($path), '/');
    }
    
    protected function getFullPath(string $path): string
    {
        $root = $this->getConfig('root', '/');
        
        return rtrim($root, '/') . '/' . ltrim($path, '/');
    }
    
    protected function ensureRemoteDirectory(string $directory): bool
    {
        if (!$this->connection) {
            return false;
        }
        
        $parts = explode('/', trim($directory, '/'));
        $currentPath = '';
        
        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }
            
            $currentPath .= '/' . $part;
            
            if (!@ftp_chdir($this->connection, $currentPath)) {
                if (!ftp_mkdir($this->connection, $currentPath)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    public function __destruct()
    {
        if ($this->connection) {
            ftp_close($this->connection);
        }
    }
}
