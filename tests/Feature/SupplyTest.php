<?php

namespace Tests\Feature;

use App\Models\Disease\Disease;
use App\Models\DiseaseSupply\DiseaseSupply;
use App\Models\Supply\Supply;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SupplyTest extends TestCase
{
    private string $tokenA;

    private string $tokenB;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'jwt.secret' => bin2hex(random_bytes(32)), 'jwt.ttl' => 60, 'cache.default' => 'array']);
        DB::purge('mysql');
        $pdo = DB::connection('mysql')->getPdo();
        $this->assertSame('sqlite', $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        // Disposable test tables only; never connect to the physical MySQL database.
        $pdo->exec('CREATE TABLE usuarios (id_usuario INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, apellido TEXT, correo TEXT UNIQUE, password TEXT, rol TEXT, dni TEXT, telefono TEXT, sexo TEXT, fecha_nacimiento TEXT)');
        $pdo->exec('CREATE TABLE refresh_tokens (id TEXT PRIMARY KEY, family_id TEXT, id_usuario INTEGER, token_hash TEXT UNIQUE, device_hash TEXT, user_agent TEXT, expires_at DATETIME, used_at DATETIME, revoked_at DATETIME, replaced_by TEXT, created_at DATETIME, updated_at DATETIME)');
        $pdo->exec('CREATE TABLE insumos (id_insumo INTEGER PRIMARY KEY AUTOINCREMENT, nombre_insumo VARCHAR(150) NOT NULL, tipo_insumo VARCHAR(100) NOT NULL, unidad_medida VARCHAR(50) NOT NULL, costo_unitario DECIMAL(10,2) CHECK (costo_unitario IS NULL OR costo_unitario >= 0), descripcion VARCHAR(255))');
        $pdo->exec('CREATE TABLE enfermedades (id_enfermedad INTEGER PRIMARY KEY AUTOINCREMENT, nombre_enfermedad TEXT NOT NULL UNIQUE, nombre_cientifico TEXT, descripcion TEXT, sintomas TEXT)');
        $pdo->exec('CREATE TABLE enfermedad_insumo (id_enfermedad_insumo INTEGER PRIMARY KEY AUTOINCREMENT, id_insumo INTEGER NOT NULL REFERENCES insumos(id_insumo) ON DELETE CASCADE, id_enfermedad INTEGER NOT NULL REFERENCES enfermedades(id_enfermedad) ON DELETE CASCADE, frecuencia_aplicacion VARCHAR(150), organico BOOLEAN NOT NULL DEFAULT 0, dosis_referencial_ha DECIMAL(10,2), UNIQUE(id_enfermedad, id_insumo))');
        foreach (['a', 'b', 'admin'] as $name) {
            User::create(['nombre' => $name, 'apellido' => 'Test', 'correo' => $name.'@example.com',
                'password' => Hash::make('12345678'), 'rol' => $name === 'admin' ? 'admin' : 'user']);
        }
        $this->adminToken = $this->api('POST', '/api/auth/login', null, ['correo' => 'admin@example.com', 'password' => '12345678'])->assertOk()->json('data.access_token');
        $this->tokenA = $this->api('POST', '/api/auth/login', null, ['correo' => 'a@example.com', 'password' => '12345678'])
            ->assertOk()->json('data.access_token');
        $this->tokenB = $this->api('POST', '/api/auth/login', null, ['correo' => 'b@example.com', 'password' => '12345678'])
            ->assertOk()->json('data.access_token');
    }

    private function api(string $method, string $uri, ?string $token = null, array $body = []): TestResponse
    {
        foreach (['users', 'supplies'] as $resource) {
            if ($uri === '/api/'.$resource || str_starts_with($uri, '/api/'.$resource.'/')) {
                $suffix = substr($uri, strlen('/api/'.$resource));
                $action = match ($method) {
                    'GET' => 'list',
                    'POST' => 'create',
                    'PUT', 'PATCH' => 'update',
                    'DELETE' => 'delete',
                };
                $uri = '/api/'.$resource.'/'.$action.$suffix;
            }
        }

        app('auth')->forgetGuards();
        app('tymon.jwt')->unsetToken();
        app('tymon.jwt.manager')->setRefreshFlow(false);
        app('tymon.jwt.payload.factory')->setRefreshFlow(false)->customClaims([]);
        app('tymon.jwt.validators.payload')->setRefreshFlow(false);

        return $this->json($method, $uri, $body, ['Authorization' => $token ? 'Bearer '.$token : '']);
    }

    public function test_empty_catalog_is_successful(): void
    {
        $this->api('GET', '/api/supplies', $this->tokenA)->assertOk()->assertExactJson([
            'success' => true, 'message' => 'Insumos obtenidos correctamente.', 'data' => [],
        ]);
    }

    public function test_catalog_is_sorted_shared_and_contains_only_supply_fields(): void
    {
        foreach (['Test D', 'Test B', 'Test C', 'Test A'] as $name) {
            Supply::create(['nombre_insumo' => $name, 'tipo_insumo' => 'Test', 'unidad_medida' => 'kg', 'costo_unitario' => 25.5]);
        }
        $a = $this->api('GET', '/api/supplies', $this->tokenA)->assertOk()->assertJsonCount(4, 'data');
        $b = $this->api('GET', '/api/supplies', $this->tokenB)->assertOk();
        $this->assertSame($a->json(), $b->json());
        $this->assertSame(['Test A', 'Test B', 'Test C', 'Test D'], array_column($a->json('data'), 'nombre_insumo'));
        foreach ($a->json('data') as $supply) {
            $this->assertEqualsCanonicalizing(['id_insumo', 'nombre_insumo', 'tipo_insumo', 'unidad_medida', 'costo_unitario', 'descripcion'], array_keys($supply));
            $this->assertSame('25.50', $supply['costo_unitario']);
            $this->assertNull($supply['descripcion']);
            $this->assertSame('kg', $supply['unidad_medida']);
        }
        $this->assertStringNotContainsString('password', $a->getContent());
    }

    public function test_show_and_missing_id_use_existing_api_response(): void
    {
        $supply = Supply::create(['nombre_insumo' => 'Test supply', 'tipo_insumo' => 'Test', 'unidad_medida' => 'kg']);
        $expected = $supply->refresh()->toArray();
        $this->assertNull($expected['costo_unitario']);
        foreach ([$this->tokenA, $this->tokenB] as $token) {
            $this->api('GET', '/api/supplies/'.$supply->id_insumo, $token)->assertOk()->assertExactJson([
                'success' => true, 'message' => 'Insumo obtenido correctamente.', 'data' => $expected,
            ]);
        }
        // ApiResponse::error() uses an empty array, while Data returns null.
        $this->api('GET', '/api/supplies/999999', $this->tokenA)->assertNotFound()->assertExactJson([
            'success' => false, 'message' => 'Insumo no encontrado.', 'data' => [],
        ]);
    }

    public function test_both_routes_require_valid_unexpired_jwt(): void
    {
        $this->travel(61)->minutes();
        foreach ([null, 'invalid-token', $this->tokenA] as $token) {
            foreach (['/api/supplies', '/api/supplies/1'] as $uri) {
                $this->api('GET', $uri, $token)->assertUnauthorized()->assertJsonPath('code', 401);
            }
        }
    }

    public function test_normal_users_cannot_use_administrative_routes(): void
    {
        foreach ([['GET', '/api/users'], ['GET', '/api/users/1'], ['POST', '/api/users'],
            ['PUT', '/api/users/1'], ['PATCH', '/api/users/1'], ['DELETE', '/api/users/1'],
            ['POST', '/api/supplies'], ['PUT', '/api/supplies/1'], ['PATCH', '/api/supplies/1'], ['DELETE', '/api/supplies/1']] as [$method, $uri]) {
            $this->api($method, $uri)->assertUnauthorized();
            $this->api($method, $uri, $this->tokenA)->assertForbidden()
                ->assertJsonPath('message', 'No tiene permisos para realizar esta acción.');
        }
    }

    public function test_admin_supply_crud_and_validation(): void
    {
        $body = ['nombre_insumo' => 'Test supply', 'tipo_insumo' => 'Test', 'unidad_medida' => 'kg',
            'costo_unitario' => 24.5, 'id_insumo' => 999];
        $created = $this->api('POST', '/api/supplies', $this->adminToken, $body)->assertCreated()->assertJsonPath('data.costo_unitario', '24.50');
        $id = $created->json('data.id_insumo');
        $this->assertNotSame(999, $id);
        $this->api('GET', '/api/supplies', $this->adminToken)->assertOk();
        $this->api('GET', '/api/supplies/'.$id, $this->tokenA)->assertOk();
        $this->api('PATCH', '/api/supplies/'.$id, $this->adminToken, ['costo_unitario' => 0, 'id_insumo' => 999])
            ->assertOk()->assertJsonPath('data.costo_unitario', '0.00')->assertJsonPath('data.id_insumo', $id);
        $this->api('PATCH', '/api/supplies/'.$id, $this->adminToken, ['costo_unitario' => null])->assertOk()->assertJsonPath('data.costo_unitario', null);
        $this->api('PUT', '/api/supplies/'.$id, $this->adminToken, array_merge($body, ['costo_unitario' => 26]))->assertOk()->assertJsonPath('data.costo_unitario', '26.00');
        foreach ([['nombre_insumo' => ''], ['nombre_insumo' => str_repeat('x', 151)],
            ['tipo_insumo' => str_repeat('x', 101)], ['unidad_medida' => str_repeat('x', 51)],
            ['costo_unitario' => -1], ['descripcion' => str_repeat('x', 256)]] as $invalid) {
            foreach (['POST', 'PUT', 'PATCH'] as $method) {
                $this->api($method, '/api/supplies'.($method === 'POST' ? '' : '/'.$id), $this->adminToken, array_merge($body, $invalid))->assertUnprocessable();
            }
        }
        $this->api('PUT', '/api/supplies/'.$id, $this->adminToken, ['costo_unitario' => 1])->assertUnprocessable();
        $this->api('DELETE', '/api/supplies/'.$id, $this->adminToken)->assertOk()->assertJsonPath('data', null);
        foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->api($method, '/api/supplies/'.$id, $this->adminToken, $body)->assertNotFound();
        }
    }

    public function test_foreign_key_restrict_returns_conflict_and_preserves_records(): void
    {
        $supply = Supply::create(['nombre_insumo' => 'Referenced', 'tipo_insumo' => 'Test', 'unidad_medida' => 'kg']);
        $disease = Disease::create(['nombre_enfermedad' => 'Test disease']);
        $relation = DiseaseSupply::create([
            'id_insumo' => $supply->id_insumo,
            'id_enfermedad' => $disease->id_enfermedad,
            'organico' => false,
            'dosis_referencial_ha' => 1,
        ]);

        $this->api('DELETE', '/api/supplies/'.$supply->id_insumo, $this->adminToken)->assertStatus(409)
            ->assertJsonPath('message', 'El insumo no puede eliminarse porque tiene relaciones con enfermedades.');

        $this->assertNotNull($supply->fresh());
        $this->assertNotNull($relation->fresh());
        $this->assertSame(0, DB::connection('mysql')->transactionLevel());
    }

    public function test_admin_user_crud_and_role_validation(): void
    {
        $body = ['nombre' => 'New', 'apellido' => 'Account', 'correo' => 'new@example.com', 'password' => '12345678', 'rol' => 'admin'];
        $created = $this->api('POST', '/api/users', $this->adminToken, $body)->assertCreated()->assertJsonPath('data.rol', 'admin')->assertJsonMissingPath('data.password');
        $id = $created->json('data.id_usuario');
        $this->api('GET', '/api/users', $this->adminToken)->assertOk();
        $this->api('GET', '/api/users/'.$id, $this->adminToken)->assertOk();
        foreach (['root', 'superadmin', 'administrator123'] as $role) {
            foreach (['POST', 'PUT', 'PATCH'] as $method) {
                $input = array_merge($body, ['correo' => $method === 'POST' ? 'unused@example.com' : $body['correo'], 'rol' => $role]);
                $this->api($method, '/api/users'.($method === 'POST' ? '' : '/'.$id), $this->adminToken, $input)
                    ->assertUnprocessable()->assertJsonStructure(['data' => ['rol']]);
            }
        }
        $this->api('POST', '/api/users', $this->adminToken, array_diff_key($body, ['rol' => true]))->assertUnprocessable();
        $this->api('PUT', '/api/users/'.$id, $this->adminToken, array_merge($body, ['rol' => 'user']))->assertOk()->assertJsonPath('data.rol', 'user');
        $this->api('PATCH', '/api/users/'.$id, $this->adminToken, ['rol' => 'admin'])->assertOk()->assertJsonPath('data.rol', 'admin');
        $this->api('DELETE', '/api/users/'.$id, $this->adminToken)->assertOk();
    }

    public function test_public_registration_cannot_grant_admin_role(): void
    {
        $response = $this->api('POST', '/api/auth/register', null, [
            'nombre' => 'Public', 'apellido' => 'Account', 'correo' => 'public@example.com',
            'password' => '12345678', 'rol' => 'admin',
        ])->assertCreated()->assertJsonPath('data.user.correo', 'public@example.com')->assertJsonMissingPath('data.user.password');
        $this->api('GET', '/api/users', $response->json('data.access_token'))->assertForbidden();
        $this->api('GET', '/api/auth/me', $response->json('data.access_token'))->assertOk();
    }

    public function test_role_changes_apply_to_existing_tokens(): void
    {
        $admin = User::where('correo', 'admin@example.com')->firstOrFail();
        $this->api('GET', '/api/users', $this->adminToken)->assertOk();
        $admin->update(['rol' => 'user']);
        $this->api('GET', '/api/users', $this->adminToken)->assertForbidden();
    }
}
