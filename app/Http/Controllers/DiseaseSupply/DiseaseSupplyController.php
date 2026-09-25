<?php

namespace App\Http\Controllers\DiseaseSupply;

use App\Http\Controllers\Controller;
use App\Http\Data\DiseaseSupply\DiseaseSupplyData;
use App\Http\Requests\DiseaseSupply\StoreDiseaseSupplyRequest;
use App\Http\Requests\DiseaseSupply\UpdateDiseaseSupplyRequest;
use App\Traits\ApiResponse;

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

    public function store(StoreDiseaseSupplyRequest $request)
    {
        $response = DiseaseSupplyData::createDiseaseSupply($request->validated());

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 201)
            : $this->error($response['message'], $this->errorStatus($response['message']));
    }

    public function update(UpdateDiseaseSupplyRequest $request, int $id)
    {
        return $this->respondToUpdate(DiseaseSupplyData::updateDiseaseSupply($id, $request->validated()));
    }

    public function patch(UpdateDiseaseSupplyRequest $request, int $id)
    {
        return $this->respondToUpdate(DiseaseSupplyData::updateDiseaseSupply($id, $request->validated()));
    }

    private function respondToUpdate(array $response)
    {
        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], $this->errorStatus($response['message']));
    }

    private function errorStatus(string $message): int
    {
        return match ($message) {
            'Este insumo ya está asociado a la enfermedad seleccionada.' => 409,
            'No se pueden asociar insumos a la enfermedad Sano.' => 422,
            'La dosis referencial debe ser un número finito mayor que cero.' => 422,
            default => 404,
        };
    }
}
