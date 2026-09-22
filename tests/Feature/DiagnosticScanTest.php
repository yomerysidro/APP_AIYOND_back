<?php

namespace Tests\Feature;

use App\Http\Data\DiagnosticScan\DiagnosticScanData;
use App\Models\DiagnosticScan\DiagnosticScan;
use App\Models\Disease\Disease;
use App\Models\Sector\Sector;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DiagnosticScanTest extends TestCase
{
    private array $tokens = [];

    private int $sectorId;

    private int $plotId;

    private int $diseaseId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'jwt.secret' => bin2hex(random_bytes(32)), 'jwt.ttl' => 60, 'cache.default' => 'array']);
        DB::purge('mysql');
        $pdo = DB::connection('mysql')->getPdo();
        $this->assertSame('sqlite', $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        $pdo->exec('CREATE TABLE usuarios (id_usuario INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, apellido TEXT, correo TEXT UNIQUE, password TEXT, rol TEXT)');
        $pdo->exec('CREATE TABLE refresh_tokens (id TEXT PRIMARY KEY, family_id TEXT, id_usuario INTEGER, token_hash TEXT UNIQUE, device_hash TEXT, user_agent TEXT, expires_at DATETIME, used_at DATETIME, revoked_at DATETIME, replaced_by TEXT, created_at DATETIME, updated_at DATETIME)');
        $pdo->exec('CREATE TABLE parcelas (id_parcela INTEGER PRIMARY KEY AUTOINCREMENT, id_usuario INTEGER NOT NULL REFERENCES usuarios(id_usuario), nombre_parcela TEXT, total_hectareas DECIMAL(10,2), ubicacion TEXT, fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE sectores (id_sector INTEGER PRIMARY KEY AUTOINCREMENT, id_parcela INTEGER NOT NULL REFERENCES parcelas(id_parcela) ON DELETE CASCADE, nombre_sector TEXT, superficie_hectareas DECIMAL(10,2), descripcion TEXT, ubicacion TEXT, fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE enfermedades (id_enfermedad INTEGER PRIMARY KEY AUTOINCREMENT, nombre_enfermedad TEXT, nombre_cientifico TEXT, descripcion TEXT, sintomas TEXT)');
        $pdo->exec("CREATE TABLE escaneos_diagnosticos (id_escaneo_diagnostico INTEGER PRIMARY KEY AUTOINCREMENT, id_sector INTEGER NOT NULL REFERENCES sectores(id_sector) ON DELETE CASCADE, id_enfermedad INTEGER NOT NULL REFERENCES enfermedades(id_enfermedad), fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, pct_confianza DECIMAL(6,5) NOT NULL CHECK(pct_confianza BETWEEN 0 AND 1), area_afectada_ha DECIMAL(10,2) CHECK(area_afectada_ha IS NULL OR area_afectada_ha >= 0), estado_tratamiento VARCHAR(50) NOT NULL DEFAULT 'pendiente')");
        foreach (['a', 'b', 'admin'] as $name) {
            User::create(['nombre' => $name, 'apellido' => 'Test', 'correo' => $name.'@example.com', 'password' => Hash::make('12345678'), 'rol' => $name === 'admin' ? 'admin' : 'user']);
            $this->tokens[$name] = $this->api('POST', '/api/auth/login', null, ['correo' => $name.'@example.com', 'password' => '12345678'])->assertOk()->json('data.access_token');
        }
        $this->plotId = $this->api('POST', '/api/plots/create', 'a', ['nombre_parcela' => 'Plot A', 'total_hectareas' => 10])->assertCreated()->json('data.id_parcela');
        $this->sectorId = $this->api('POST', '/api/sectors/create/'.$this->plotId, 'a', ['nombre_sector' => 'Sector A', 'superficie_hectareas' => 3])->assertCreated()->json('data.id_sector');
        $this->diseaseId = Disease::create(['nombre_enfermedad' => 'Moniliasis'])->id_enfermedad;
    }

    private function api(string $method, string $uri, ?string $account = 'a', array $body = [])
    {
        app('auth')->forgetGuards();
        app('tymon.jwt')->unsetToken();
        app('tymon.jwt.manager')->setRefreshFlow(false);
        app('tymon.jwt.payload.factory')->setRefreshFlow(false)->customClaims([]);
        app('tymon.jwt.validators.payload')->setRefreshFlow(false);
        $token = $account === null ? '' : ($this->tokens[$account] ?? $account);

        return $this->json($method, $uri, $body, ['Authorization' => $token ? 'Bearer '.$token : '']);
    }

    private function createScan(array $body = [], ?int $sectorId = null)
    {
        return $this->api('POST', '/api/diagnostic-scans/create/'.($sectorId ?? $this->sectorId), 'a', array_merge([
            'id_enfermedad' => $this->diseaseId, 'pct_confianza' => 0.92450,
        ], $body));
    }

    public function test_crud_defaults_dates_relations_and_editable_fields(): void
    {
        $created = $this->createScan()->assertCreated()->assertJsonPath('data.id_sector', $this->sectorId)
            ->assertJsonPath('data.id_enfermedad', $this->diseaseId)->assertJsonPath('data.pct_confianza', '0.92450')
            ->assertJsonPath('data.estado_tratamiento', 'pendiente')->assertJsonPath('data.area_afectada_ha', null);
        $id = $created->json('data.id_escaneo_diagnostico');
        $this->assertNotNull($created->json('data.fecha_hora'));
        $this->assertLessThan(10, abs(time() - strtotime(DiagnosticScan::find($id)->getRawOriginal('fecha_hora').' UTC')));
        $this->assertStringNotContainsString('password', $created->getContent());
        $this->api('GET', '/api/diagnostic-scans/list/'.$id)->assertOk()->assertJsonPath('data.disease.nombre_enfermedad', 'Moniliasis')->assertJsonPath('data.sector.nombre_sector', 'Sector A');
        foreach (['en_tratamiento', 'completado'] as $state) {
            $this->api('PATCH', '/api/diagnostic-scans/update/'.$id, 'a', ['estado_tratamiento' => $state])->assertOk()->assertJsonPath('data.estado_tratamiento', $state);
        }
        $this->api('PUT', '/api/diagnostic-scans/update/'.$id, 'a', ['area_afectada_ha' => 1.5, 'estado_tratamiento' => 'pendiente'])->assertOk()->assertJsonPath('data.area_afectada_ha', '1.50');
        $this->api('PATCH', '/api/diagnostic-scans/update/'.$id, 'a', ['area_afectada_ha' => null])->assertOk()->assertJsonPath('data.area_afectada_ha', null);
        $this->api('DELETE', '/api/diagnostic-scans/delete/'.$id)->assertOk()->assertJsonPath('data', null);
        $this->api('GET', '/api/diagnostic-scans/list/'.$id)->assertNotFound();
        $this->assertSame(1, Sector::count());
        $this->assertSame(1, Disease::count());
        $dated = $this->createScan(['fecha_hora' => '2026-01-02 10:30:00', 'estado_tratamiento' => null])->assertCreated();
        $this->assertSame('2026-01-02 10:30:00', DiagnosticScan::find($dated->json('data.id_escaneo_diagnostico'))->getRawOriginal('fecha_hora'));
        $this->createScan(['fecha_hora' => null])->assertCreated()->assertJsonPath('data.estado_tratamiento', 'pendiente');
    }

    public function test_other_users_and_admin_cannot_access_private_scans(): void
    {
        $id = $this->createScan()->assertCreated()->json('data.id_escaneo_diagnostico');
        foreach (['b', 'admin'] as $account) {
            $this->api('GET', '/api/diagnostic-scans/list', $account)->assertOk()->assertJsonPath('data', []);
            foreach ([
                ['GET', 'list/'.$id, []],
                ['GET', 'by-sector/'.$this->sectorId, []],
                ['GET', 'by-plot/'.$this->plotId, []],
                ['POST', 'create/'.$this->sectorId, ['id_enfermedad' => $this->diseaseId, 'pct_confianza' => 0.9]],
                ['PUT', 'update/'.$id, ['estado_tratamiento' => 'completado']],
                ['PATCH', 'update/'.$id, ['area_afectada_ha' => 1]],
                ['DELETE', 'delete/'.$id, []],
            ] as [$method, $path, $body]) {
                $this->api($method, '/api/diagnostic-scans/'.$path, $account, $body)->assertNotFound();
            }
        }
        $this->assertSame(1, DiagnosticScan::count());
        $this->assertSame('pendiente', DiagnosticScan::find($id)->estado_tratamiento);
        $plot = $this->api('POST', '/api/plots/create', 'admin', ['nombre_parcela' => 'Admin', 'total_hectareas' => 5])->assertCreated()->json('data.id_parcela');
        $sector = $this->api('POST', '/api/sectors/create/'.$plot, 'admin', ['nombre_sector' => 'Admin', 'superficie_hectareas' => 2])->assertCreated()->json('data.id_sector');
        $adminId = $this->api('POST', '/api/diagnostic-scans/create/'.$sector, 'admin', ['id_enfermedad' => $this->diseaseId, 'pct_confianza' => 1])->assertCreated()->json('data.id_escaneo_diagnostico');
        $this->api('GET', '/api/diagnostic-scans/list/'.$adminId, 'a')->assertNotFound();
        $this->api('PATCH', '/api/diagnostic-scans/update/'.$adminId, 'admin', ['estado_tratamiento' => 'completado'])->assertOk();
        $this->api('DELETE', '/api/diagnostic-scans/delete/'.$adminId, 'admin')->assertOk();
    }

    public function test_confidence_area_and_validation_boundaries(): void
    {
        foreach ([0, 1, 0.92450] as $confidence) {
            $this->createScan(['pct_confianza' => $confidence])->assertCreated();
        }
        foreach ([-0.1, 1.1, 92] as $confidence) {
            $this->createScan(['pct_confianza' => $confidence])->assertUnprocessable();
        }
        $this->api('PATCH', '/api/sectors/update/'.$this->sectorId, 'a', ['superficie_hectareas' => 2])->assertOk();
        foreach ([null, 0, 1.5, 2] as $area) {
            $this->createScan(['area_afectada_ha' => $area])->assertCreated();
        }
        foreach ([-1, 2.1] as $area) {
            $this->createScan(['area_afectada_ha' => $area])->assertUnprocessable();
        }
        foreach ([
            ['id_enfermedad' => 999999], ['fecha_hora' => 'invalid'],
            ['estado_tratamiento' => 'cualquier_cosa'], ['id_sector' => 999],
            ['id_usuario' => 999], ['id_parcela' => 999], ['id_escaneo_diagnostico' => 99],
        ] as $invalid) {
            $this->createScan($invalid)->assertUnprocessable();
        }
        $id = $this->createScan()->assertCreated()->json('data.id_escaneo_diagnostico');
        foreach (['PUT', 'PATCH'] as $method) {
            $this->api($method, '/api/diagnostic-scans/update/'.$id, 'a', ['estado_tratamiento' => 'pendiente', 'area_afectada_ha' => 2.1])->assertUnprocessable();
            $this->api($method, '/api/diagnostic-scans/update/'.$id, 'a', ['estado_tratamiento' => 'invalid'])->assertUnprocessable();
        }
        $this->api('PUT', '/api/diagnostic-scans/update/'.$id, 'a', [])->assertUnprocessable();
        $this->api('GET', '/api/diagnostic-scans/by-sector/999999')->assertNotFound();
        $this->api('GET', '/api/diagnostic-scans/by-plot/999999')->assertNotFound();
        $this->createScan([], 999999)->assertNotFound();
    }

    public function test_original_ai_fields_are_immutable_in_controller_and_data(): void
    {
        $id = $this->createScan(['fecha_hora' => '2026-01-01 10:00:00'])->assertCreated()->json('data.id_escaneo_diagnostico');
        $original = DiagnosticScan::find($id)->getAttributes();
        foreach (['PUT', 'PATCH'] as $method) {
            foreach (['id_enfermedad' => 999, 'pct_confianza' => 0.5, 'fecha_hora' => '2026-01-02', 'id_sector' => 999, 'id_escaneo_diagnostico' => 999] as $field => $value) {
                $this->api($method, '/api/diagnostic-scans/update/'.$id, 'a', [$field => $value, 'estado_tratamiento' => 'pendiente'])->assertUnprocessable();
            }
        }
        $result = DiagnosticScanData::updateDiagnosticScan($id, ['id_sector' => 999, 'id_enfermedad' => 999, 'pct_confianza' => 0.5, 'fecha_hora' => '2026-02-01'], 1);
        $this->assertTrue($result['success']);
        $this->assertSame($original, DiagnosticScan::find($id)->getAttributes());
    }

    public function test_history_filters_and_deterministic_order(): void
    {
        foreach (['list', 'by-sector/'.$this->sectorId, 'by-plot/'.$this->plotId] as $path) {
            $this->api('GET', '/api/diagnostic-scans/'.$path)->assertOk()->assertJsonPath('data', []);
        }
        $secondSector = $this->api('POST', '/api/sectors/create/'.$this->plotId, 'a', ['nombre_sector' => 'Second', 'superficie_hectareas' => 2])->assertCreated()->json('data.id_sector');
        $otherPlot = $this->api('POST', '/api/plots/create', 'a', ['nombre_parcela' => 'Other', 'total_hectareas' => 2])->assertCreated()->json('data.id_parcela');
        $otherSector = $this->api('POST', '/api/sectors/create/'.$otherPlot, 'a', ['nombre_sector' => 'Other', 'superficie_hectareas' => 2])->assertCreated()->json('data.id_sector');
        $old = $this->createScan(['fecha_hora' => '2026-01-01 10:00:00'])->assertCreated()->json('data.id_escaneo_diagnostico');
        $new = $this->createScan(['fecha_hora' => '2026-01-02 10:00:00'])->assertCreated()->json('data.id_escaneo_diagnostico');
        $tie = $this->createScan(['fecha_hora' => '2026-01-02 10:00:00'], $secondSector)->assertCreated()->json('data.id_escaneo_diagnostico');
        $outside = $this->createScan(['fecha_hora' => '2026-01-03 10:00:00'], $otherSector)->assertCreated()->json('data.id_escaneo_diagnostico');
        foreach (['list' => [$outside, $tie, $new, $old], 'by-sector/'.$this->sectorId => [$new, $old], 'by-plot/'.$this->plotId => [$tie, $new, $old]] as $path => $expected) {
            $response = $this->api('GET', '/api/diagnostic-scans/'.$path)->assertOk();
            $this->assertSame($expected, array_column($response->json('data'), 'id_escaneo_diagnostico'));
        }
    }

    public function test_sector_reduction_uses_maximum_not_sum_of_scan_areas(): void
    {
        $this->createScan(['area_afectada_ha' => 2.5])->assertCreated();
        $this->createScan(['area_afectada_ha' => 2])->assertCreated();
        $this->createScan(['area_afectada_ha' => null])->assertCreated();
        foreach (['PUT', 'PATCH'] as $method) {
            $this->api($method, '/api/sectors/update/'.$this->sectorId, 'a', ['nombre_sector' => 'Sector A', 'superficie_hectareas' => 2])->assertUnprocessable()
                ->assertJsonPath('message', 'La superficie del sector no puede ser menor que el área afectada registrada en sus diagnósticos.');
            foreach ([2.5, 3] as $area) {
                $this->api($method, '/api/sectors/update/'.$this->sectorId, 'a', ['nombre_sector' => 'Sector A', 'superficie_hectareas' => $area])->assertOk();
            }
        }
    }

    public function test_all_routes_require_valid_jwt_and_numeric_ids(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'api/diagnostic-scans/'));
        $this->assertCount(8, $routes);
        foreach ($routes as $route) {
            $this->assertContains('auth:api', $route->gatherMiddleware());
            $this->assertNotContains('role:admin', $route->gatherMiddleware());
            $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
            foreach ([null, 'invalid-token'] as $token) {
                $this->api($route->methods()[0], $uri, $token)->assertUnauthorized();
            }
        }
        $this->travel(61)->minutes();
        foreach ($routes as $route) {
            $this->api($route->methods()[0], '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri()))->assertUnauthorized();
        }
    }

    public function test_data_checks_disease_and_rolls_back_unexpected_failures(): void
    {
        $this->assertFalse(DiagnosticScanData::createDiagnosticScan($this->sectorId, ['id_enfermedad' => 99999, 'pct_confianza' => 1], 1)['success']);
        DiagnosticScan::created(function () {
            throw new \RuntimeException('Test rollback');
        });
        try {
            DiagnosticScanData::createDiagnosticScan($this->sectorId, ['id_enfermedad' => $this->diseaseId, 'pct_confianza' => 1], 1);
            $this->fail('Expected original exception');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Test rollback', $exception->getMessage());
        } finally {
            DiagnosticScan::flushEventListeners();
        }
        $this->assertSame(0, DiagnosticScan::count());
        $this->assertSame(0, DB::connection('mysql')->transactionLevel());
    }
}
