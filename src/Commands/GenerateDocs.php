<?php

namespace SwaggerAuto\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Foundation\Http\FormRequest;

class GenerateDocs extends Command
{
    protected $signature = 'docs:generate';
    protected $description = 'Generate API documentation automatically';

    protected $paths = [];
    protected $schemas = [];

    public function handle()
    {
        $this->info('🚀 Generating API documentation...');

        $routes = $this->getApiRoutes();

        if (empty($routes)) {
            $this->warn('No API routes found with prefix "api/"');
            return 1;
        }

        $this->info("Found " . count($routes) . " API routes");

        foreach ($routes as $route) {
            $this->processRoute($route);
        }

        $docs = $this->buildApiDocument();

        $this->saveApiDocument($docs);

        $this->info('✅ API documentation generated successfully!');
        $this->info('📄 Output: ' . config('docs-generate.output_path'));
        $this->info('🌐 View at: ' . url(config('docs-generate.routes.documentation_path')));

        return 0;
    }

    protected function getApiRoutes()
    {
        $routes = Route::getRoutes();
        $apiRoutes = [];

        foreach ($routes as $route) {
            $uri = $route->uri();
            $prefix = config('docs-generate.route_filters.prefix', 'api/');

            if (!Str::startsWith($uri, $prefix)) {
                continue;
            }

            $excludePatterns = config('docs-generate.route_filters.exclude_patterns', []);
            $shouldExclude = false;

            foreach ($excludePatterns as $pattern) {
                if (Str::is($pattern, $uri)) {
                    $shouldExclude = true;
                    break;
                }
            }

            if ($shouldExclude) {
                continue;
            }

            $methods = $route->methods();
            $includedMethods = config('docs-generate.http_methods.include', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
            $excludedMethods = config('docs-generate.http_methods.exclude', ['HEAD', 'OPTIONS']);

            $validMethods = array_diff(
                array_intersect($methods, $includedMethods),
                $excludedMethods
            );

            if (!empty($validMethods)) {
                $apiRoutes[] = $route;
            }
        }

        return $apiRoutes;
    }

    protected function processRoute($route)
    {
        $uri = '/' . $route->uri();
        $methods = $route->methods();
        $action = $route->getAction();

        $controller = $action['controller'] ?? null;

        if (!$controller || !Str::contains($controller, '@')) {
            return;
        }

        [$controllerClass, $method] = Str::parseCallback($controller);

        if (!class_exists($controllerClass)) {
            return;
        }

        $tag = $this->getTagFromController($controllerClass);
        $resource = $this->getResourceFromUri($uri);

        foreach ($methods as $httpMethod) {
            $httpMethodLower = strtolower($httpMethod);

            if (!in_array(strtoupper($httpMethod), config('docs-generate.http_methods.include', []))) {
                continue;
            }

            $operation = $this->buildOperation($route, $controllerClass, $method, $tag, $resource, $httpMethodLower);

            if (!isset($this->paths[$uri])) {
                $this->paths[$uri] = [];
            }

            $this->paths[$uri][$httpMethodLower] = $operation;
        }
    }

    protected function buildOperation($route, $controllerClass, $method, $tag, $resource, $httpMethod)
    {
        $operation = [
            'tags' => [$tag],
            'summary' => $this->getSummary($method, $resource),
            'description' => $this->getMethodDescription($method, $resource),
            'operationId' => $controllerClass . '@' . $method,
        ];

        if ($this->requiresAuth($route)) {
            $operation['security'] = config('docs-generate.default_security', []);
        }

        $parameters = $this->extractParameters($route);
        if (!empty($parameters)) {
            $operation['parameters'] = $parameters;
        }

        $requestBody = $this->extractRequestBody($controllerClass, $method, $httpMethod);
        if (!empty($requestBody)) {
            $operation['requestBody'] = $requestBody;
        }

        $this->applyEndpointOverrides($operation, $method);

        $operation['responses'] = $this->generateResponses($method);

        return $operation;
    }

    protected function getTagFromController($controllerClass)
    {
        $className = class_basename($controllerClass);
        $mappings = config('docs-generate.tag_mappings', []);

        if (isset($mappings[$className])) {
            return $mappings[$className];
        }

        return Str::replace('Controller', '', $className);
    }

    protected function getResourceFromUri($uri)
    {
        $parts = explode('/', trim($uri, '/'));
        $resources = array_filter($parts, fn($part) => !Str::startsWith($part, '{'));

        if (empty($resources)) {
            return 'Resource';
        }

        $resource = end($resources);
        return Str::singular(Str::title(str_replace('-', ' ', $resource)));
    }

    protected function getSummary($method, $resource)
    {
        $summaries = config('docs-generate.method_summaries', []);

        if (isset($summaries[$method])) {
            return str_replace(':resource', $resource, $summaries[$method]);
        }

        return ucfirst($method) . ' ' . $resource;
    }

    protected function getMethodDescription($method, $resource)
    {
        $descriptions = config('docs-generate.method_descriptions', []);

        if (isset($descriptions[$method])) {
            return str_replace(':resource', $resource, $descriptions[$method]);
        }

        return '';
    }

    protected function requiresAuth($route)
    {
        $middleware = $route->middleware();

        foreach ($middleware as $m) {
            if (is_string($m) && (
                $m === 'auth' ||
                Str::startsWith($m, 'auth:') ||
                $m === 'sanctum' ||
                Str::contains($m, 'Authenticate')
            )) {
                return true;
            }
        }

        $action = $route->getAction();
        if (isset($action['middleware'])) {
            $actionMiddleware = is_array($action['middleware']) ? $action['middleware'] : [$action['middleware']];
            
            foreach ($actionMiddleware as $m) {
                if (is_string($m) && (
                    $m === 'auth' ||
                    Str::startsWith($m, 'auth:') ||
                    $m === 'sanctum'
                )) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function extractParameters($route)
    {
        $parameters = [];
        $uri = $route->uri();

        preg_match_all('/\{([^}]+)\}/', $uri, $matches);

        foreach ($matches[1] as $param) {
            $isOptional = Str::endsWith($param, '?');
            $paramName = rtrim($param, '?');

            $parameters[] = [
                'name' => $paramName,
                'in' => 'path',
                'required' => !$isOptional,
                'schema' => [
                    'type' => $this->guessParameterType($paramName),
                ],
                'description' => 'The ' . $paramName . ' identifier',
            ];
        }

        return $parameters;
    }

    protected function guessParameterType($paramName)
    {
        if (in_array($paramName, ['id', 'user_id', 'post_id', 'category_id'])) {
            return 'integer';
        }

        if (Str::contains($paramName, 'uuid')) {
            return 'string';
        }

        return 'string';
    }

    protected function extractRequestBody($controllerClass, $method, $httpMethod)
    {
        try {
            $reflectionMethod = new ReflectionMethod($controllerClass, $method);
            $parameters = $reflectionMethod->getParameters();

            foreach ($parameters as $param) {
                $type = $param->getType();

                if (!$type || $type->isBuiltin()) {
                    continue;
                }

                $className = $type->getName();

                if (!class_exists($className)) {
                    continue;
                }

                $reflection = new ReflectionClass($className);

                if ($reflection->isSubclassOf(FormRequest::class)) {
                    $schema = $this->extractSchemaFromFormRequest($className);

                    if (!empty($schema)) {
                        return $this->buildRequestBody($schema, $httpMethod);
                    }
                }
            }

            $inlineValidation = $this->extractInlineValidation($controllerClass, $method);
            if (!empty($inlineValidation)) {
                return $this->buildRequestBody($inlineValidation, $httpMethod);
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    protected function extractInlineValidation($controllerClass, $method)
    {
        try {
            $reflectionMethod = new ReflectionMethod($controllerClass, $method);
            $fileName = $reflectionMethod->getFileName();
            $startLine = $reflectionMethod->getStartLine();
            $endLine = $reflectionMethod->getEndLine();

            if (!$fileName || !file_exists($fileName)) {
                return null;
            }

            $fileLines = file($fileName);
            $methodCode = implode('', array_slice($fileLines, $startLine - 1, $endLine - $startLine + 1));

            if (preg_match('/\$request->validate\(\s*\[(.*?)\]\s*\)/s', $methodCode, $matches)) {
                $validationRules = $this->parseValidationArray($matches[1]);
                return $this->convertValidationToSchema($validationRules);
            }

            if (preg_match('/\$request->validate\(\s*\[\s*(.*?)\s*\]\s*\)/s', $methodCode, $matches)) {
                $validationRules = $this->parseValidationArray($matches[1]);
                return $this->convertValidationToSchema($validationRules);
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    protected function parseValidationArray($arrayContent)
    {
        $rules = [];
        $lines = explode("\n", $arrayContent);

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/[\'"]([^\'"]+)[\'"]\s*=>\s*[\'"](.*?)[\'"]/', $line, $matches)) {
                $field = $matches[1];
                $rule = $matches[2];
                $rules[$field] = $rule;
            } elseif (preg_match('/[\'"]([^\'"]+)[\'"]\s*=>\s*\[(.*?)\]/', $line, $matches)) {
                $field = $matches[1];
                $ruleArray = $matches[2];
                $ruleItems = array_map('trim', explode(',', $ruleArray));
                $ruleItems = array_map(fn($r) => trim($r, '\'"'), $ruleItems);
                $rules[$field] = implode('|', $ruleItems);
            }
        }

        return $rules;
    }

    protected function convertValidationToSchema($validationRules)
    {
        if (empty($validationRules)) {
            return null;
        }

        $properties = [];
        $required = [];

        foreach ($validationRules as $field => $rule) {
            $ruleArray = is_array($rule) ? $rule : explode('|', $rule);
            $fieldSchema = $this->convertRulesToSchema($field, $ruleArray);

            if ($fieldSchema) {
                $properties[$field] = $fieldSchema;

                if ($this->isRequired($ruleArray)) {
                    $required[] = $field;
                }
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    protected function buildRequestBody($schema, $httpMethod)
    {
        $httpMethod = strtoupper($httpMethod);

        if (in_array($httpMethod, ['POST', 'PUT', 'PATCH'])) {
            return [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => $schema,
                    ],
                ],
            ];
        }

        return null;
    }

    protected function extractSchemaFromFormRequest($className)
    {
        try {
            $reflection = new ReflectionClass($className);
            
            // Check if constructor requires parameters
            $constructor = $reflection->getConstructor();
            $hasRequiredParams = false;
            
            if ($constructor) {
                $params = $constructor->getParameters();
                foreach ($params as $param) {
                    if (!$param->isOptional()) {
                        $hasRequiredParams = true;
                        break;
                    }
                }
            }
            
            $rules = [];
            
            // Try to instantiate normally if no required params
            if (!$hasRequiredParams) {
                try {
                    $request = new $className();
                    $rules = method_exists($request, 'rules') ? $request->rules() : [];
                } catch (\Exception $e) {
                    // Fall back to reflection method
                }
            }
            
            // If instantiation failed or has required params, use reflection to read rules method
            if (empty($rules) && $reflection->hasMethod('rules')) {
                $rulesMethod = $reflection->getMethod('rules');
                
                // Check if rules() method has dependencies
                $methodParams = $rulesMethod->getParameters();
                $requiresDeps = false;
                foreach ($methodParams as $param) {
                    if (!$param->isOptional()) {
                        $requiresDeps = true;
                        break;
                    }
                }
                
                // If rules() doesn't require dependencies, try to call it directly
                if (!$requiresDeps) {
                    try {
                        // Try to resolve through Laravel container if available
                        if (app()->bound($className)) {
                            $request = app($className);
                            $rules = $request->rules();
                        } else {
                            // Try to create a mock request and instantiate
                            $mockRequest = \Illuminate\Http\Request::create('/', 'GET');
                            
                            // Check if constructor accepts Request instance
                            if ($constructor && $constructor->getNumberOfParameters() > 0) {
                                $firstParam = $constructor->getParameters()[0];
                                $paramType = $firstParam->getType();
                                
                                if ($paramType && !$paramType->isBuiltin()) {
                                    $typeName = $paramType->getName();
                                    
                                    // Check if it's Request or a subclass of Request
                                    if ($typeName === 'Illuminate\Http\Request' || 
                                        class_exists($typeName) && is_subclass_of($typeName, 'Illuminate\Http\Request')) {
                                        $request = $reflection->newInstance($mockRequest);
                                        $rules = $request->rules();
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // If all else fails, try to read rules from source code
                        $rules = $this->extractRulesFromSource($reflection);
                    }
                }
            }

            if (empty($rules)) {
                return null;
            }

            $properties = [];
            $required = [];

            foreach ($rules as $field => $rule) {
                $ruleArray = is_array($rule) ? $rule : explode('|', $rule);
                $fieldSchema = $this->convertRulesToSchema($field, $ruleArray);

                if ($fieldSchema) {
                    $properties[$field] = $fieldSchema;

                    if ($this->isRequired($ruleArray)) {
                        $required[] = $field;
                    }
                }
            }

            $schema = [
                'type' => 'object',
                'properties' => $properties,
            ];

            if (!empty($required)) {
                $schema['required'] = $required;
            }

            return $schema;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    protected function extractRulesFromSource(ReflectionClass $reflection)
    {
        try {
            $fileName = $reflection->getFileName();
            
            if (!$fileName || !file_exists($fileName)) {
                return [];
            }
            
            $fileContent = file_get_contents($fileName);
            
            // Try to extract rules array from the rules() method
            if (preg_match('/public\s+function\s+rules\(\)\s*:\s*array\s*\{([^}]+)\}/s', $fileContent, $matches)) {
                $rulesContent = $matches[1];
                
                // Try to parse the return array
                if (preg_match('/return\s*\[(.*?)\];/s', $rulesContent, $returnMatch)) {
                    $rulesArrayContent = $returnMatch[1];
                    return $this->parseRulesArrayFromString($rulesArrayContent);
                }
            }
        } catch (\Exception $e) {
            // Ignore parsing errors
        }
        
        return [];
    }
    
    protected function parseRulesArrayFromString($content)
    {
        $rules = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Match patterns like 'field' => ['rule1', 'rule2'] or 'field' => 'rule1|rule2'
            if (preg_match("/['\"]([^'\"]+)['\"]\s*=>\s*(.*)/", $line, $matches)) {
                $field = $matches[1];
                $ruleValue = trim($matches[2]);
                
                // Remove trailing comma if present
                $ruleValue = rtrim($ruleValue, ',');
                
                // Handle array format
                if (preg_match("/\[(.*?)\]/s", $ruleValue, $arrayMatch)) {
                    $ruleArray = $arrayMatch[1];
                    $ruleItems = [];
                    
                    // Extract rules from array
                    preg_match_all("/['\"]([^'\"]+)['\"]/", $ruleArray, $ruleMatches);
                    if (!empty($ruleMatches[1])) {
                        $rules[$field] = $ruleMatches[1];
                    }
                } else {
                    // Handle string format
                    $ruleValue = trim($ruleValue, "['\"]");
                    if (!empty($ruleValue)) {
                        $rules[$field] = explode('|', $ruleValue);
                    }
                }
            }
        }
        
        return $rules;
    }

    public function convertRulesToSchema($field, $rules)
    {
        $schema = [];

        $fieldDetection = config('docs-generate.field_detection', []);
        $hasClosureRules = false;
        
        // Check if there are any closure rules
        foreach ($rules as $rule) {
            if (is_object($rule) && $rule instanceof \Closure) {
                $hasClosureRules = true;
                break;
            }
        }
        
        // Only apply field detection if there are no closure rules
        if (!$hasClosureRules) {
            foreach ($fieldDetection as $key => $detection) {
                if (Str::contains($field, $key)) {
                    return $detection;
                }
            }
        }

        foreach ($rules as $rule) {
            if (is_object($rule)) {
                // Handle closure-based validation rules
                if ($rule instanceof \Closure) {
                    $closureInfo = $this->analyzeClosureRule($rule, $field);
                    if ($closureInfo) {
                        $this->applyClosureSchema($schema, $closureInfo);
                        continue;
                    }
                }
                $rule = (string) $rule;
            }

            $ruleName = $rule;
            $ruleValue = null;

            if (Str::contains($rule, ':')) {
                [$ruleName, $ruleValue] = explode(':', $rule, 2);
            }

            $mappings = config('docs-generate.validation_rule_mappings', []);

            if (isset($mappings[$ruleName])) {
                $schema['type'] = $mappings[$ruleName];
            }

            switch ($ruleName) {
                case 'exists':
                    if ($ruleValue && Str::contains($ruleValue, 'id')) {
                        $schema['type'] = 'integer';
                        $schema['description'] = 'Must exist in ' . explode(',', $ruleValue)[0];
                    }
                    break;

                case 'digits':
                    $schema['type'] = 'string';
                    $schema['pattern'] = '^[0-9]{' . $ruleValue . '}$';
                    $schema['minLength'] = (int) $ruleValue;
                    $schema['maxLength'] = (int) $ruleValue;
                    break;

                case 'digits_between':
                    if ($ruleValue && Str::contains($ruleValue, ',')) {
                        [$min, $max] = explode(',', $ruleValue);
                        $schema['type'] = 'string';
                        $schema['pattern'] = '^[0-9]{' . $min . ',' . $max . '}$';
                        $schema['minLength'] = (int) $min;
                        $schema['maxLength'] = (int) $max;
                    }
                    break;

                case 'min':
                    if ($schema['type'] === 'string' || !isset($schema['type'])) {
                        $schema['minLength'] = (int) $ruleValue;
                    } else {
                        $schema['minimum'] = (int) $ruleValue;
                    }
                    break;

                case 'max':
                    if ($schema['type'] === 'string' || !isset($schema['type'])) {
                        $schema['maxLength'] = (int) $ruleValue;
                    } else {
                        $schema['maximum'] = (int) $ruleValue;
                    }
                    break;

                case 'email':
                    $schema['type'] = 'string';
                    $schema['format'] = 'email';
                    $schema['example'] = 'user@example.com';
                    break;

                case 'url':
                    $schema['type'] = 'string';
                    $schema['format'] = 'uri';
                    break;

                case 'date':
                    $schema['type'] = 'string';
                    $schema['format'] = 'date';
                    break;

                case 'uuid':
                    $schema['type'] = 'string';
                    $schema['format'] = 'uuid';
                    break;

                case 'in':
                    $schema['enum'] = explode(',', $ruleValue);
                    break;

                case 'image':
                    $schema['type'] = 'string';
                    $schema['format'] = 'binary';
                    break;

                case 'file':
                    $schema['type'] = 'string';
                    $schema['format'] = 'binary';
                    break;

                case 'regex':
                    if ($ruleValue) {
                        $schema['pattern'] = trim($ruleValue, '/');
                    }
                    break;

                case 'alpha':
                    $schema['type'] = 'string';
                    $schema['pattern'] = '^[a-zA-Z]+$';
                    break;

                case 'alpha_num':
                    $schema['type'] = 'string';
                    $schema['pattern'] = '^[a-zA-Z0-9]+$';
                    break;

                case 'alpha_dash':
                    $schema['type'] = 'string';
                    $schema['pattern'] = '^[a-zA-Z0-9_-]+$';
                    break;
            }
        }

        if (!isset($schema['type'])) {
            if (Str::endsWith($field, '_id') || $field === 'id') {
                $schema['type'] = 'integer';
            } else {
                $schema['type'] = 'string';
            }
        }

        return $schema;
    }

    protected function isRequired($rules)
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && Str::startsWith($rule, 'required')) {
                return true;
            }
        }

        return false;
    }

    protected function applyEndpointOverrides(&$operation, $method)
    {
        $overrides = config('docs-generate.endpoint_overrides', []);

        if (!isset($overrides[$method])) {
            return;
        }

        $methodOverrides = $overrides[$method];

        if (isset($methodOverrides['body']) && isset($operation['requestBody']['content']['application/json']['schema']['properties'])) {
            foreach ($methodOverrides['body'] as $field => $exampleValue) {
                if (isset($operation['requestBody']['content']['application/json']['schema']['properties'][$field])) {
                    $operation['requestBody']['content']['application/json']['schema']['properties'][$field]['example'] = $exampleValue;
                }
            }
        }

        if (isset($methodOverrides['path']) && isset($operation['parameters'])) {
            foreach ($operation['parameters'] as &$param) {
                if ($param['in'] === 'path' && isset($methodOverrides['path'][$param['name']])) {
                    $param['example'] = $methodOverrides['path'][$param['name']];
                    $param['schema']['example'] = $methodOverrides['path'][$param['name']];
                }
            }
        }

        if (isset($methodOverrides['headers'])) {
            if (!isset($operation['parameters'])) {
                $operation['parameters'] = [];
            }

            foreach ($methodOverrides['headers'] as $headerName => $headerValue) {
                $headerExists = false;
                foreach ($operation['parameters'] as $param) {
                    if ($param['in'] === 'header' && $param['name'] === $headerName) {
                        $headerExists = true;
                        break;
                    }
                }

                if (!$headerExists) {
                    $operation['parameters'][] = [
                        'name' => $headerName,
                        'in' => 'header',
                        'required' => false,
                        'schema' => [
                            'type' => 'string',
                            'example' => $headerValue,
                        ],
                        'example' => $headerValue,
                    ];
                }
            }
        }
    }

    protected function generateResponses($method)
    {
        $responseCodes = config('docs-generate.response_codes', []);
        $codes = $responseCodes[$method] ?? [200];

        $responses = [];

        foreach ($codes as $code) {
            $responses[(string) $code] = [
                'description' => $this->getResponseDescription($code),
            ];

            if ($code >= 200 && $code < 300) {
                $responses[(string) $code]['content'] = [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                        ],
                    ],
                ];
            }
        }

        return $responses;
    }

    protected function getResponseDescription($code)
    {
        $descriptions = [
            200 => 'Successful operation',
            201 => 'Resource created successfully',
            204 => 'Resource deleted successfully',
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Resource not found',
            422 => 'Validation error',
            500 => 'Internal server error',
        ];

        return $descriptions[$code] ?? 'Response';
    }

    protected function buildApiDocument()
    {
        $config = config('docs-generate');

        $apiDoc = [
            'openapi' => $config['openapi'],
            'info' => $config['info'],
            'servers' => $config['servers'],
            'paths' => $this->paths,
        ];

        if (!empty($config['security_schemes'])) {
            $apiDoc['components'] = [
                'securitySchemes' => $config['security_schemes'],
            ];
        }

        if (!empty($this->schemas)) {
            $apiDoc['components']['schemas'] = $this->schemas;
        }

        return $apiDoc;
    }

    protected function saveApiDocument($apiDoc)
    {
        $outputPath = config('docs-generate.output_path');
        $directory = dirname($outputPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($outputPath, json_encode($apiDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function analyzeClosureRule(\Closure $closure, string $field): ?array
    {
        try {
            $reflection = new \ReflectionFunction($closure);
            $source = $this->getClosureSource($reflection);
            
            if (!$source) {
                // Fallback: return basic closure info
                return [
                    'type' => 'string',
                    'description' => 'Custom validation rule (Closure)',
                ];
            }

            return $this->parseClosureValidation($source, $field);
        } catch (\Exception $e) {
            // Fallback: return basic closure info
            return [
                'type' => 'string',
                'description' => 'Custom validation rule (Closure)',
            ];
        }
    }

    protected function getClosureSource(\ReflectionFunction $reflection): ?string
    {
        try {
            $fileName = $reflection->getFileName();
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();

            if (!$fileName || !file_exists($fileName)) {
                return null;
            }

            $fileLines = file($fileName);
            $lines = array_slice($fileLines, $startLine - 1, $endLine - $startLine + 1);
            
            return implode('', $lines);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function parseClosureValidation(string $source, string $field): ?array
    {
        $info = [
            'type' => 'string',
            'description' => 'Custom validation rule',
        ];

        // Look for common validation patterns in closure source
        if (preg_match('/return\s+.*?->(?:validate|fails|passes)/', $source)) {
            $info['description'] = 'Custom validation with additional checks';
        }

        // Check for email validation
        if (preg_match('/email|@/', $source)) {
            $info['type'] = 'string';
            $info['format'] = 'email';
            $info['description'] = 'Email validation with custom rules';
        }

        // Check for numeric validation
        if (preg_match('/is_numeric|is_int|is_float|\d+/', $source)) {
            $info['type'] = 'number';
            $info['description'] = 'Numeric validation with custom rules';
        }

        // Check for required validation
        if (preg_match('/required|not_empty|filled/', $source)) {
            $info['description'] = 'Required field with custom validation';
        }

        // Check for length validation
        if (preg_match('/strlen|length|min|max/', $source)) {
            $info['type'] = 'string';
            $info['description'] = 'String length validation with custom rules';
        }

        // Check for date validation
        if (preg_match('/date|time|Carbon|DateTime/', $source)) {
            $info['type'] = 'string';
            $info['format'] = 'date-time';
            $info['description'] = 'Date validation with custom rules';
        }

        // Check for array validation
        if (preg_match('/is_array|array|count\(/', $source)) {
            $info['type'] = 'array';
            $info['description'] = 'Array validation with custom rules';
        }

        return $info;
    }

    protected function applyClosureSchema(array &$schema, array $closureInfo): void
    {
        foreach ($closureInfo as $key => $value) {
            if ($key !== 'description' || !isset($schema['description'])) {
                $schema[$key] = $value;
            }
        }

        // Add a note that this field has custom validation
        if (isset($schema['description'])) {
            $schema['description'] .= ' (Custom validation)';
        } else {
            $schema['description'] = 'Custom validation rule';
        }
    }
}

