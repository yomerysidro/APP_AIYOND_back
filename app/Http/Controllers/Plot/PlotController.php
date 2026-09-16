<?php

namespace App\Http\Controllers\Plot;

use App\Http\Controllers\Controller;
use App\Http\Data\Plot\PlotData;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PlotController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $response = PlotData::getPlots(auth('api')->user()->id_usuario);

        return $this->ok($response['data'], $response['message'], 200);
    }

    public function show(int $id)
    {
        $response = PlotData::getPlotById($id, auth('api')->user()->id_usuario);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], 404);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre_parcela' => 'required|string|max:150',
            'total_hectareas' => 'required|numeric|gt:0',
            'ubicacion' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $response = PlotData::createPlot($validator->validated(), auth('api')->user()->id_usuario);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 201)
            : $this->error($response['message'], 400);
    }

    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'nombre_parcela' => 'required|string|max:150',
            'total_hectareas' => 'required|numeric|gt:0',
            'ubicacion' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $response = PlotData::updatePlot($id, $validator->validated(), auth('api')->user()->id_usuario);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], 404);
    }

    public function patch(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'nombre_parcela' => 'sometimes|required|string|max:150',
            'total_hectareas' => 'sometimes|required|numeric|gt:0',
            'ubicacion' => 'sometimes|nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $response = PlotData::updatePlot($id, $validator->validated(), auth('api')->user()->id_usuario);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], 404);
    }

    public function destroy(int $id)
    {
        $response = PlotData::deletePlot($id, auth('api')->user()->id_usuario);

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], 404);
    }
}
