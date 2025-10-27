# Release Notes

## [v1.1.1] - 2025-01-27

### 🐛 Bug Fixes

**Fixed FormRequest instantiation with custom constructors**

The documentation generator would crash with an `ArgumentCountError` when encountering FormRequest classes that have custom constructors requiring a `Request` parameter.

**Before:**
```php
// This would cause a crash
class CompanySignupRequest extends FormRequest
{
    public function __construct(Request $request)
    {
        parent::__construct();
        $this->input = $request->all();
        // ... custom logic
    }
}
```

**Error:**
```
ArgumentCountError: Too few arguments to function 
App\Http\Requests\CompanySignupRequest::__construct(), 
0 passed and exactly 1 expected
```

**Solution:**
- Enhanced `extractSchemaFromFormRequest()` method to detect constructor dependencies using reflection
- Added intelligent fallback mechanisms:
  1. Attempt Laravel container resolution for FormRequest instances
  2. Create a mock `Request` instance and pass it to constructors that require it
  3. Parse validation rules from source code as a last resort fallback
- Improved error handling to gracefully handle edge cases

**Impact:**
- ✅ Documentation generation no longer crashes on FormRequest classes with custom constructors
- ✅ Better error handling and fallback mechanisms
- ✅ More robust handling of edge cases in Laravel request validation classes

**Migration:**
No changes required. Existing users can update to this version and documentation generation will handle custom FormRequest constructors automatically.

**Reported by:** Raymond Ativie

---

## [v1.1.0] - Previous Release

### ✨ Features

- Added closure handling for validation rules
- Improved validation rule detection and parsing

---

## [v1.0.0] - Initial Release

### 🎉 Initial Features

- Automatic API documentation generation
- Zero annotations required
- OpenAPI 3.0 JSON output
- Swagger UI integration
- Laravel FormRequest validation rule extraction
- Inline validation rule support
- Automatic route detection and filtering
- Customizable documentation configuration

---

## Installation & Update

```bash
# Install
composer require provydon/docs-generate

# Update to latest version
composer update provydon/docs-generate
```

## Support

For issues and feature requests, please visit: https://github.com/provydon/docs-generate

