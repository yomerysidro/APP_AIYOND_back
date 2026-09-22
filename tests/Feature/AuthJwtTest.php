<?php

namespace Tests\Feature;

use App\Models\Auth\RefreshToken;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthJwtTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'jwt.secret' => bin2hex(random_bytes(32)), 'jwt.ttl' => 60,
            'jwt.blacklist_enabled' => true, 'cache.default' => 'array', 'auth_tokens.refresh_ttl_days' => 60]);
        DB::purge('mysql');
        $pdo = DB::connection('mysql')->getPdo();
        $pdo->exec('CREATE TABLE usuarios (id_usuario INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, apellido TEXT, correo TEXT UNIQUE, password TEXT, rol TEXT)');
        $pdo->exec('CREATE TABLE refresh_tokens (id TEXT PRIMARY KEY, family_id TEXT NOT NULL, id_usuario INTEGER NOT NULL, token_hash TEXT NOT NULL UNIQUE, device_hash TEXT NOT NULL, user_agent TEXT, expires_at DATETIME NOT NULL, used_at DATETIME, revoked_at DATETIME, replaced_by TEXT, created_at DATETIME, updated_at DATETIME)');
        $this->user = User::create(['nombre' => 'Yomer', 'apellido' => 'Santos', 'correo' => 'yomer@example.com',
            'password' => Hash::make('12345678'), 'rol' => 'user']);
    }

    public function test_login_returns_contract_and_stores_only_refresh_token_hash(): void
    {
        $response = $this->postJson('/api/auth/login', ['correo' => $this->user->correo, 'password' => '12345678'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonPath('data.user.rol', 'user')
            ->assertJsonPath('data.expires_in', 3600)->assertJsonStructure(['data' => [
                'user' => ['id_usuario', 'nombre', 'apellido', 'correo', 'rol', 'dni', 'telefono', 'sexo', 'fecha_nacimiento'],
                'access_token', 'refresh_token',
            ]]);

        $this->withToken($response->json('data.access_token'))->getJson('/api/auth/me')
            ->assertOk()->assertJsonPath('data.rol', 'user')
            ->assertJsonStructure(['data' => ['id_usuario', 'nombre', 'apellido', 'correo', 'rol', 'dni', 'telefono', 'sexo', 'fecha_nacimiento']]);

        $refresh = $response->json('data.refresh_token');
        $record = RefreshToken::firstOrFail();
        $this->assertNotSame($refresh, $record->token_hash);
        $this->assertSame(hash('sha256', $refresh), $record->token_hash);
        $this->assertSame($this->user->id_usuario, $record->id_usuario);
    }

    public function test_refresh_rotates_token_and_reuse_revokes_the_family(): void
    {
        $oldRefresh = $this->login()['refresh_token'];
        $rotated = $this->postJson('/api/auth/refresh', ['refresh_token' => $oldRefresh])
            ->assertOk()->assertJsonPath('data.expires_in', 3600)->json('data');
        $this->assertNotSame($oldRefresh, $rotated['refresh_token']);

        $oldRecord = RefreshToken::where('token_hash', hash('sha256', $oldRefresh))->firstOrFail();
        $this->assertNotNull($oldRecord->used_at);
        $this->assertNotNull($oldRecord->revoked_at);
        $this->assertNotNull($oldRecord->replaced_by);

        $this->postJson('/api/auth/refresh', ['refresh_token' => $oldRefresh])->assertUnauthorized()
            ->assertExactJson(['success' => false, 'message' => 'La sesión ha expirado.']);
        $this->postJson('/api/auth/refresh', ['refresh_token' => $rotated['refresh_token']])->assertUnauthorized();
    }

    public function test_expired_revoked_missing_and_unknown_refresh_tokens_are_unauthorized(): void
    {
        foreach (['expired', 'revoked'] as $state) {
            $refresh = $this->login()['refresh_token'];
            $record = RefreshToken::where('token_hash', hash('sha256', $refresh))->firstOrFail();
            $record->update($state === 'expired' ? ['expires_at' => now()->subSecond()] : ['revoked_at' => now()]);
            $this->postJson('/api/auth/refresh', ['refresh_token' => $refresh])->assertUnauthorized();
        }
        $this->postJson('/api/auth/refresh', [])->assertUnauthorized();
        $this->postJson('/api/auth/refresh', ['refresh_token' => 'unknown'])->assertUnauthorized();
    }

    public function test_logout_revokes_session_family_and_is_idempotent(): void
    {
        $login = $this->login();
        $this->withToken($login['access_token'])->postJson('/api/auth/logout', ['refresh_token' => $login['refresh_token']])->assertOk();
        $this->assertNotNull(RefreshToken::firstOrFail()->revoked_at);

        $second = $this->login();
        RefreshToken::where('token_hash', hash('sha256', $second['refresh_token']))->update(['revoked_at' => now()]);
        $this->withToken($second['access_token'])->postJson('/api/auth/logout', ['refresh_token' => $second['refresh_token']])->assertOk();
    }

    public function test_refresh_is_bound_to_device(): void
    {
        $login = $this->withHeader('X-Device-ID', 'phone-a')->postJson('/api/auth/login', [
            'correo' => $this->user->correo, 'password' => '12345678',
        ])->json('data');
        $this->withHeader('X-Device-ID', 'phone-b')->postJson('/api/auth/refresh', [
            'refresh_token' => $login['refresh_token'],
        ])->assertUnauthorized();
    }

    private function login(): array
    {
        return $this->postJson('/api/auth/login', ['correo' => $this->user->correo, 'password' => '12345678'])
            ->assertOk()->json('data');
    }
}
