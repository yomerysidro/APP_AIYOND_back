<?php

namespace Tests\Feature;

use App\Http\Controllers\Disease\DiseaseController;
use App\Models\Disease\Disease;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DiseaseTest extends TestCase
{
    private string $tokenA;

    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ], 'jwt.secret' => bin2hex(random_bytes(32)), 'jwt.ttl' => 60, 'cache.default' => 'array']);
        DB::purge('mysql');
        $pdo = DB::connection('mysql')->getPdo();
        $this->assertSame('sqlite', $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        // Disposable test tables only; never connect to the physical MySQL database.
        $pdo->exec('CREATE TABLE usuarios (id_usuario INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, apellido TEXT, correo TEXT UNIQUE, password TEXT, rol TEXT)');
        $pdo->exec('CREATE TABLE refresh_tokens (id TEXT PRIMARY KEY, family_id TEXT, id_usuario INTEGER, token_hash TEXT UNIQUE, device_hash TEXT, user_agent TEXT, expires_at DATETIME, used_at DATETIME, revoked_at DATETIME, replaced_by TEXT, created_at DATETIME, updated_at DATETIME)');
        $pdo->exec('CREATE TABLE enfermedades (id_enfermedad INTEGER PRIMARY KEY AUTOINCREMENT, nombre_enfermedad VARCHAR(100) NOT NULL UNIQUE, nombre_cientifico VARCHAR(150), descripcion TEXT, sintomas TEXT)');
        foreach (['a', 'b'] as $name) {
            User::create(['nombre' => $name, 'apellido' => 'Test', 'correo' => $name.'@example.com',
                'password' => Hash::make('12345678'), 'rol' => 'user']);
        }
        $this->tokenA = $this->api('POST', '/api/auth/login', null, ['correo' => 'a@example.com', 'password' => '12345678'])
            ->assertOk()->json('data.access_token');
        $this->tokenB = $this->api('POST', '/api/auth/login', null, ['correo' => 'b@example.com', 'password' => '12345678'])
            ->assertOk()->json('data.access_token');
    }

    private function api(string $method, string $uri, ?string $token = null, array $body = []): TestResponse
    {
        app('auth')->forgetGuards();
        app('tymon.jwt')->unsetToken();
        app('tymon.jwt.manager')->setRefreshFlow(false);
        app('tymon.jwt.payload.factory')->setRefreshFlow(false)->customClaims([]);
        app('tymon.jwt.validators.payload')->setRefreshFlow(false);

        return $this->json($method, $uri, $body, ['Authorization' => $token ? 'Bearer '.$token : '']);
    }

    public function test_empty_catalog_is_successful(): void
    {
        $this->api('GET', '/api/diseases/list', $this->tokenA)->assertOk()->assertExactJson([
            'success' => true, 'message' => 'Enfermedades obtenidas correctamente.', 'data' => [],
        ]);
    }

    public function test_catalog_is_sorted_shared_and_contains_only_disease_fields(): void
    {
        foreach (['Sano', 'Moniliasis', 'Pudrición negra', 'Escoba de bruja'] as $name) {
            Disease::create(['nombre_enfermedad' => $name]);
        }
        $a = $this->api('GET', '/api/diseases/list', $this->tokenA)->assertOk()->assertJsonCount(4, 'data');
        $b = $this->api('GET', '/api/diseases/list', $this->tokenB)->assertOk();
        $this->assertSame($a->json(), $b->json());
        $this->assertSame(['Escoba de bruja', 'Moniliasis', 'Pudrición negra', 'Sano'], array_column($a->json('data'), 'nombre_enfermedad'));
        foreach ($a->json('data') as $disease) {
            $this->assertEqualsCanonicalizing(['id_enfermedad', 'nombre_enfermedad', 'nombre_cientifico', 'descripcion', 'sintomas'], array_keys($disease));
            $this->assertNull($disease['nombre_cientifico']);
            $this->assertNull($disease['descripcion']);
            $this->assertNull($disease['sintomas']);
        }
        $this->assertStringNotContainsString('password', $a->getContent());
    }

    public function test_show_and_missing_id_use_existing_api_response(): void
    {
        $disease = Disease::create(['nombre_enfermedad' => 'Sano']);
        $expected = $disease->refresh()->toArray();
        foreach ([$this->tokenA, $this->tokenB] as $token) {
            $this->api('GET', '/api/diseases/list/'.$disease->id_enfermedad, $token)->assertOk()->assertExactJson([
                'success' => true, 'message' => 'Enfermedad obtenida correctamente.', 'data' => $expected,
            ]);
        }
        // ApiResponse::error() uses an empty array, while Data returns null.
        $this->api('GET', '/api/diseases/list/999999', $this->tokenA)->assertNotFound()->assertExactJson([
            'success' => false, 'message' => 'Enfermedad no encontrada.', 'data' => [],
        ]);
    }

    public function test_both_routes_require_valid_unexpired_jwt(): void
    {
        $this->travel(61)->minutes();
        foreach ([null, 'invalid-token', $this->tokenA] as $token) {
            foreach (['/api/diseases/list', '/api/diseases/list/1'] as $uri) {
                $this->api('GET', $uri, $token)->assertUnauthorized()->assertJsonPath('code', 401);
            }
        }
    }

    public function test_no_write_routes_are_exposed(): void
    {
        $disease = Disease::create(['nombre_enfermedad' => 'Sano']);
        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route) => $route->getControllerClass() === DiseaseController::class);
        $this->assertCount(2, $routes);
        foreach ($routes as $route) {
            $this->assertSame(['GET', 'HEAD'], $route->methods());
            $this->assertContains('auth:api', $route->gatherMiddleware());
        }
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            foreach (['/api/diseases/list', '/api/diseases/list/'.$disease->id_enfermedad] as $uri) {
                $this->api($method, $uri, $this->tokenA, ['nombre_enfermedad' => 'Changed'])->assertStatus(405);
            }
        }
        $this->assertSame(1, Disease::count());
        $this->assertSame('Sano', $disease->refresh()->nombre_enfermedad);
    }
}
