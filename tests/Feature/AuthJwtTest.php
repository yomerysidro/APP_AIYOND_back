<?php

namespace Tests\Feature;

use App\Models\User\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class AuthJwtTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => bin2hex(random_bytes(32)), 'jwt.ttl' => 60,
            'jwt.refresh_ttl' => 20160, 'jwt.blacklist_enabled' => true,
            'cache.default' => 'array']);
        $this->user = new User(['nombre' => 'Yomer', 'apellido' => 'Santos',
            'correo' => 'yomer@example.com', 'password' => Hash::make('12345678'), 'rol' => 'user']);
        $this->user->setAttribute('id_usuario', 123);
        // Keep real JWT signing, hashing and blacklist; replace only database reads.
        $provider = Mockery::mock(EloquentUserProvider::class, [app('hash'), User::class])->makePartial();
        $provider->shouldReceive('retrieveById')->andReturnUsing(fn ($id) => $id == 123 ? $this->user : null);
        $provider->shouldReceive('retrieveByCredentials')->andReturnUsing(
            fn ($credentials) => $credentials['correo'] === $this->user->correo ? $this->user : null,
        );
        app('auth')->provider('jwt_test_users', fn () => $provider);
        config(['auth.providers.users.driver' => 'jwt_test_users']);
    }

    private function nextRequest(): void
    {
        // Emulate a new PHP request, preserving the blacklist cache.
        app('auth')->forgetGuards();
        app('tymon.jwt')->unsetToken();
        app('tymon.jwt.manager')->setRefreshFlow(false);
        app('tymon.jwt.payload.factory')->setRefreshFlow(false)->customClaims([]);
        app('tymon.jwt.validators.payload')->setRefreshFlow(false);
    }

    public function test_login_me_logout_and_blacklist(): void
    {
        $login = $this->postJson('/api/auth/login', ['correo' => $this->user->correo, 'password' => '12345678']);
        $login->assertOk()->assertJsonPath('data.token_type', 'bearer')->assertJsonPath('data.expires_in', 3600);
        $this->assertArrayNotHasKey('password', $login->json('data.user'));
        $token = $login->json('data.access_token');
        $this->nextRequest();
        $this->withToken($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.id_usuario', 123)
            ->assertJsonMissingPath('data.password');
        $this->nextRequest();
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk()->assertJsonPath('data', null);
        $this->nextRequest();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
        $this->nextRequest();
        $this->withToken($token)->postJson('/api/auth/refresh')->assertUnauthorized();
    }

    public function test_expired_token_can_refresh_once_within_window(): void
    {
        $token = auth('api')->login($this->user);
        $this->travel(61)->minutes();
        $this->nextRequest();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
        $this->nextRequest();
        $refresh = $this->withToken($token)->postJson('/api/auth/refresh');
        $refresh->assertOk()->assertJsonPath('data.expires_in', 3600)->assertJsonPath('data.token_type', 'bearer');
        $newToken = $refresh->json('data.access_token');
        $this->assertNotSame($token, $newToken);
        $this->nextRequest();
        $this->withToken($newToken)->getJson('/api/auth/me')->assertOk();
        $this->nextRequest();
        $this->withToken($token)->postJson('/api/auth/refresh')->assertUnauthorized();
    }

    public function test_refresh_window_is_finite(): void
    {
        $token = auth('api')->login($this->user);
        $this->travel(15)->days();
        $this->nextRequest();
        $this->withToken($token)->postJson('/api/auth/refresh')->assertUnauthorized()->assertJsonPath('code', 401);
    }

    public function test_missing_and_invalid_tokens_are_unauthorized(): void
    {
        foreach ([null, 'invalid-token'] as $token) {
            foreach (['me', 'logout', 'refresh'] as $endpoint) {
                $this->nextRequest();
                $this->withHeaders(['Authorization' => $token ? 'Bearer '.$token : '']);
                $response = $endpoint === 'me' ? $this->getJson('/api/auth/me') : $this->postJson('/api/auth/'.$endpoint);
                $response->assertUnauthorized()->assertJsonPath('code', 401);
            }
        }
    }

    public function test_incorrect_credentials_do_not_disclose_which_field_failed(): void
    {
        foreach ([[$this->user->correo, 'wrong'], ['missing@example.com', '12345678']] as [$correo, $password]) {
            $this->nextRequest();
            $this->postJson('/api/auth/login', compact('correo', 'password'))
                ->assertUnauthorized()->assertJsonPath('message', 'Credenciales incorrectas.');
        }
    }

    public function test_validation_uses_existing_api_response(): void
    {
        $this->postJson('/api/auth/register', [])->assertUnprocessable()->assertJsonPath('success', false)
            ->assertJsonStructure(['data' => ['nombre', 'apellido', 'correo', 'password']]);
        $this->postJson('/api/auth/login', [])->assertUnprocessable()->assertJsonStructure(['data' => ['correo', 'password']]);
    }

    public function test_missing_token_without_accept_header_is_unauthorized(): void
    {
        $this->get('/api/auth/me')->assertUnauthorized()->assertJsonPath('code', 401);
    }
}
