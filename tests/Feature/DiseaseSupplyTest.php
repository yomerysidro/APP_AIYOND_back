<?php

namespace Tests\Feature;

use App\Http\Data\DiseaseSupply\DiseaseSupplyData;
use App\Models\Disease\Disease;
use App\Models\DiseaseSupply\DiseaseSupply;
use App\Models\Supply\Supply;
use App\Models\User\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class DiseaseSupplyTest extends TestCase
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
        $pdo->exec('CREATE TABLE enfermedad_insumo (id_enfermedad_insumo INTEGER PRIMARY KEY AUTOINCREMENT, id_insumo INTEGER NOT NULL REFERENCES insumos(id_insumo), id_enfermedad INTEGER NOT NULL REFERENCES enfermedades(id_enfermedad), dosis_indicacion TEXT, modalidad_calculo TEXT, referencia_tecnica TEXT, frecuencia_aplicacion VARCHAR(150), organico BOOLEAN NOT NULL DEFAULT 0, dosis_referencial_ha DECIMAL(10,2), UNIQUE(id_enfermedad, id_insumo))');
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

    private function fixture(): array
    {
        $disease = Disease::create(['nombre_enfermedad' => 'Test disease']);
        $supply = Supply::create(['nombre_insumo' => 'Test supply', 'tipo_insumo' => 'Test', 'unidad_medida' => 'kg', 'costo_unitario' => 24.5]);

        return ['id_enfermedad' => $disease->id_enfermedad, 'id_insumo' => $supply->id_insumo,
            'organico' => false, 'dosis_referencial_ha' => 2.5, 'frecuencia_aplicacion' => 'Test frequency'];
    }

    public function test_admin_crud_and_shared_eager_loaded_reads(): void
    {
        $body = $this->fixture();
        $this->api('GET', '/api/disease-supplies/list', $this->adminToken)->assertOk()->assertJsonPath('data', []);
        $created = $this->api('POST', '/api/disease-supplies/create', $this->adminToken, $body + ['id_enfermedad_insumo' => 999])
            ->assertCreated()->assertJsonPath('data.organico', false)->assertJsonPath('data.dosis_referencial_ha', '2.50')
            ->assertJsonPath('data.supply.costo_unitario', '24.50');
        $id = $created->json('data.id_enfermedad_insumo');
        $this->assertNotSame(999, $id);
        foreach ([$this->tokenA, $this->tokenB, $this->adminToken] as $token) {
            $this->api('GET', '/api/disease-supplies/list', $token)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.disease.id_enfermedad', $body['id_enfermedad']);
            $this->api('GET', '/api/disease-supplies/list/'.$id, $token)->assertOk()->assertJsonPath('data.supply.unidad_medida', 'kg');
            $this->api('GET', '/api/diseases/'.$body['id_enfermedad'].'/supplies', $token)->assertOk()
                ->assertJsonPath('data.0.id_insumo', $body['id_insumo'])->assertJsonPath('data.0.organico', false);
        }
        $this->assertSame(1, Disease::findOrFail($body['id_enfermedad'])->diseaseSupplies()->count());
        $this->assertSame(1, Supply::findOrFail($body['id_insumo'])->diseaseSupplies()->count());
        $this->api('PUT', '/api/disease-supplies/update/'.$id, $this->adminToken, array_merge($body, ['organico' => true]))->assertOk()->assertJsonPath('data.organico', true);
        $this->api('PATCH', '/api/disease-supplies/update/'.$id, $this->adminToken, ['frecuencia_aplicacion' => null, 'dosis_referencial_ha' => 1])
            ->assertOk()->assertJsonPath('data.frecuencia_aplicacion', null)->assertJsonPath('data.dosis_referencial_ha', '1.00');
        $this->api('PATCH', '/api/disease-supplies/update/'.$id, $this->adminToken, ['dosis_referencial_ha' => null])
            ->assertOk()->assertJsonPath('data.dosis_referencial_ha', null);
        $this->api('DELETE', '/api/disease-supplies/delete/'.$id, $this->adminToken)->assertOk()->assertJsonPath('data', null);
        $this->assertSame(0, DiseaseSupply::count());
        $this->assertSame(1, Disease::count());
        $this->assertSame(1, Supply::count());
    }

    #[TestWith([null, null])]
    #[TestWith([2.5, '2.50'])]
    public function test_by_disease_preserves_descriptive_instructions_and_price_with_optional_numeric_dose(?float $dose, ?string $expectedDose): void
    {
        $body = array_merge($this->fixture(), [
            'dosis_referencial_ha' => $dose,
            'dosis_indicacion' => 'Seguir las indicaciones de la etiqueta del producto.',
            'modalidad_calculo' => 'segun_etiqueta',
            'referencia_tecnica' => 'Ficha tecnica del producto de prueba',
        ]);
        $created = DiseaseSupplyData::createDiseaseSupply($body);
        $this->assertTrue($created['success']);

        $response = $this->api('GET', '/api/diseases/'.$body['id_enfermedad'].'/supplies', $this->tokenA);

        $response->assertOk()->assertJsonPath('success', true)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_enfermedad', $body['id_enfermedad'])
            ->assertJsonPath('data.0.id_insumo', $body['id_insumo'])
            ->assertJsonPath('data.0.dosis_indicacion', 'Seguir las indicaciones de la etiqueta del producto.')
            ->assertJsonPath('data.0.modalidad_calculo', 'segun_etiqueta')
            ->assertJsonPath('data.0.referencia_tecnica', 'Ficha tecnica del producto de prueba')
            ->assertJsonPath('data.0.frecuencia_aplicacion', 'Test frequency')
            ->assertJsonPath('data.0.dosis_referencial_ha', $expectedDose)
            ->assertJsonPath('data.0.supply.nombre_insumo', 'Test supply')
            ->assertJsonPath('data.0.supply.unidad_medida', 'kg')
            ->assertJsonPath('data.0.supply.costo_unitario', '24.50');
    }

    public function test_duplicate_create_and_update_are_conflicts(): void
    {
        $body = $this->fixture();
        $id = $this->api('POST', '/api/disease-supplies/create', $this->adminToken, $body)->assertCreated()->json('data.id_enfermedad_insumo');
        $this->api('POST', '/api/disease-supplies/create', $this->adminToken, $body)->assertStatus(409)
            ->assertJsonPath('message', 'Este insumo ya está asociado a la enfermedad seleccionada.');
        $otherSupply = Supply::create(['nombre_insumo' => 'Other', 'tipo_insumo' => 'Test', 'unidad_medida' => 'kg']);
        $other = array_merge($body, ['id_insumo' => $otherSupply->id_insumo]);
        $otherId = $this->api('POST', '/api/disease-supplies/create', $this->adminToken, $other)->assertCreated()->json('data.id_enfermedad_insumo');
        $this->api('PUT', '/api/disease-supplies/update/'.$id, $this->adminToken, $body)->assertOk();
        foreach (['PUT', 'PATCH'] as $method) {
            $this->api($method, '/api/disease-supplies/update/'.$id, $this->adminToken, $other)->assertStatus(409);
        }
        $this->assertSame($body['id_insumo'], DiseaseSupply::findOrFail($id)->id_insumo);
        $this->api('GET', '/api/disease-supplies/list', $this->adminToken)->assertJsonPath('data.0.id_enfermedad_insumo', $otherId);
        $newDisease = Disease::create(['nombre_enfermedad' => 'Other disease']);
        $this->api('PATCH', '/api/disease-supplies/update/'.$id, $this->adminToken, [
            'id_enfermedad' => $newDisease->id_enfermedad, 'id_insumo' => $otherSupply->id_insumo,
        ])->assertOk()->assertJsonPath('data.disease.id_enfermedad', $newDisease->id_enfermedad)->assertJsonPath('data.supply.id_insumo', $otherSupply->id_insumo);
    }

    public function test_missing_resources_and_sano_without_supplies(): void
    {
        $body = $this->fixture();
        $sano = Disease::create(['nombre_enfermedad' => 'Sano']);
        $this->api('GET', '/api/diseases/'.$sano->id_enfermedad.'/supplies', $this->tokenA)->assertOk()->assertJsonPath('data', []);
        $this->api('POST', '/api/disease-supplies/create', $this->adminToken, array_merge($body, [
            'id_enfermedad' => $sano->id_enfermedad,
        ]))->assertUnprocessable()->assertJsonPath('message', 'No se pueden asociar insumos a la enfermedad Sano.');
        $this->api('GET', '/api/diseases/999999/supplies', $this->tokenA)->assertNotFound()->assertJsonPath('message', 'Enfermedad no encontrada.');
        $this->api('GET', '/api/disease-supplies/list/999999', $this->adminToken)->assertNotFound();
        $this->api('PUT', '/api/disease-supplies/update/999999', $this->adminToken, $body)->assertNotFound();
        $this->api('PATCH', '/api/disease-supplies/update/999999', $this->adminToken, ['organico' => true])->assertNotFound();
        $this->api('DELETE', '/api/disease-supplies/delete/999999', $this->adminToken)->assertNotFound();
    }

    public function test_validation_rejects_bad_references_and_values(): void
    {
        $body = $this->fixture();
        $id = $this->api('POST', '/api/disease-supplies/create', $this->adminToken, $body)->assertCreated()->json('data.id_enfermedad_insumo');
        foreach ([['id_enfermedad' => 999999], ['id_insumo' => 999999], ['dosis_referencial_ha' => 0], ['dosis_referencial_ha' => -1],
            ['organico' => 'invalid'], ['frecuencia_aplicacion' => str_repeat('x', 151)]] as $invalid) {
            foreach (['POST', 'PUT', 'PATCH'] as $method) {
                $uri = $method === 'POST' ? '/api/disease-supplies/create' : '/api/disease-supplies/update/'.$id;
                $this->api($method, $uri, $this->adminToken, array_merge($body, $invalid))->assertUnprocessable()
                    ->assertJsonStructure(['data' => [array_key_first($invalid)]]);
            }
        }
        $this->api('PUT', '/api/disease-supplies/update/'.$id, $this->adminToken, ['organico' => true])->assertUnprocessable();
        $this->api('POST', '/api/disease-supplies/create', $this->adminToken, [])->assertUnprocessable();
    }

    public function test_all_routes_require_jwt_and_writes_require_admin(): void
    {
        $reads = [['GET', '/api/disease-supplies/list'], ['GET', '/api/disease-supplies/list/1'], ['GET', '/api/diseases/1/supplies']];
        $writes = [['POST', '/api/disease-supplies/create'], ['PUT', '/api/disease-supplies/update/1'],
            ['PATCH', '/api/disease-supplies/update/1'], ['DELETE', '/api/disease-supplies/delete/1']];
        foreach ($writes as [$method, $uri]) {
            $this->api($method, $uri, $this->tokenA)->assertForbidden();
        }
        $this->travel(61)->minutes();
        foreach ([null, 'invalid-token', $this->tokenA] as $token) {
            foreach (array_merge($reads, $writes) as [$method, $uri]) {
                $this->api($method, $uri, $token)->assertUnauthorized();
            }
        }
    }

    public function test_data_checks_references_and_whitelists_fields(): void
    {
        $body = $this->fixture();
        $this->assertFalse(DiseaseSupplyData::createDiseaseSupply(array_merge($body, ['id_enfermedad' => 999999]))['success']);
        $this->assertFalse(DiseaseSupplyData::createDiseaseSupply(array_merge($body, ['id_insumo' => 999999]))['success']);
        $created = DiseaseSupplyData::createDiseaseSupply($body + ['id_enfermedad_insumo' => 999, 'cantidad_recomendada' => 100]);
        $id = $created['data']->id_enfermedad_insumo;
        $this->assertNotSame(999, $id);
        $this->assertFalse(DiseaseSupplyData::updateDiseaseSupply($id, ['id_enfermedad' => 999999])['success']);
        $this->assertFalse(DiseaseSupplyData::updateDiseaseSupply($id, ['id_insumo' => 999999])['success']);
        $updated = DiseaseSupplyData::updateDiseaseSupply($id, ['id_enfermedad_insumo' => 999, 'organico' => true]);
        $this->assertSame($id, $updated['data']->id_enfermedad_insumo);
        $this->assertSame($body['id_enfermedad'], $updated['data']->id_enfermedad);
        $this->assertSame(0, DB::connection('mysql')->transactionLevel());
    }

    public function test_unique_constraint_race_is_controlled_and_unexpected_errors_propagate(): void
    {
        $body = $this->fixture();
        $exception = new UniqueConstraintViolationException('mysql', 'internal SQL', [], new RuntimeException('duplicate'));
        DiseaseSupply::creating(function () use ($exception) {
            throw $exception;
        });
        try {
            $this->api('POST', '/api/disease-supplies/create', $this->adminToken, $body)->assertStatus(409)
                ->assertJsonPath('message', 'Este insumo ya está asociado a la enfermedad seleccionada.');
        } finally {
            DiseaseSupply::flushEventListeners();
        }
        $unexpected = new RuntimeException('Internal failure');
        DiseaseSupply::created(function () use ($unexpected) {
            throw $unexpected;
        });
        try {
            DiseaseSupplyData::createDiseaseSupply($body);
            $this->fail('Expected original exception');
        } catch (RuntimeException $caught) {
            $this->assertSame($unexpected, $caught);
        } finally {
            DiseaseSupply::flushEventListeners();
        }
        $this->assertSame(0, DiseaseSupply::count());
        $this->assertSame(0, DB::connection('mysql')->transactionLevel());
    }

    public function test_eager_loading_has_constant_query_count(): void
    {
        $body = $this->fixture();
        DiseaseSupplyData::createDiseaseSupply($body);
        $otherSupply = Supply::create(['nombre_insumo' => 'Other', 'tipo_insumo' => 'Test', 'unidad_medida' => 'kg']);
        DiseaseSupplyData::createDiseaseSupply(array_merge($body, ['id_insumo' => $otherSupply->id_insumo]));
        $connection = DB::connection('mysql');
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        DiseaseSupplyData::getDiseaseSupplies()['data']->toJson();
        $this->assertCount(3, $connection->getQueryLog());
        $connection->disableQueryLog();
    }
}
