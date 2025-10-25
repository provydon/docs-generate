<?php

namespace SwaggerAuto\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HandleCors
{
    public function handle(Request $request, Closure $next)
    {
        $uri = $request->path();

        if (!$this->shouldApplyCors($uri)) {
            return $next($request);
        }

        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflightRequest();
        }

        $response = $next($request);

        return $this->addCorsHeaders($response);
    }

    protected function shouldApplyCors($uri)
    {
        return str_starts_with($uri, 'api/') || 
               str_ends_with($uri, 'docs.json') ||
               str_contains($uri, 'documentation');
    }

    protected function handlePreflightRequest()
    {
        return response('', 200, $this->getCorsHeaders());
    }

    protected function addCorsHeaders($response)
    {
        foreach ($this->getCorsHeaders() as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    protected function getCorsHeaders()
    {
        $config = config('swagger-auto.cors', []);

        $allowOrigins = $config['allow_origins'] ?? [env('APP_URL', 'http://localhost')];
        $allowMethods = $config['allow_methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
        $allowHeaders = $config['allow_headers'] ?? ['Content-Type', 'Authorization', 'Accept'];
        $allowCredentials = $config['allow_credentials'] ?? false;
        $maxAge = $config['max_age'] ?? 86400;

        return [
            'Access-Control-Allow-Origin' => implode(', ', $allowOrigins),
            'Access-Control-Allow-Methods' => implode(', ', $allowMethods),
            'Access-Control-Allow-Headers' => implode(', ', $allowHeaders),
            'Access-Control-Allow-Credentials' => $allowCredentials ? 'true' : 'false',
            'Access-Control-Max-Age' => (string) $maxAge,
        ];
    }
}

