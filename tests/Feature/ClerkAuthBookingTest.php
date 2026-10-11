<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Support\Clerk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClerkAuthBookingTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'test-app-1.clerk.accounts.dev';

    /** Throwaway keys used only by these tests. */
    private const SIGNING_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDFsy5QIb6TYwKg
F0l2f3KHLybHSP/EHbF0sXRRvLyDAvuHSflb86VOLAJx7Zxh0Ub/rIGKCMuB9Nf9
uL+9YnNmg1G0z2WqbWNCKPa16hUwroHwempJ51bKENBJAQto7s4WyPI5CQAUNawV
MybrB9zojOlgH2yzHT0e1qBn0XdwG7zXASU22/r1ek/xHMBBDJb/LTki7gWzqhd9
CKa7FDH3c0k9bkvtVAtVTrOWSeZIzRMWfF+NCSaJsTL1nl79HkfkfncU8sfOk+tj
InKBGYe28gDY+WOJvS5387gFG8zjEG0w6ayVhst/v7LUtwRLJNGv4QUscLznphq1
mulMQhWtAgMBAAECggEAHHvPoyNte/oypWKdhrlfhJsWRPWfRf/6MoQjrGgbJ+wM
1cXia61bpnrgQk5X7t7yUgFma38PZRKj8O4GkBBobH9kjP6pmOeoOp7Axt5qPaiS
15UnjazLPBM2kyIXpP9tCWrbvTxu+rpt5+MGCyxHl2i/8gWtCVRf2zrUgwiyot/X
U5TGERlA6pYXl+MYFZl6XJTXxTqGutRb7rKMadn+MaqpQArYDT/IIKAzMsTv08hp
v0dAjrCnBxpc7Mx9eIpvz/dOHxp7BspmawcGG3hJFSSqL/8aoO1P/HH6fngTgBtm
rhPuB0bnW87pfcCoFteA6OELJE1aPw7cjBHHVyWUoQKBgQDpUVM1TvkMN5p5tGIE
we+kMsLNKCw6nQy6NNoqY3JBCxW3WME5okUDid+JHvL9xK7eNHoaR+Jex/PuQvmG
VBechJYb7jdM7GEEqrsU3aDvFEVZelBIWQFPnRQactT3i2ON76Xzi1S/HapzSh2y
VDRRfSPmzYtZQY338kbIdQCxTQKBgQDY62wF/fzdb1ZAUG9VjmcUulASoR90Ftl6
geIz3FC7a3wiZUsv6SXH6gIGFtfz7l7zHDt32zK7Gh8IvB83dQi/W+RQYG5bG4eP
ZpZqUgkoLs3Goey9rfrt1ZE3K4v2ps/HLFFBdLiaOnOCV2MDEXWdYYByofw8mlhS
Lx/mLA7F4QKBgDYT6WtTsJnKUX2f95AOu1KdiVUv2ICFYivOkDp+7j5xKUljwvVu
zPwy+qK3j66N5dIO67Ynryo0wi8ZSuh9mTNn77rtjdfAegQw5kfBzslbM2+3z9/U
8V7Yk45Fy2LM5fhB8X7vbmcVoyoxpYxtw+qXTLj0LzYy4Yr0lr9jjxOJAoGASFFM
ZT8FA2nv7mcihvp/wThQI7XVv6UyghY1LKcBv0oZS3pLs8Bd2SVZ4V/SRT+uJgy+
grnwUtJlkErveVSqQunG10ke4X7vFc7UzMNmzB+2eMdZHu+W3Z262RLKicOZN8S9
nj3koX5lLgomN0ql1H9CDHWWmzm1wkh1sQ31KuECgYEAgdiK08bCZpexAeATxYNf
A4Fvk6IPUBPwzUQgVmNeQalUgTN/IIVDHoUzozdTcxoPC4sKNXvvf96OSe5gzTQ3
D9a8MKjUQJ5ARhH2Zlc7bdzKqTofuciP2XygdhRmgZsCco42Awp7XsZYX8ytFMLj
tItqGn+z00a/mk8RkoC5gv4=
-----END PRIVATE KEY-----
PEM;

    private const OTHER_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCikyF9m5i3Jkb5
CWmknO+wPmFfsuzN7vMuIGXi19HONaWPpUDFXOudiL5rK0ZkCGEYxgz2DI0O2uPS
fEnYN+nblCsO8tfNN8/mbnWpUAxhMB52qjXu48U1DMdH0XdUiItvZOolZPttaaf9
2J4xO9KHXW5DBiD9cSqKoFWZb+UT1zkINCf5b6Ef7UDv478r0bPcxcKiL3V4h3N6
ho/SJGt4uGVRZKzqXfUSGHBBjjI6L8++WOK/wkeLIl9ZY2cEmqa1yQv/fPw3WBeY
1I/n+SXFxA2JobDIiF9UMOTl/3DV7zVgxKsqaiDBhZJycyv4w0c1DajYI4o7U139
DvbkwRn5AgMBAAECggEADKA8Gt71bI3st6UXPwlQJSInGlFRSkoLOVMbTpN/0BHD
BqWpItrNUqn+zeBpwlWTQuMIuSgu4U83H872xjPLdfnrISlTgLTbsNP1z90vZPHh
f8pGz4OBepYAOQ/nwlkraQ6Pj7FoxG3O9ew3lu/EMS6cpFhqwfwit2rt0vGJezGd
TuAhU5+BmvCFRZ5A8KS/INuzy8ULakV7osZuli70x93bVHp1sQ56b8G2ZKU44LWr
z0LFvARk0U0NVHJ4AlRtxrYjAE5mOcs52oAFLAeWM02LjfaAOoTiGGd7W6fMwN7a
XgXbPQCrcxxEXfojLt2W5PXYphGk2T12ynkp2G4YvQKBgQDbb7Bxad+ANoVSxkkt
0LRg6T7ylXqskHYeI2GV6C2Wh01sjS2O2umZN3qsL+Rgc9lGFU9f6l0mWPKz8En/
Vb+c0Zwfq7QPYEnH5mrNluROgVTbxZHcwTp9dL+HZJE125nsXLzN6fjuUQyhK9Rb
NTVr+gGAfzoAxFUvbXcGCIpoZQKBgQC9qfIRSL2MSU3D4XnyeOs24VPamdKqIkd7
oPO+BErnw1nvrvznCNG/mBDM8ObKf2dsjeKYCCaoBTbrGXBLqkGtYAyYtgueGksG
cLgDRePNWgy+r4AE3bcmBgrCJ+gBIKd75cc+kj4Y9lpDKPHfidy1kXInn00N1xhV
uGyujR/QBQKBgAu+6r7nX+NJB9HqczhUl5MoaQorHinmgawTUMHpb6dDDs3ksK+Y
4DAnXBWjaqIPEW5C7cDAlsPatwMStaNJP7xxK942HqlECi79EnJ2xTm+ioBJDwm1
/h+BBE+G/o7XdmHEhQawo3Z+OCLYacGIE5f4BeSO6tfnqnejcF+OTXpRAoGAPz1X
NWQnr4sRnuqjmrGu3BGtwAniMFBVvWchXo5X24dLiZuzP+W06klt8KeyjHqGpa95
im/NU+DJOwK0asZON9z61kB9By6FLZSQBqdbGbGY5kvmR7kVxFvnYm2j8hxNPAaV
tMtWYURjPFl+rKZvLSteANAhBShfONXRhzIPFXECgYAdtoSaH+X46ZxRFVnbMMdG
4SFM9ozDDmaQjd3rpGwWCbC0wiaGvJ0jdt/FFNK/kzZ0JC/kM5snBH5KP7Tvs97V
nQCOLCioTcDWJVnkCy8D+wSdaqST/hPJk7zEkPuoius+Y39z2Z29R1wlXkWr7w3B
kr6qafDSqRFJ8UJ9LTwMDw==
-----END PRIVATE KEY-----
PEM;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.clerk.publishable_key' => 'pk_test_'.base64_encode(self::HOST.'$'),
            'services.clerk.secret_key' => 'sk_test_secret',
            'services.clerk.authorized_parties' => null,
        ]);
    }

    // ---- helpers -------------------------------------------------------

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function jwk(string $pem, string $kid): array
    {
        $rsa = openssl_pkey_get_details(openssl_pkey_get_private($pem))['rsa'];

        return ['kty' => 'RSA', 'kid' => $kid, 'alg' => 'RS256', 'use' => 'sig', 'n' => self::b64($rsa['n']), 'e' => self::b64($rsa['e'])];
    }

    /** Build a signed JWT like Clerk's session tokens. */
    private function token(array $claims = [], array $header = [], ?string $signWith = null): string
    {
        $header = array_merge(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'ins_kid1'], $header);
        $claims = array_merge([
            'iss' => 'https://'.self::HOST, 'sub' => 'user_2abcXYZ', 'sid' => 'sess_1',
            'iat' => time(), 'nbf' => time() - 5, 'exp' => time() + 60,
        ], $claims);

        $data = self::b64(json_encode($header)).'.'.self::b64(json_encode($claims));
        openssl_sign($data, $sig, openssl_pkey_get_private($signWith ?? self::SIGNING_KEY), OPENSSL_ALGO_SHA256);

        return $data.'.'.self::b64($sig);
    }

    private function fakeClerk(array $user = [], int $profileStatus = 200): void
    {
        Http::fake([
            self::HOST.'/.well-known/jwks.json' => Http::response(['keys' => [$this->jwk(self::SIGNING_KEY, 'ins_kid1')]]),
            'api.clerk.com/v1/users/*' => Http::response(array_merge([
                'id' => 'user_2abcXYZ', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'username' => null,
                'image_url' => 'https://img.clerk.com/ada.png',
                'primary_email_address_id' => 'idn_1',
                'email_addresses' => [['id' => 'idn_1', 'email_address' => 'ada@example.com', 'verification' => ['status' => 'verified']]],
            ], $user), $profileStatus),
        ]);
    }

    /** @return array{0: User, 1: string} */
    private function signedInUser(string $email = 'ada@example.com'): array
    {
        $user = User::create(['name' => 'Ada', 'email' => $email, 'password' => Str::random(20)]);
        $plain = Str::random(64);
        ApiToken::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $plain)]);

        return [$user, $plain];
    }

    private function room(): Room
    {
        return Room::create(['name' => 'Room 1', 'capacity' => 8, 'floor' => 1, 'color' => '#4f46e5']);
    }

    private function payload(Room $room, array $extra = []): array
    {
        return array_merge([
            'room_id' => $room->id, 'title' => 'Sync', 'date' => '2030-01-01',
            'start_time' => '10:00', 'end_time' => '11:00', 'attendees' => 3,
        ], $extra);
    }

    // ---- Clerk helpers -------------------------------------------------

    public function test_frontend_api_is_decoded_from_the_publishable_key(): void
    {
        $this->assertSame(self::HOST, Clerk::frontendApi());
        $this->assertArrayNotHasKey('secret_key', Clerk::forBrowser());
    }

    public function test_jwk_converts_to_the_same_pem_openssl_produces(): void
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_private(self::SIGNING_KEY));

        $this->assertSame($details['key'], Clerk::jwkToPem($this->jwk(self::SIGNING_KEY, 'k')));
    }

    // ---- sign in -------------------------------------------------------

    public function test_clerk_sign_in_creates_user_and_returns_app_token(): void
    {
        $this->fakeClerk();

        $res = $this->postJson('/api/auth/clerk', ['token' => $this->token()])->assertOk();

        $this->assertSame('Ada Lovelace', $res->json('user.name'));
        $this->assertDatabaseHas('users', ['email' => 'ada@example.com', 'clerk_id' => 'user_2abcXYZ']);
        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$res->json('token')])
            ->assertOk()->assertJsonPath('email', 'ada@example.com');
    }

    public function test_signing_in_twice_reuses_the_same_user(): void
    {
        $this->fakeClerk();

        $this->postJson('/api/auth/clerk', ['token' => $this->token()])->assertOk();
        $this->postJson('/api/auth/clerk', ['token' => $this->token()])->assertOk();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_token_signed_with_another_key_is_rejected(): void
    {
        $this->fakeClerk();

        $this->postJson('/api/auth/clerk', ['token' => $this->token(signWith: self::OTHER_KEY)])->assertStatus(401);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_tampered_payload_is_rejected(): void
    {
        $this->fakeClerk();
        [$h, , $s] = explode('.', $this->token());
        $forged = $h.'.'.self::b64(json_encode(['iss' => 'https://'.self::HOST, 'sub' => 'user_admin', 'exp' => time() + 60])).'.'.$s;

        $this->postJson('/api/auth/clerk', ['token' => $forged])->assertStatus(401);
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->fakeClerk();

        $this->postJson('/api/auth/clerk', ['token' => $this->token(['exp' => time() - 3600])])->assertStatus(401);
    }

    public function test_token_from_another_issuer_is_rejected(): void
    {
        $this->fakeClerk();

        $this->postJson('/api/auth/clerk', ['token' => $this->token(['iss' => 'https://evil.example.com'])])->assertStatus(401);
    }

    public function test_only_rs256_is_accepted(): void
    {
        $this->fakeClerk();

        foreach (['none', 'HS256'] as $alg) {
            $this->postJson('/api/auth/clerk', ['token' => $this->token(header: ['alg' => $alg])])->assertStatus(401);
        }
    }

    public function test_unverified_email_is_rejected(): void
    {
        $this->fakeClerk(['email_addresses' => [['id' => 'idn_1', 'email_address' => 'ada@example.com', 'verification' => ['status' => 'unverified']]]]);

        $this->postJson('/api/auth/clerk', ['token' => $this->token()])->assertStatus(401);
    }

    public function test_unknown_origin_is_rejected_when_authorized_parties_are_set(): void
    {
        config(['services.clerk.authorized_parties' => 'https://meet.example.com']);
        $this->fakeClerk();

        $this->postJson('/api/auth/clerk', ['token' => $this->token(['azp' => 'https://evil.example.com'])])->assertStatus(401);
        $this->postJson('/api/auth/clerk', ['token' => $this->token(['azp' => 'https://meet.example.com'])])->assertOk();
    }

    public function test_sign_in_reports_missing_server_configuration(): void
    {
        config(['services.clerk.secret_key' => null]);

        $this->postJson('/api/auth/clerk', ['token' => 'x.y.z'])->assertStatus(503);
    }

    // ---- bookings tied to a user --------------------------------------

    public function test_booking_requires_sign_in(): void
    {
        $this->postJson('/api/bookings', $this->payload($this->room()))->assertStatus(401);
    }

    public function test_booking_is_saved_for_the_signed_in_user(): void
    {
        [$user, $token] = $this->signedInUser();

        $this->postJson('/api/bookings', $this->payload($this->room()), ['Authorization' => "Bearer $token"])
            ->assertCreated()
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('organizer', 'Ada');
    }

    public function test_my_bookings_lists_only_my_own(): void
    {
        [$ada, $adaToken] = $this->signedInUser();
        [$bob] = $this->signedInUser('bob@example.com');
        $room = $this->room();
        Booking::create($this->payload($room, ['user_id' => $ada->id, 'organizer' => 'Ada']));
        Booking::create($this->payload($room, ['user_id' => $bob->id, 'organizer' => 'Bob', 'start_time' => '12:00', 'end_time' => '13:00']));

        $this->getJson('/api/me/bookings', ['Authorization' => "Bearer $adaToken"])
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.organizer', 'Ada')->assertJsonPath('0.room.name', 'Room 1');
    }

    public function test_user_can_cancel_own_but_not_others_bookings(): void
    {
        [$ada, $adaToken] = $this->signedInUser();
        [$bob] = $this->signedInUser('bob@example.com');
        $room = $this->room();
        $mine = Booking::create($this->payload($room, ['user_id' => $ada->id, 'organizer' => 'Ada']));
        $theirs = Booking::create($this->payload($room, ['user_id' => $bob->id, 'organizer' => 'Bob', 'start_time' => '12:00', 'end_time' => '13:00']));
        $auth = ['Authorization' => "Bearer $adaToken"];

        $this->deleteJson("/api/me/bookings/{$theirs->id}", [], $auth)->assertForbidden();
        $this->deleteJson("/api/me/bookings/{$mine->id}", [], $auth)->assertNoContent();
        $this->assertDatabaseMissing('bookings', ['id' => $mine->id]);
        $this->assertDatabaseHas('bookings', ['id' => $theirs->id]);
    }

    public function test_logout_invalidates_the_token(): void
    {
        [, $token] = $this->signedInUser();
        $auth = ['Authorization' => "Bearer $token"];

        $this->postJson('/api/auth/logout', [], $auth)->assertNoContent();
        $this->getJson('/api/me', $auth)->assertStatus(401);
    }
}
