<div align="center">

# 🚀 Docs Generate

**Automatic API documentation for your Laravel app — zero annotations, OpenAPI 3.0 JSON & Swagger UI.**

[![MIT License](https://img.shields.io/badge/License-MIT-green.svg)](https://choosealicense.com/licenses/mit/)
[![Laravel](https://img.shields.io/badge/Laravel-10%2B-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)

*Generate beautiful API documentation automatically from your existing Laravel code*

 [☕ Buy me a coffee](https://buymeacoffee.com/provydon)
</div>

---

## ✨ Why Docs Generate?

**Stop writing documentation manually!** This package automatically generates beautiful Swagger/OpenAPI documentation from your existing Laravel code—no annotations, no code pollution, no maintenance headaches.

### 🎯 **Perfect For:**
- **Laravel APIs** that need professional documentation
- **Teams** who want docs that stay in sync with code
- **Developers** who hate writing documentation
- **Projects** that need client-ready API docs

---

## 🚀 Quick Start

**Get documented instantly:**

```bash
# 1. Install
composer require provydon/docs-generate

# 2. Publish config
php artisan vendor:publish --tag=docs-generate

# 3. Generate docs (instant!)
php artisan docs:generate

# 4. View your beautiful docs
# Visit: http://your-app-url/docs
```

**That's it!** 🎉 Your API is now fully documented.

### 🔗 **Use with Any API Client**

The generated JSON is compatible with all major API clients:

```bash
# Get the raw OpenAPI JSON
curl http://your-app-url/docs.json
```

**Import into your favorite tools:**
- 📮 **Postman** - Import OpenAPI 3.0 JSON
- 🌙 **Insomnia** - Import OpenAPI spec
- 🚀 **Thunder Client** - VS Code extension
- 🔥 **Bruno** - Open-source API client
- 📱 **Any OpenAPI-compatible tool**

---

## ✨ What You Get

<div align="center">

| Before | After |
|--------|-------|
| ❌ No documentation<br>❌ Manual maintenance<br>❌ Outdated docs | ✅ Beautiful Swagger UI<br>✅ Auto-generated from code<br>✅ Always up-to-date |

</div>

### 🎨 **Beautiful Swagger UI**
- Modern, responsive design
- Interactive "Try it out" functionality
- Professional API explorer
- Mobile-friendly interface

### 🔄 **Zero Maintenance**
- **Auto-detects** your routes, validation, and auth
- **Stays in sync** with your code changes
- **No annotations** to maintain
- **Regenerate** with one command

### 🛡️ **Production Ready**
- Configurable authentication
- CORS handling included
- Error handling built-in
- Laravel-native integration

---

## 🎯 Key Features

<div align="center">

| Feature | Description |
|---------|-------------|
| 🚫 **Zero Annotations** | No code pollution—works with existing code |
| 🔍 **Smart Detection** | Auto-detects routes, validation, and auth |
| 📝 **Inline Validation** | Supports `$request->validate()` calls |
| 🎨 **Beautiful UI** | Modern Swagger UI with "Try it out" |
| 🔒 **Auth Ready** | Built-in authentication support |
| ⚙️ **Fully Configurable** | Customize everything via config |
| 🚀 **Laravel Native** | Uses Laravel conventions and patterns |

</div>

---

## 📋 Requirements

- **PHP** 8.1+
- **Laravel** 10.0+

---

## 🛠️ Installation

### Step 1: Install via Composer

```bash
composer require provydon/docs-generate
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=docs-generate
```

This creates:
- `config/docs-generate.php` - Configuration file
- `resources/views/vendor/docs-generate/` - Swagger UI view

### Step 3: Generate Documentation

```bash
php artisan docs:generate
```

### Step 4: View Your Documentation

Navigate to: `http://your-app-url/docs`

---

## 🎨 Authentication

### Documentation Access Control

Control who can access your API documentation:

```env
# No authentication (default)
DOCS_AUTH_ENABLED=false

# Authenticated users only
DOCS_AUTH_ENABLED=true
DOCS_AUTH_TYPE=authenticated

# Specific emails only
DOCS_AUTH_ENABLED=true
DOCS_AUTH_TYPE=specific_emails
DOCS_AUTH_ALLOWED_EMAILS=admin@example.com,developer@example.com
```

### API Authentication Detection

The package automatically detects authentication requirements by checking for these middleware:
- `auth`
- `auth:sanctum`
- `auth:api`
- `sanctum`
- Any middleware containing `Authenticate`

---

## ⚙️ Configuration

### Route Configuration

Customize your documentation routes:

```env
# Enable/disable route registration (default: true)
DOCS_ROUTES_ENABLED=true

# Customize documentation path (default: /docs)
DOCS_PATH=/api-docs

# Customize JSON path (default: /docs.json)
DOCS_JSON_PATH=/api-docs.json
```

### API Information

Edit `config/docs-generate.php`:

```php
'info' => [
    'title' => env('APP_NAME', 'Laravel API'),
    'description' => 'API Documentation automatically generated',
    'version' => '1.0.0',
    'contact' => [
        'name' => 'API Support',
        'email' => 'support@example.com',
    ],
],
```

---

## 📁 Output File

The generated API documentation is saved as `public/docs.json` in your Laravel project. This approach offers several benefits:

- **Always Accessible**: File is in the public folder, accessible via web server
- **Part of Project**: Included in version control and deployments
- **CDN Ready**: Can be served via CDN for better performance
- **Simple**: No complex storage configuration needed

**Direct Access:**
```
https://your-app.com/docs.json
```

**Via Route (with error handling):**
```
https://your-app.com/docs.json
```

---

## 🔧 How It Works

### 1. **Route Analysis**
Scans all Laravel routes with the `api/` prefix and extracts:
- HTTP methods (GET, POST, PUT, DELETE, etc.)
- URI patterns and parameters
- Controller and method names
- Applied middleware (for auth detection)

### 2. **Validation Extraction**
For each route, it inspects the controller method:
- Identifies FormRequest classes in method parameters
- Detects inline `$request->validate()` calls in method body
- Extracts validation rules from both sources
- Converts rules to OpenAPI schema definitions

### 3. **Smart Type Detection**
Based on field names and validation rules, it automatically detects:
- `email` fields → format: email
- `password` fields → format: password, minLength: 8
- `phone` fields → format: phone
- `url` fields → format: uri
- `date` fields → format: date
- `uuid` fields → format: uuid
- `image`/`file` fields → format: binary

### 4. **Authentication Detection**
Automatically adds security requirements if route has:
- `auth` middleware
- `auth:sanctum` middleware
- `auth:api` middleware
- `sanctum` middleware
- Any middleware containing `Authenticate`

---

## 📚 Examples

### Example 1: FormRequest Validation

**Laravel Code:**
```php
Route::post('/api/users', [UserController::class, 'store'])
    ->middleware('auth:sanctum');

class StoreUserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string',
        ];
    }
}
```

**Generated Swagger:**
```json
{
  "paths": {
    "/api/users": {
      "post": {
        "tags": ["Users"],
        "summary": "Create a new User",
        "security": [{"sanctum": []}],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": ["name", "email", "password"],
                "properties": {
                  "name": {"type": "string", "maxLength": 255},
                  "email": {"type": "string", "format": "email"},
                  "password": {"type": "string", "format": "password", "minLength": 8},
                  "phone": {"type": "string", "format": "phone"}
                }
              }
            }
          }
        }
      }
    }
  }
}
```

### Example 2: Inline Validation

**Laravel Code:**
```php
Route::post('/api/otp/verify', [OtpController::class, 'verify']);

class OtpController extends Controller
{
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'otp' => 'required|string|digits:6',
            'verified' => 'nullable|boolean',
        ]);
    }
}
```

**Generated Swagger:**
```json
{
                "properties": {
                  "user_id": {"type": "integer", "description": "Must exist in users"},
                  "otp": {"type": "string", "pattern": "^[0-9]{6}$", "minLength": 6, "maxLength": 6},
                  "verified": {"type": "boolean"}
  }
}
```

---

## 🚀 Commands

### Generate Documentation

```bash
php artisan docs:generate
```

Generates fresh API documentation from your current routes and controllers.

---

## 🛠️ Troubleshooting

### Documentation not showing?

Make sure you've run:
```bash
php artisan docs:generate
```

### Routes not appearing?

Check that routes have the `api/` prefix:
```php
'route_filters' => [
    'prefix' => 'api/',
],
```

### 404 on /docs?

Clear route cache:
```bash
php artisan route:clear
php artisan route:cache
```

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## ☕ Support

If this package helped you, consider [buying me a coffee](https://buymeacoffee.com/provydon) to support development!

---

<div align="center">

**Made with ❤️ by [Provydon](https://github.com/provydon)**

[⭐ Star on GitHub](https://github.com/provydon/docs-generate) • [🐛 Report Bug](https://github.com/provydon/docs-generate/issues) • [💡 Request Feature](https://github.com/provydon/docs-generate/issues)

</div>