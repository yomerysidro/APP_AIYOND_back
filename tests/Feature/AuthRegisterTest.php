<?php

namespace Tests\Feature;

use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\DatabasePresenceVerifierInterface;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AuthRegisterTest extends TestCase
{
    public function test_registration_reuses_user_data_forces_role_and_returns_jwt(): void
    {
        config(['database.connections.mysql' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'jwt.secret' => bin2hex(random_bytes(32)), 'jwt.ttl' => 60]);
        DB::purge('mysql');
        DB::connection('mysql')->getPdo()->exec('CREATE TABLE refresh_tokens (id TEXT PRIMARY KEY, family_id TEXT, id_usuario INTEGER, token_hash TEXT UNIQUE, device_hash TEXT, user_agent TEXT, expires_at DATETIME, used_at DATETIME, revoked_at DATETIME, replaced_by TEXT, created_at DATETIME, updated_at DATETIME)');
        $presence = Mockery::mock(DatabasePresenceVerifierInterface::class);
        $presence->shouldReceive('setConnection')->with('mysql');
        $presence->shouldReceive('getCount')->andReturn(0);
        app('validator')->setPresenceVerifier($presence);
        $data = Mockery::mock('alias:App\Http\Data\User\UserData');
        $data->shouldReceive('createUser')->once()->with(Mockery::on(function ($input) {
            return $input['rol'] === 'user' && $input['correo'] === 'yomer@example.com';
        }))->andReturnUsing(function ($input) {
            $input['password'] = Hash::make($input['password']);
            $user = new User($input);
            $user->setAttribute('id_usuario', 123);

            return ['success' => true, 'message' => 'Usuario creado correctamente.', 'data' => $user];
        });
        $response = $this->postJson('/api/auth/register', [
            'nombre' => 'Yomer', 'apellido' => 'Santos', 'correo' => 'yomer@example.com',
            'password' => '12345678', 'rol' => 'admin',
        ]);
        $response->assertCreated()->assertJsonPath('data.user.correo', 'yomer@example.com')
            ->assertJsonPath('data.token_type', 'bearer')->assertJsonPath('data.expires_in', 3600)
            ->assertJsonStructure(['data' => ['refresh_token']])
            ->assertJsonMissingPath('data.user.password');
        $payload = app('tymon.jwt')->setToken($response->json('data.access_token'))->getPayload();
        $this->assertEquals(123, $payload['sub']);
    }
}
