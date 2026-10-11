<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a request by the bearer token issued after Google sign-in.
 * Only a sha256 hash of the token is stored in the database.
 */
class AuthenticateToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        $token = $plain
            ? ApiToken::with('user')
                ->where('token_hash', hash('sha256', $plain))
                ->where('created_at', '>=', now()->subDays(ApiToken::LIFETIME_DAYS))
                ->first()
            : null;

        if (! $token || ! $token->user) {
            return response()->json(['message' => 'Please sign in.'], 401);
        }

        $user = $token->user;
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
