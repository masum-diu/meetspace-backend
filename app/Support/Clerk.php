<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Verifies Clerk session tokens (RS256 JWTs) against the instance's public keys
 * and reads the verified user's profile from the Clerk Backend API.
 */
final class Clerk
{
    /** Seconds of clock drift tolerated on exp / nbf. */
    private const LEEWAY = 15;

    public static function publishableKey(): ?string
    {
        return config('services.clerk.publishable_key') ?: null;
    }

    /** The Frontend API host is base64-encoded inside the publishable key: pk_test_<base64("host$")>. */
    public static function frontendApi(): ?string
    {
        $key = self::publishableKey();
        if (! $key || ! preg_match('/^pk_(?:test|live)_(.+)$/', $key, $m)) {
            return null;
        }

        $host = rtrim((string) base64_decode($m[1]), '$');

        return preg_match('/^[a-z0-9.-]+$/i', $host) ? strtolower($host) : null;
    }

    public static function isConfigured(): bool
    {
        return self::frontendApi() !== null && (bool) config('services.clerk.secret_key');
    }

    /** Values the browser needs to load Clerk (never includes the secret key). */
    public static function forBrowser(): array
    {
        return ['publishableKey' => self::publishableKey(), 'frontendApi' => self::frontendApi()];
    }

    /**
     * Verify the token's signature, expiry and issuer and return its claims.
     *
     * @return array<string, mixed>
     *
     * @throws ClerkException
     */
    public static function claims(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new ClerkException('Malformed token.');
        }
        [$h64, $p64, $s64] = $parts;

        $header = json_decode((string) self::b64($h64), true);
        $payload = json_decode((string) self::b64($p64), true);
        $signature = self::b64($s64);

        // Only RS256 is accepted, so a token can never pick a weaker algorithm (e.g. "none" or HS256).
        if (! is_array($header) || ! is_array($payload) || $signature === null
            || ($header['alg'] ?? null) !== 'RS256' || empty($header['kid'])) {
            throw new ClerkException('Unsupported token.');
        }

        $jwk = self::keyFor((string) $header['kid']);
        $valid = openssl_verify("$h64.$p64", $signature, self::jwkToPem($jwk), OPENSSL_ALGO_SHA256) === 1;
        if (! $valid) {
            throw new ClerkException('Bad signature.');
        }

        $now = time();
        if (! isset($payload['exp']) || $payload['exp'] < $now - self::LEEWAY) {
            throw new ClerkException('Token expired.');
        }
        if (isset($payload['nbf']) && $payload['nbf'] > $now + self::LEEWAY) {
            throw new ClerkException('Token not valid yet.');
        }
        if (($payload['iss'] ?? null) !== 'https://'.self::frontendApi()) {
            throw new ClerkException('Wrong issuer.');
        }
        if (! preg_match('/^user_[A-Za-z0-9]+$/', (string) ($payload['sub'] ?? ''))) {
            throw new ClerkException('Not a user token.');
        }

        // Optional: only accept tokens minted for our own origins.
        $allowed = array_filter(array_map('trim', explode(',', (string) config('services.clerk.authorized_parties'))));
        if ($allowed && isset($payload['azp']) && ! in_array($payload['azp'], $allowed, true)) {
            throw new ClerkException('Unexpected origin.');
        }

        return $payload;
    }

    /**
     * Name, verified primary email and picture of a Clerk user.
     *
     * @return array{name: string, email: string, avatar: ?string}
     *
     * @throws ClerkException
     */
    public static function profile(string $userId): array
    {
        $res = Http::withToken((string) config('services.clerk.secret_key'))
            ->acceptJson()->timeout(10)
            ->get('https://api.clerk.com/v1/users/'.rawurlencode($userId));

        if ($res->failed()) {
            throw new ClerkException('Could not load the Clerk profile.');
        }
        $user = $res->json();

        $primary = collect($user['email_addresses'] ?? [])->firstWhere('id', $user['primary_email_address_id'] ?? null);
        $email = $primary['email_address'] ?? null;
        if (! $email || ($primary['verification']['status'] ?? null) !== 'verified') {
            throw new ClerkException('No verified email address.');
        }

        $name = trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? ''))
            ?: ($user['username'] ?? null)
            ?: strstr($email, '@', true);

        return ['name' => $name, 'email' => $email, 'avatar' => $user['image_url'] ?? null];
    }

    /** Public key with the given id; refetches the key set once if it rotated. */
    private static function keyFor(string $kid): array
    {
        foreach ([false, true] as $fresh) {
            foreach (self::jwks($fresh) as $jwk) {
                if (($jwk['kid'] ?? null) === $kid && ($jwk['kty'] ?? null) === 'RSA') {
                    return $jwk;
                }
            }
        }

        throw new ClerkException('Unknown signing key.');
    }

    /** @return list<array<string, mixed>> */
    private static function jwks(bool $fresh): array
    {
        if ($fresh) {
            Cache::forget('clerk.jwks');
        }

        return Cache::remember('clerk.jwks', 3600, function () {
            $res = Http::timeout(10)->get('https://'.self::frontendApi().'/.well-known/jwks.json');
            if ($res->failed() || ! is_array($res->json('keys'))) {
                throw new ClerkException('Could not load Clerk signing keys.');
            }

            return $res->json('keys');
        });
    }

    /** Convert an RSA JWK (n, e) to a PEM public key for openssl. */
    public static function jwkToPem(array $jwk): string
    {
        $der = fn (string $tag, string $body) => $tag.self::derLength(strlen($body)).$body;
        $int = function (string $bin) use ($der) {
            $bin = ltrim($bin, "\0");
            if ($bin === '' || ord($bin[0]) > 0x7F) {
                $bin = "\0".$bin;
            }

            return $der("\x02", $bin);
        };

        $rsaKey = $der("\x30", $int((string) self::b64($jwk['n'] ?? '')).$int((string) self::b64($jwk['e'] ?? '')));
        $algorithm = $der("\x30", "\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01\x05\x00"); // rsaEncryption
        $spki = $der("\x30", $algorithm.$der("\x03", "\0".$rsaKey));

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }
        $bytes = ltrim(pack('N', $length), "\0");

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private static function b64(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
