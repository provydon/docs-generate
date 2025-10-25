# Storage Configuration Examples

## Local Storage (Default)

```php
// config/docs-generate.php
'storage' => [
    'default' => 'local',
    'drivers' => [
        'local' => [
            'root' => storage_path('app'),
            'public_path' => 'storage',
            'base_url' => env('APP_URL'),
        ],
    ],
],
```

## AWS S3 Storage

```php
// config/docs-generate.php
'storage' => [
    'default' => 's3',
    'drivers' => [
        's3' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('DOCS_S3_BUCKET'),
            'prefix' => env('DOCS_S3_PREFIX', 'api-docs'),
            'acl' => env('DOCS_S3_ACL', 'public-read'),
            'base_url' => env('DOCS_S3_BASE_URL'),
        ],
    ],
],
```

### Environment Variables for S3

```env
DOCS_STORAGE_DRIVER=s3
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
DOCS_S3_BUCKET=your-bucket-name
DOCS_S3_PREFIX=api-docs
DOCS_S3_ACL=public-read
DOCS_S3_BASE_URL=https://your-bucket.s3.amazonaws.com
```

## FTP Storage

```php
// config/docs-generate.php
'storage' => [
    'default' => 'ftp',
    'drivers' => [
        'ftp' => [
            'host' => env('DOCS_FTP_HOST'),
            'port' => env('DOCS_FTP_PORT', 21),
            'username' => env('DOCS_FTP_USERNAME'),
            'password' => env('DOCS_FTP_PASSWORD'),
            'root' => env('DOCS_FTP_ROOT', '/'),
            'ssl' => env('DOCS_FTP_SSL', false),
            'passive' => env('DOCS_FTP_PASSIVE', true),
            'timeout' => env('DOCS_FTP_TIMEOUT', 90),
            'base_url' => env('DOCS_FTP_BASE_URL'),
        ],
    ],
],
```

### Environment Variables for FTP

```env
DOCS_STORAGE_DRIVER=ftp
DOCS_FTP_HOST=ftp.example.com
DOCS_FTP_PORT=21
DOCS_FTP_USERNAME=your-username
DOCS_FTP_PASSWORD=your-password
DOCS_FTP_ROOT=/public_html/api-docs
DOCS_FTP_SSL=false
DOCS_FTP_PASSIVE=true
DOCS_FTP_TIMEOUT=90
DOCS_FTP_BASE_URL=https://example.com/api-docs
```

## Usage

After configuring your storage driver, simply run:

```bash
php artisan docs:generate
```

The documentation will be saved to your configured storage and accessible via the `/docs` route.
