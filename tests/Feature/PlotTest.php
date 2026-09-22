<?php

namespace Tests\Feature;

use App\Exceptions\Handler;
use App\Http\Data\Plot\PlotData;
use App\Http\Data\User\UserData;
use App\Models\Plot\Plot;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class PlotTest extends TestCase
{
    private User $userA;

    private User $userB;

    private string $tokenA;

    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();
        // Replace the named connection before any query: physical MySQL is never used.
        config(['database.default' => 'mysql', 'database.connections.mysql' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'jwt.secret' => bin2hex(random_bytes(32)), 'jwt.ttl' => 60, 'cache.default' => 'array']);
        DB::purge('mysql');
        $pdo = DB::connection('mysql')->getPdo();
        $this->assertSame('sqlite', $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        // Disposable test schema, not application migrations or a physical database.
        $pdo->exec('CREATE TABLE usuarios (id_usuario INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL, apellido TEXT NOT NULL, correo TEXT UNIQUE NOT NULL, password TEXT NOT NULL, dni TEXT, telefono TEXT, sexo TEXT, fecha_nacimiento TEXT, rol TEXT)');
        $pdo->exec('CREATE TABLE refresh_tokens (id TEXT PRIMARY KEY, family_id TEXT, id_usuario INTEGER, token_hash TEXT UNIQUE, device_hash TEXT, user_agent TEXT, expires_at DATETIME, used_at DATETIME, revoked_at DATETIME, replaced_by TEXT, created_at DATETIME, updated_at DATETIME)');
        $pdo->exec('CREATE TABLE parcelas (id_parcela INTEGER PRIMARY KEY AUTOINCREMENT, id_usuario INTEGER NOT NULL REFERENCES usuarios(id_usuario), nombre_parcela TEXT NOT NULL, total_hectareas DECIMAL(10,2) NOT NULL, ubicacion TEXT, fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE sectores (id_sector INTEGER PRIMARY KEY AUTOINCREMENT, id_parcela INTEGER NOT NULL REFERENCES parcelas(id_parcela) ON DELETE CASCADE, nombre_sector TEXT NOT NULL, superficie_hectareas DECIMAL(10,2) NOT NULL, descripcion TEXT, ubicacion TEXT, fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->userA = UserData::createUser(['nombre' => 'User A', 'apellido' => 'Test', 'correo' => 'a@example.com', 'password' => '12345678'])['data'];
        $this->userB = UserData::createUser(['nombre' => 'User B', 'apellido' => 'Test', 'correo' => 'b@example.com', 'password' => '12345678'])['data'];
        $this->tokenA = $this->api('POST', '/api/auth/login', ['correo' => 'a@example.com', 'password' => '12345678'])
            ->assertOk()->json('data.access_token');
        $this->tokenB = $this->api('POST', '/api/auth/login', ['correo' => 'b@example.com', 'password' => '12345678'])
            ->assertOk()->json('data.access_token');
    }

    private function api(string $method, string $uri, array $data = [], ?string $token = null): TestResponse
    {
        if (str_starts_with($uri, '/api/plots')) {
            $suffix = substr($uri, strlen('/api/plots'));
            $action = match ($method) {
                'GET' => 'list',
                'POST' => 'create',
                'PUT', 'PATCH' => 'update',
                'DELETE' => 'delete',
            };
            $uri = '/api/plots/'.$action.$suffix;
        }

        app('auth')->forgetGuards();
        app('tymon.jwt')->unsetToken();
        app('tymon.jwt.manager')->setRefreshFlow(false);
        app('tymon.jwt.payload.factory')->setRefreshFlow(false)->customClaims([]);
        app('tymon.jwt.validators.payload')->setRefreshFlow(false);

        return $this->json($method, $uri, $data, ['Authorization' => $token ? 'Bearer '.$token : '']);
    }

    private function createPlot(): TestResponse
    {
        return $this->api('POST', '/api/plots', [
            'nombre_parcela' => 'Parcela El Porvenir', 'total_hectareas' => 10.50, 'ubicacion' => 'San MartÃ­n',
            'id_usuario' => 999, 'id_parcela' => 999, 'fecha_registro' => '1990-01-01 00:00:00',
        ], $this->tokenA)->assertCreated()->assertJsonPath('data.id_usuario', $this->userA->id_usuario);
    }

    public function test_full_crud_and_immutable_owner_fields(): void
    {
        $this->api('GET', '/api/plots', [], $this->tokenA)->assertOk()->assertJsonPath('data', []);
        $created = $this->createPlot();
        $id = $created->json('data.id_parcela');
        $date = $created->json('data.fecha_registro');
        $this->assertNotSame(999, $id);
        $this->assertNotNull($date);
        $this->assertStringNotContainsString('1990', $date);
        $created->assertJsonPath('data.total_hectareas', '10.50');
        $this->api('GET', '/api/plots/'.$id, [], $this->tokenA)->assertOk();
        $this->api('PUT', '/api/plots/'.$id, [
            'nombre_parcela' => 'Parcela Actualizada', 'total_hectareas' => 12, 'ubicacion' => 'Tarapoto',
            'id_usuario' => $this->userB->id_usuario, 'id_parcela' => 999, 'fecha_registro' => '1990-01-01',
        ], $this->tokenA)->assertOk()->assertJsonPath('data.id_usuario', $this->userA->id_usuario)
            ->assertJsonPath('data.id_parcela', $id)->assertJsonPath('data.fecha_registro', $date);
        $this->api('PATCH', '/api/plots/'.$id, ['total_hectareas' => 13.50], $this->tokenA)
            ->assertOk()->assertJsonPath('data.total_hectareas', '13.50')->assertJsonPath('data.nombre_parcela', 'Parcela Actualizada');
        $this->api('PATCH', '/api/plots/'.$id, ['ubicacion' => null], $this->tokenA)
            ->assertOk()->assertJsonPath('data.ubicacion', null);
        $second = $this->createPlot()->json('data.id_parcela');
        $this->api('GET', '/api/plots', [], $this->tokenA)->assertOk()->assertJsonPath('data.0.id_parcela', $second);
        $this->assertSame($this->userA->id_usuario, Plot::findOrFail($id)->user->id_usuario);
        $this->assertSame(2, $this->userA->plots()->count());
        $this->api('DELETE', '/api/plots/'.$id, [], $this->tokenA)->assertOk()->assertJsonPath('data', null);
        $this->api('GET', '/api/plots/'.$id, [], $this->tokenA)->assertNotFound();
        $this->assertNull(Plot::find($id));
    }

    public function test_user_b_cannot_read_update_or_delete_user_a_plot(): void
    {
        $id = $this->createPlot()->json('data.id_parcela');
        $this->api('GET', '/api/plots', [], $this->tokenB)->assertOk()->assertJsonPath('data', []);
        foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->api($method, '/api/plots/'.$id, ['nombre_parcela' => 'Intrusion', 'total_hectareas' => 5], $this->tokenB)
                ->assertNotFound()->assertJsonPath('message', 'Parcela no encontrada.');
        }
        $this->api('GET', '/api/plots/'.$id, [], $this->tokenA)->assertOk()->assertJsonPath('data.nombre_parcela', 'Parcela El Porvenir');
        $own = $this->api('POST', '/api/plots', ['nombre_parcela' => 'Plot B', 'total_hectareas' => 1], $this->tokenB)
            ->assertCreated()->json('data.id_parcela');
        $this->api('GET', '/api/plots', [], $this->tokenB)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id_parcela', $own);
        $this->api('GET', '/api/plots', [], $this->tokenA)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id_parcela', $id);
    }

    public function test_validation_rules_for_post_put_and_patch(): void
    {
        $id = $this->createPlot()->json('data.id_parcela');
        foreach ([['total_hectareas' => 0], ['total_hectareas' => -5], ['nombre_parcela' => ''],
            ['nombre_parcela' => str_repeat('a', 151)], ['ubicacion' => str_repeat('a', 256)]] as $invalid) {
            foreach (['POST', 'PUT', 'PATCH'] as $method) {
                $body = array_merge(['nombre_parcela' => 'Valid', 'total_hectareas' => 10], $invalid);
                $this->api($method, '/api/plots'.($method === 'POST' ? '' : '/'.$id), $body, $this->tokenA)
                    ->assertUnprocessable()->assertJsonPath('success', false)->assertJsonStructure(['data' => [array_key_first($invalid)]]);
            }
        }
        $this->api('PUT', '/api/plots/'.$id, ['ubicacion' => 'Tarapoto'], $this->tokenA)->assertUnprocessable();
        $this->api('PATCH', '/api/plots/'.$id, ['ubicacion' => 'Tarapoto'], $this->tokenA)->assertOk();
    }

    public function test_every_route_requires_valid_jwt(): void
    {
        $this->travel(61)->minutes();
        foreach ([null, 'invalid-token', $this->tokenA] as $token) {
            foreach ([['GET', ''], ['GET', '/1'], ['POST', ''], ['PUT', '/1'], ['PATCH', '/1'], ['DELETE', '/1']] as [$method, $suffix]) {
                $this->api($method, '/api/plots'.$suffix, [], $token)->assertUnauthorized();
            }
        }
    }

    public function test_data_ignores_immutable_fields_and_rolls_back_unexpected_failure(): void
    {
        $result = PlotData::createPlot(['nombre_parcela' => 'Direct', 'total_hectareas' => 1,
            'id_usuario' => 999, 'id_parcela' => 999, 'fecha_registro' => '1990-01-01'], $this->userA->id_usuario);
        $plot = $result['data'];
        $updated = PlotData::updatePlot($plot->id_parcela, ['id_usuario' => 999, 'id_parcela' => 999,
            'fecha_registro' => '1990-01-01', 'ubicacion' => 'Updated'], $this->userA->id_usuario)['data'];
        $this->assertSame($this->userA->id_usuario, $updated->id_usuario);
        $this->assertSame($plot->id_parcela, $updated->id_parcela);
        $this->assertTrue($plot->fecha_registro->equalTo($updated->fecha_registro));
        $exception = new RuntimeException('Sensitive internal details');
        Plot::created(function () use ($exception) {
            throw $exception;
        });
        try {
            PlotData::createPlot(['nombre_parcela' => 'Rollback', 'total_hectareas' => 2], $this->userA->id_usuario);
            $this->fail('Expected original exception');
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        } finally {
            Plot::flushEventListeners();
        }
        $this->assertSame(1, Plot::count());
        $this->assertSame(0, DB::connection('mysql')->transactionLevel());
        config(['app.debug' => false]);
        $response = app(Handler::class)->render(Request::create('/api/plots'), $exception);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringNotContainsString('Sensitive internal details', $response->getContent());
        $this->assertStringNotContainsString('File:', $response->getContent());
    }
}
