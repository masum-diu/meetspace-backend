<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use App\Support\Clerk;
use App\Support\ClerkException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Exchange a Clerk session token (from the signed-in browser) for an app token.
     */
    public function clerk(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string', 'max:8192']]);

        if (! Clerk::isConfigured()) {
            return response()->json(['message' => 'Sign-in is not configured on the server.'], 503);
        }

        try {
            $claims = Clerk::claims($request->input('token'));
            $profile = Clerk::profile($claims['sub']);
        } catch (ClerkException) {
            return response()->json(['message' => 'Sign-in could not be verified. Please try again.'], 401);
        } catch (ConnectionException) {
            return response()->json(['message' => 'Could not reach the sign-in service. Please try again.'], 502);
        }

        $user = User::where('clerk_id', $claims['sub'])->first()
            ?? User::where('email', $profile['email'])->first()
            ?? new User(['email' => $profile['email'], 'password' => Str::random(40)]);

        $user->fill([
            'name' => $profile['name'],
            'clerk_id' => $claims['sub'],
            'avatar' => $profile['avatar'],
        ])->save();

        // forget expired sign-ins of this user, then issue a fresh one
        ApiToken::where('user_id', $user->id)
            ->where('created_at', '<', now()->subDays(ApiToken::LIFETIME_DAYS))
            ->delete();

        $plain = Str::random(64);
        ApiToken::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $plain)]);

        return response()->json(['token' => $plain, 'user' => $this->present($user)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->present($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('api_token')?->delete();

        return response()->json(null, 204);
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
        ];
    }
}
