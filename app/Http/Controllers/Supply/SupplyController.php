<?php

namespace App\Http\Controllers\Supply;

use App\Http\Controllers\Controller;
use App\Http\Data\Supply\SupplyData;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre_insumo' => 'required|string|max:150',
            'tipo_insumo' => 'required|string|max:100',
            'unidad_medida' => 'required|string|max:50',
            'costo_unitario' => 'nullable|numeric|min:0',
            'descripcion' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $response = SupplyData::createSupply($validator->validated());

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 201)
            : $this->error($response['message'], 400);
    }

    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'nombre_insumo' => 'required|string|max:150',
            'tipo_insumo' => 'required|string|max:100',
            'unidad_medida' => 'required|string|max:50',
            'costo_unitario' => 'nullable|numeric|min:0',
            'descripcion' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $response = SupplyData::updateSupply($id, $validator->validated());

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], 404);
    }

    public function patch(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'nombre_insumo' => 'sometimes|required|string|max:150',
            'tipo_insumo' => 'sometimes|required|string|max:100',
            'unidad_medida' => 'sometimes|required|string|max:50',
            'costo_unitario' => 'sometimes|nullable|numeric|min:0',
            'descripcion' => 'sometimes|nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $response = SupplyData::updateSupply($id, $validator->validated());

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], 404);
    }

    public function destroy(int $id)
    {
        $response = SupplyData::deleteSupply($id);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], $response['message'] === 'Insumo no encontrado.' ? 404 : 409);
    }
}
