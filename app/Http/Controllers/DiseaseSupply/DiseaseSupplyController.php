<?php

namespace App\Http\Controllers\DiseaseSupply;

use App\Http\Controllers\Controller;
use App\Http\Data\DiseaseSupply\DiseaseSupplyData;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DiseaseSupplyController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $response = DiseaseSupplyData::getDiseaseSupplies();

        return $this->ok($response['data'], $response['message'], 200);
    }

    public function show(int $id)
    {
        $response = DiseaseSupplyData::getDiseaseSupplyById($id);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], 404);
    }

    public function byDisease(int $diseaseId)
    {
        $response = DiseaseSupplyData::getSuppliesByDisease($diseaseId);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], 404);
    }

    public function destroy(int $id)
    {
        $response = DiseaseSupplyData::deleteDiseaseSupply($id);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], 404);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_enfermedad' => 'required|integer|exists:mysql.enfermedades,id_enfermedad',
            'id_insumo' => 'required|integer|exists:mysql.insumos,id_insumo',
            'frecuencia_aplicacion' => 'nullable|string|max:150',
            'organico' => 'required|boolean',
            'dosis_referencial_ha' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $response = DiseaseSupplyData::createDiseaseSupply($validator->validated());

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 201)
            : $this->error($response['message'], $response['message'] === 'Este insumo ya está asociado a la enfermedad seleccionada.' ? 409 : 404);
    }

    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'id_enfermedad' => 'required|integer|exists:mysql.enfermedades,id_enfermedad',
            'id_insumo' => 'required|integer|exists:mysql.insumos,id_insumo',
            'frecuencia_aplicacion' => 'nullable|string|max:150',
            'organico' => 'required|boolean',
            'dosis_referencial_ha' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $response = DiseaseSupplyData::updateDiseaseSupply($id, $validator->validated());

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], $response['message'] === 'Este insumo ya está asociado a la enfermedad seleccionada.' ? 409 : 404);
    }

    public function patch(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'id_enfermedad' => 'sometimes|required|integer|exists:mysql.enfermedades,id_enfermedad',
            'id_insumo' => 'sometimes|required|integer|exists:mysql.insumos,id_insumo',
            'frecuencia_aplicacion' => 'sometimes|nullable|string|max:150',
            'organico' => 'sometimes|required|boolean',
            'dosis_referencial_ha' => 'sometimes|nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $response = DiseaseSupplyData::updateDiseaseSupply($id, $validator->validated());

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], $response['message'] === 'Este insumo ya está asociado a la enfermedad seleccionada.' ? 409 : 404);
    }
}
