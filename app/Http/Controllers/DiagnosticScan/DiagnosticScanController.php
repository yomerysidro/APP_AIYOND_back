<?php

namespace App\Http\Controllers\DiagnosticScan;

use App\Http\Controllers\Controller;
use App\Http\Data\DiagnosticScan\DiagnosticScanData;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DiagnosticScanController extends Controller
{
    use ApiResponse;

    private function respond(array $response, int $status = 200)
    {
        if (! $response['success']) {
            $notFound = in_array($response['message'], ['Diagnóstico no encontrado.', 'Sector no encontrado.', 'Parcela no encontrada.', 'Enfermedad no encontrada.'], true);

            return $this->error($response['message'], $notFound ? 404 : 422);
        }

        return $this->ok($response['data'], $response['message'], $status);
    }

    public function index()
    {
        return $this->respond(DiagnosticScanData::getDiagnosticScans(auth('api')->user()->id_usuario));
    }

    public function show(int $id)
    {
        return $this->respond(DiagnosticScanData::getDiagnosticScanById($id, auth('api')->user()->id_usuario));
    }

    public function bySector(int $sectorId)
    {
        return $this->respond(DiagnosticScanData::getDiagnosticScansBySector($sectorId, auth('api')->user()->id_usuario));
    }

    public function byPlot(int $plotId)
    {
        return $this->respond(DiagnosticScanData::getDiagnosticScansByPlot($plotId, auth('api')->user()->id_usuario));
    }

    public function store(Request $request, int $sectorId)
    {
        $validator = Validator::make($request->all(), [
            'id_enfermedad' => 'required|integer|exists:mysql.enfermedades,id_enfermedad',
            'pct_confianza' => 'required|numeric|between:0,1',
            'area_afectada_ha' => 'nullable|numeric|min:0',
            'fecha_hora' => 'nullable|date',
            'estado_tratamiento' => ['nullable', 'string', Rule::in(['pendiente', 'en_tratamiento', 'completado'])],
            'id_sector' => 'prohibited',
            'id_escaneo_diagnostico' => 'prohibited',
            'id_usuario' => 'prohibited',
            'id_parcela' => 'prohibited',
        ]);
        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        return $this->respond(DiagnosticScanData::createDiagnosticScan($sectorId, $validator->validated(), auth('api')->user()->id_usuario), 201);
    }

    private function editRules(bool $partial): array
    {
        return [
            'area_afectada_ha' => ($partial ? 'sometimes|' : '').'nullable|numeric|min:0',
            'estado_tratamiento' => array_merge($partial ? ['sometimes'] : [], ['required', 'string', Rule::in(['pendiente', 'en_tratamiento', 'completado'])]),
            'id_enfermedad' => 'prohibited',
            'pct_confianza' => 'prohibited',
            'fecha_hora' => 'prohibited',
            'id_sector' => 'prohibited',
            'id_escaneo_diagnostico' => 'prohibited',
            'id_usuario' => 'prohibited',
            'id_parcela' => 'prohibited',
        ];
    }

    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), $this->editRules(false));
        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }
        $data = $validator->validated();
        $data['area_afectada_ha'] = $data['area_afectada_ha'] ?? null;

        return $this->respond(DiagnosticScanData::updateDiagnosticScan($id, $data, auth('api')->user()->id_usuario));
    }

    public function patch(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), $this->editRules(true));
        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        return $this->respond(DiagnosticScanData::updateDiagnosticScan($id, $validator->validated(), auth('api')->user()->id_usuario));
    }

    public function destroy(int $id)
    {
        return $this->respond(DiagnosticScanData::deleteDiagnosticScan($id, auth('api')->user()->id_usuario));
    }
}
