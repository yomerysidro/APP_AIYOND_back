<?php

namespace Tests\Feature;

use App\Http\Data\Sector\SectorData;
use App\Http\Data\User\UserData;
use App\Models\Plot\Plot;
use App\Models\Sector\Sector;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class SectorTest extends TestCase
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
        $pdo->exec('CREATE TABLE escaneos_diagnosticos (id_escaneo_diagnostico INTEGER PRIMARY KEY AUTOINCREMENT, id_sector INTEGER NOT NULL REFERENCES sectores(id_sector) ON DELETE CASCADE, area_afectada_ha DECIMAL(10,2))');
        $this->userA = UserData::createUser(['nombre' => 'User A', 'apellido' => 'Test', 'correo' => 'a@example.com', 'password' => '12345678'])['data'];
        $this->userB = UserData::createUser(['nombre' => 'User B', 'apellido' => 'Test', 'correo' => 'b@example.com', 'password' => '12345678'])['data'];
        $this->tokenA = $this->api('POST', '/api/auth/login', ['correo' => 'a@example.com', 'password' => '12345678'])
            ->assertOk()->json('data.access_token');
        $this->tokenB = $this->api('POST', '/api/auth/login', ['correo' => 'b@example.com', 'password' => '12345678'])
            ->assertOk()->json('data.access_token');
    }

    private function api(string $method, string $uri, array $data = [], ?string $token = null): TestResponse
    {
        app('auth')->forgetGuards();
        app('tymon.jwt')->unsetToken();
        app('tymon.jwt.manager')->setRefreshFlow(false);
        app('tymon.jwt.payload.factory')->setRefreshFlow(false)->customClaims([]);
        app('tymon.jwt.validators.payload')->setRefreshFlow(false);

        return $this->json($method, $uri, $data, ['Authorization' => $token ? 'Bearer '.$token : '']);
    }

    private function createPlot(string $token, int $area = 10): int
    {
        return $this->api('POST', '/api/plots/create', ['nombre_parcela' => 'Test plot', 'total_hectareas' => $area], $token)
            ->assertCreated()->json('data.id_parcela');
    }

    private function createSector(int $plotId, float $area, ?string $token = null): TestResponse
    {
        return $this->api('POST', '/api/sectors/create/'.$plotId, [
            'nombre_sector' => 'Sector Norte', 'superficie_hectareas' => $area,
            'descripcion' => 'Zona norte', 'ubicacion' => 'Parte norte',
            'id_parcela' => 999, 'id_usuario' => 999, 'id_sector' => 999, 'fecha_registro' => '1990-01-01',
        ], $token ?? $this->tokenA);
    }

    public function test_sector_crud_and_immutable_fields(): void
    {
        $plotId = $this->createPlot($this->tokenA);
        $this->api('GET', '/api/sectors/list', [], $this->tokenA)->assertOk()->assertJsonPath('data', []);
        $this->api('GET', '/api/sectors/by-plot/'.$plotId, [], $this->tokenA)->assertOk()->assertJsonPath('data', []);
        $created = $this->createSector($plotId, 3.5)->assertCreated()->assertJsonPath('data.id_parcela', $plotId)
            ->assertJsonPath('data.superficie_hectareas', '3.50');
        $id = $created->json('data.id_sector');
        $date = $created->json('data.fecha_registro');
        $this->assertNotSame(999, $id);
        $this->assertNotNull($date);
        $this->assertStringNotContainsString('1990', $date);
        $this->api('GET', '/api/sectors/list/'.$id, [], $this->tokenA)->assertOk();
        $this->api('GET', '/api/sectors/by-plot/'.$plotId, [], $this->tokenA)->assertOk()->assertJsonPath('data.0.id_sector', $id);
        $this->api('PUT', '/api/sectors/update/'.$id, [
            'nombre_sector' => 'Updated', 'superficie_hectareas' => 3.75,
            'id_sector' => 999, 'id_parcela' => 999, 'fecha_registro' => '1990-01-01',
        ], $this->tokenA)->assertOk()->assertJsonPath('data.id_sector', $id)
            ->assertJsonPath('data.id_parcela', $plotId)->assertJsonPath('data.fecha_registro', $date);
        $this->api('PATCH', '/api/sectors/update/'.$id, ['superficie_hectareas' => 4], $this->tokenA)
            ->assertOk()->assertJsonPath('data.superficie_hectareas', '4.00')->assertJsonPath('data.nombre_sector', 'Updated');
        $this->api('PATCH', '/api/sectors/update/'.$id, ['descripcion' => null, 'ubicacion' => 'South'], $this->tokenA)
            ->assertOk()->assertJsonPath('data.descripcion', null);
        $secondId = $this->createSector($plotId, 1)->assertCreated()->json('data.id_sector');
        $this->api('GET', '/api/sectors/list', [], $this->tokenA)->assertOk()->assertJsonPath('data.0.id_sector', $secondId);
        $this->assertSame($plotId, Sector::findOrFail($id)->plot->id_parcela);
        $this->assertSame(2, Plot::findOrFail($plotId)->sectors()->count());
        $this->api('DELETE', '/api/sectors/delete/'.$id, [], $this->tokenA)->assertOk()->assertJsonPath('data', null);
        $this->api('GET', '/api/sectors/list/'.$id, [], $this->tokenA)->assertNotFound();
        $this->assertNull(Sector::find($id));
    }

    public function test_owner_isolation_in_both_directions(): void
    {
        $plotA = $this->createPlot($this->tokenA);
        $plotB = $this->createPlot($this->tokenB);
        $sectorA = $this->createSector($plotA, 3)->assertCreated()->json('data.id_sector');
        $sectorB = $this->createSector($plotB, 3, $this->tokenB)->assertCreated()->json('data.id_sector');
        foreach ([[$this->tokenA, $plotB, $sectorB, $sectorA], [$this->tokenB, $plotA, $sectorA, $sectorB]] as [$token, $foreignPlot, $foreignSector, $ownSector]) {
            foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
                $this->api($method, '/api/sectors/'.($method === 'GET' ? 'list/' : ($method === 'DELETE' ? 'delete/' : 'update/')).$foreignSector, ['nombre_sector' => 'Intrusion', 'superficie_hectareas' => 1], $token)
                    ->assertNotFound()->assertJsonPath('message', 'Sector no encontrado.');
            }
            $this->api('GET', '/api/sectors/by-plot/'.$foreignPlot, [], $token)->assertNotFound();
            $this->createSector($foreignPlot, 1, $token)->assertNotFound()->assertJsonPath('message', 'Parcela no encontrada.');
            $this->api('GET', '/api/sectors/list', [], $token)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id_sector', $ownSector);
        }
        $this->assertSame(2, Sector::count());
    }

    public function test_area_capacity_and_excluding_current_sector(): void
    {
        $plotId = $this->createPlot($this->tokenA);
        $a = $this->createSector($plotId, 3)->assertCreated()->json('data.id_sector');
        $this->createSector($plotId, 4)->assertCreated();
        $this->createSector($plotId, 3)->assertCreated();
        $this->createSector($plotId, 1)->assertUnprocessable()
            ->assertJsonPath('message', 'La superficie total de los sectores no puede superar las hectáreas de la parcela.');
        foreach (['PUT', 'PATCH'] as $method) {
            $this->api($method, '/api/sectors/'.($method === 'GET' ? 'list/' : ($method === 'DELETE' ? 'delete/' : 'update/')).$a, ['nombre_sector' => 'Sector A', 'superficie_hectareas' => 4], $this->tokenA)->assertUnprocessable();
        }
        $this->assertSame('3.00', Sector::findOrFail($a)->superficie_hectareas);
        $this->api('PATCH', '/api/sectors/update/'.$a, ['superficie_hectareas' => 2.5], $this->tokenA)->assertOk();
        $this->assertEquals(9.5, Sector::where('id_parcela', $plotId)->sum('superficie_hectareas'));
        $this->assertSame(0, DB::connection('mysql')->transactionLevel());
    }

    public function test_plot_cannot_shrink_below_occupied_area(): void
    {
        $plotId = $this->createPlot($this->tokenA);
        foreach ([3, 4, 2] as $area) {
            $this->createSector($plotId, $area)->assertCreated();
        }
        foreach (['PUT', 'PATCH'] as $method) {
            $this->api($method, '/api/plots/update/'.$plotId, ['nombre_parcela' => 'Plot', 'total_hectareas' => 8], $this->tokenA)
                ->assertUnprocessable()->assertJsonPath('errors.total_hectareas.0',
                    'Las hectáreas de la parcela no pueden ser menores que la superficie total de sus sectores.');
            foreach ([9, 12] as $area) {
                $this->api($method, '/api/plots/update/'.$plotId, ['nombre_parcela' => 'Plot', 'total_hectareas' => $area], $this->tokenA)
                    ->assertOk()->assertJsonPath('data.total_hectareas', number_format($area, 2, '.', ''));
            }
        }
    }

    public function test_decimal_capacity_and_rounding_use_the_persisted_value(): void
    {
        $plotId = $this->createPlot($this->tokenA, 1);
        foreach ([0.1, 0.2, 0.7] as $area) {
            $this->createSector($plotId, $area)->assertCreated();
        }
        $this->createSector($plotId, 0.01)->assertUnprocessable();
        $other = $this->createPlot($this->tokenA, 1);
        $this->createSector($other, 0.004)->assertUnprocessable();
        $this->createSector($other, 0.994)->assertCreated()->assertJsonPath('data.superficie_hectareas', '0.99');
        $this->createSector($other, 0.015)->assertUnprocessable();
        $this->createSector($other, 0.014)->assertCreated()->assertJsonPath('data.superficie_hectareas', '0.01');
    }

    public function test_validation_and_jwt_for_all_routes(): void
    {
        $plotId = $this->createPlot($this->tokenA);
        $id = $this->createSector($plotId, 1)->assertCreated()->json('data.id_sector');
        foreach ([['superficie_hectareas' => 0], ['superficie_hectareas' => -5], ['nombre_sector' => ''],
            ['nombre_sector' => str_repeat('x', 151)], ['descripcion' => str_repeat('x', 256)], ['ubicacion' => str_repeat('x', 256)]] as $invalid) {
            foreach (['POST', 'PUT', 'PATCH'] as $method) {
                $uri = $method === 'POST' ? '/api/sectors/create/'.$plotId : '/api/sectors/update/'.$id;
                $this->api($method, $uri, array_merge(['nombre_sector' => 'Valid', 'superficie_hectareas' => 1], $invalid), $this->tokenA)
                    ->assertUnprocessable()->assertJsonStructure(['data' => [array_key_first($invalid)]]);
            }
        }
        $this->api('PUT', '/api/sectors/update/'.$id, ['ubicacion' => 'South'], $this->tokenA)->assertUnprocessable();
        $this->travel(61)->minutes();
        foreach ([null, 'invalid-token', $this->tokenA] as $token) {
            foreach ([['GET', '/api/sectors/list'], ['GET', '/api/sectors/list/'.$id], ['GET', '/api/sectors/by-plot/'.$plotId],
                ['POST', '/api/sectors/create/'.$plotId], ['PUT', '/api/sectors/update/'.$id], ['PATCH', '/api/sectors/update/'.$id], ['DELETE', '/api/sectors/delete/'.$id]] as [$method, $uri]) {
                $this->api($method, $uri, [], $token)->assertUnauthorized();
            }
        }
    }

    public function test_data_whitelist_and_rollback_preserve_original_exception(): void
    {
        $plotId = $this->createPlot($this->tokenA);
        $sector = SectorData::createSector($plotId, ['nombre_sector' => 'Direct', 'superficie_hectareas' => 1,
            'id_parcela' => 999, 'id_sector' => 999, 'fecha_registro' => '1990-01-01'], $this->userA->id_usuario)['data'];
        $result = SectorData::updateSector($sector->id_sector, ['id_parcela' => 999, 'id_sector' => 999,
            'fecha_registro' => '1990-01-01', 'descripcion' => 'Updated'], $this->userA->id_usuario);
        $this->assertSame($plotId, $result['data']->id_parcela);
        $this->assertSame($sector->id_sector, $result['data']->id_sector);
        $this->assertTrue($sector->fecha_registro->equalTo($result['data']->fecha_registro));
        $exception = new RuntimeException('Simulated write failure');
        Sector::created(function () use ($exception) {
            throw $exception;
        });
        try {
            SectorData::createSector($plotId, ['nombre_sector' => 'Rollback', 'superficie_hectareas' => 1], $this->userA->id_usuario);
            $this->fail('Expected original exception');
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        } finally {
            Sector::flushEventListeners();
        }
        $this->assertSame(1, Sector::count());
        $this->assertSame(0, DB::connection('mysql')->transactionLevel());
    }
}
