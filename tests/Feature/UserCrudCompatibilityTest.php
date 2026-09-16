<?php

namespace Tests\Feature;

use App\Models\User\User;
use Illuminate\Validation\DatabasePresenceVerifierInterface;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;
use Tymon\JWTAuth\Contracts\JWTSubject;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class UserCrudCompatibilityTest extends TestCase
{
    public function test_existing_crud_routes_and_responses_are_preserved(): void
    {
        $user = new User(['nombre' => 'Yomer', 'apellido' => 'Santos', 'correo' => 'yomer@example.com', 'password' => 'hidden', 'rol' => 'user']);
        $user->setAttribute('id_usuario', 123);
        $this->actingAs(new User(['rol' => 'admin']), 'api');
        $this->assertInstanceOf(JWTSubject::class, $user);
        $this->assertSame('mysql', $user->getConnectionName());
        $this->assertSame('usuarios', $user->getTable());
        $this->assertSame('id_usuario', $user->getKeyName());
        $this->assertFalse($user->usesTimestamps());
        $presence = Mockery::mock(DatabasePresenceVerifierInterface::class);
        $presence->shouldReceive('setConnection')->with('mysql');
        $presence->shouldReceive('getCount')->andReturn(0);
        app('validator')->setPresenceVerifier($presence);
        $data = Mockery::mock('alias:App\Http\Data\User\UserData');
        $result = ['success' => true, 'message' => 'Correcto.', 'data' => $user];
        $data->shouldReceive('getUsers')->once()->andReturn(['success' => true, 'message' => 'Correcto.', 'data' => [$user]]);
        $data->shouldReceive('getUserById')->with(123)->times(3)->andReturn($result);
        $data->shouldReceive('getUserById')->with(999)->once()->andReturn(['success' => false, 'message' => 'Usuario no encontrado.', 'data' => null]);
        $data->shouldReceive('createUser')->once()->andReturn($result);
        $data->shouldReceive('updateUser')->with(123, Mockery::type('array'))->twice()->andReturn($result);
        $data->shouldReceive('deleteUser')->with(123)->once()->andReturn(['success' => true, 'message' => 'Correcto.', 'data' => null]);
        $this->getJson('/api/users/list')->assertOk()->assertJsonMissingPath('data.0.password');
        $this->getJson('/api/users/list/123')->assertOk()->assertJsonMissingPath('data.password');
        $this->getJson('/api/users/list/999')->assertNotFound();
        $input = ['nombre' => 'Yomer', 'apellido' => 'Santos', 'correo' => 'yomer@example.com', 'password' => '12345678', 'rol' => 'user'];
        $this->postJson('/api/users/create', $input)->assertCreated();
        $this->putJson('/api/users/update/123', $input)->assertOk();
        $this->patchJson('/api/users/update/123', ['nombre' => 'Yomer'])->assertOk();
        $this->deleteJson('/api/users/delete/123')->assertOk();
    }
}
