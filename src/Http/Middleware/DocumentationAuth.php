<?php

namespace SwaggerAuto\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentationAuth
{
    public function handle(Request $request, Closure $next)
    {
        $authConfig = config('docs-generate.documentation_auth');
        
        if (!$authConfig['enabled']) {
            return $next($request);
        }
        
        $authType = $authConfig['type'];
        
        switch ($authType) {
            case 'none':
                return $next($request);
                
            case 'authenticated':
                if (!Auth::check()) {
                    return redirect('/login')->with('error', 'You must be logged in to access the documentation.');
                }
                break;
                
            case 'specific_emails':
                if (!Auth::check()) {
                    return redirect('/login')->with('error', 'You must be logged in to access the documentation.');
                }
                
                $userEmail = Auth::user()->email;
                $allowedEmails = array_map('trim', explode(',', $authConfig['allowed_emails']));
                
                if (!in_array($userEmail, $allowedEmails)) {
                    return response()->json([
                        'error' => 'You do not have permission to access the documentation.'
                    ], 403);
                }
                break;
                
            default:
                return response()->json([
                    'error' => 'Invalid authentication configuration.'
                ], 403);
        }
        
        return $next($request);
    }
}
