<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;

class AuthenticateProject
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-Key');
        
        if (!$apiKey) {
            return response()->json(['error' => 'API key required'], 401);
        }

        $project = Project::where('api_key', $apiKey)
            ->where('is_active', true)
            ->first();
        
        if (!$project) {
            return response()->json(['error' => 'Invalid or inactive API key'], 401);
        }

        $request->attributes->set('project', $project);
        
        return $next($request);
    }
}
