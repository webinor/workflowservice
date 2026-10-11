<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Http;

class JwtIntrospection
{

    

    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();


        if (!$token) {

        if ($request->header('X-Service-Token')
            === config('services.internal.token')) {

            return $next($request);
        }

            return response()->json(['error' => 'Token manquant'], 401);
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer $token"
        ])->get(config('services.user_service.base_url') . '/auth/validate');

        if ($response->failed()) {
            return response()->json(['error' => 'Token invalide'], 401);
        }

        $request->attributes->add(['user' => $response->json()]);

        return $next($request);
    }
}
