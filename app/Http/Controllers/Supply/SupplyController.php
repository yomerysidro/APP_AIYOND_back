<?php

namespace App\Http\Controllers\Supply;

use App\Http\Controllers\Controller;
use App\Http\Data\Supply\SupplyData;
use App\Http\Requests\Supply\StoreSupplyRequest;
use App\Http\Requests\Supply\UpdateSupplyRequest;
use App\Traits\ApiResponse;

class SupplyController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $response = SupplyData::getSupplies();

        return $this->ok($response['data'], $response['message'], 200);
    }

    public function show(int $id)
    {
        $response = SupplyData::getSupplyById($id);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], 404);
    }

    public function store(StoreSupplyRequest $request)
    {
        $response = SupplyData::createSupply($request->validated());

        return $this->ok($response['data'], $response['message'], 201);
    }

    public function update(UpdateSupplyRequest $request, int $id)
    {
        return $this->respondToUpdate(SupplyData::updateSupply($id, $request->validated()));
    }

    public function patch(UpdateSupplyRequest $request, int $id)
    {
        return $this->respondToUpdate(SupplyData::updateSupply($id, $request->validated()));
    }

    public function destroy(int $id)
    {
        $response = SupplyData::deleteSupply($id);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], $response['message'] === 'Insumo no encontrado.' ? 404 : 409);
    }

    private function respondToUpdate(array $response)
    {
        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], 404);
    }
}
