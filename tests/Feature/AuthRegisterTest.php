<?php

namespace Tests\Feature;

use App\Models\User\User;
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
        config(['jwt.secret' => bin2hex(random_bytes(32)), 'jwt.ttl' => 60]);
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
        $response->assertCreated()->assertJsonPath('data.user.rol', 'user')
            ->assertJsonPath('data.token_type', 'bearer')->assertJsonPath('data.expires_in', 3600)
            ->assertJsonMissingPath('data.user.password');
        $payload = app('tymon.jwt')->setToken($response->json('data.access_token'))->getPayload();
        $this->assertEquals(123, $payload['sub']);
    }
}
