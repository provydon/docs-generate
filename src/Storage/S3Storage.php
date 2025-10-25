<?php

namespace SwaggerAuto\Storage;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class S3Storage extends AbstractStorage
{
    protected S3Client $s3Client;
    
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        
        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => $this->getConfig('region', 'us-east-1'),
            'credentials' => [
                'key' => $this->getConfig('key'),
                'secret' => $this->getConfig('secret'),
            ],
        ]);
    }
    
    public function put(string $path, string $content): bool
    {
        try {
            $result = $this->s3Client->putObject([
                'Bucket' => $this->getConfig('bucket'),
                'Key' => $this->getFullPath($path),
                'Body' => $content,
                'ContentType' => 'application/json',
                'ACL' => $this->getConfig('acl', 'public-read'),
            ]);
            
            return $result['@metadata']['statusCode'] === 200;
        } catch (AwsException $e) {
            return false;
        }
    }
    
    public function get(string $path): ?string
    {
        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->getConfig('bucket'),
                'Key' => $this->getFullPath($path),
            ]);
            
            return $result['Body']->getContents();
        } catch (AwsException $e) {
            return null;
        }
    }
    
    public function exists(string $path): bool
    {
        try {
            $this->s3Client->headObject([
                'Bucket' => $this->getConfig('bucket'),
                'Key' => $this->getFullPath($path),
            ]);
            
            return true;
        } catch (AwsException $e) {
            return false;
        }
    }
    
    public function delete(string $path): bool
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->getConfig('bucket'),
                'Key' => $this->getFullPath($path),
            ]);
            
            return true;
        } catch (AwsException $e) {
            return false;
        }
    }
    
    public function url(string $path): string
    {
        $baseUrl = $this->getConfig('base_url');
        
        if ($baseUrl) {
            return rtrim($baseUrl, '/') . '/' . ltrim($this->getFullPath($path), '/');
        }
        
        return $this->s3Client->getObjectUrl(
            $this->getConfig('bucket'),
            $this->getFullPath($path)
        );
    }
    
    protected function getFullPath(string $path): string
    {
        $prefix = $this->getConfig('prefix', '');
        
        return ltrim($prefix . '/' . ltrim($path, '/'), '/');
    }
}
