<?php

namespace App\Http\Controllers\Sector;

use App\Http\Controllers\Controller;
use App\Http\Data\Sector\SectorData;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SectorController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $response = SectorData::getSectors(auth('api')->user()->id_usuario);

        return $this->ok($response['data'], $response['message'], 200);
    }

    public function byPlot(int $plotId)
    {
        $response = SectorData::getSectorsByPlot($plotId, auth('api')->user()->id_usuario);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], 404);
    }

    public function show(int $id)
    {
        $response = SectorData::getSectorById($id, auth('api')->user()->id_usuario);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], 404);
    }

    public function destroy(int $id)
    {
        $response = SectorData::deleteSector($id, auth('api')->user()->id_usuario);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], 404);
    }

    public function store(Request $request, int $plotId)
    {
        $validator = Validator::make($request->all(), [
            'nombre_sector' => 'required|string|max:150',
            'superficie_hectareas' => 'required|numeric|gt:0',
            'descripcion' => 'nullable|string|max:255',
            'ubicacion' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $response = SectorData::createSector($plotId, $validator->validated(), auth('api')->user()->id_usuario);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 201)
            : $this->error($response['message'], $response['message'] === 'Parcela no encontrada.' ? 404 : 422);
    }

    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'nombre_sector' => 'required|string|max:150',
            'superficie_hectareas' => 'required|numeric|gt:0',
            'descripcion' => 'nullable|string|max:255',
            'ubicacion' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $response = SectorData::updateSector($id, $validator->validated(), auth('api')->user()->id_usuario);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], $response['message'] === 'Sector no encontrado.' ? 404 : 422);
    }

    public function patch(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'nombre_sector' => 'sometimes|required|string|max:150',
            'superficie_hectareas' => 'sometimes|required|numeric|gt:0',
            'descripcion' => 'sometimes|nullable|string|max:255',
            'ubicacion' => 'sometimes|nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $response = SectorData::updateSector($id, $validator->validated(), auth('api')->user()->id_usuario);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], $response['message'] === 'Sector no encontrado.' ? 404 : 422);
    }
}
