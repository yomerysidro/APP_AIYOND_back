<?php

namespace App\Http\Requests\Supply;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreSupplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_insumo' => ['required', 'string', 'max:150'],
            'tipo_insumo' => ['required', 'string', 'max:100'],
            'unidad_medida' => ['required', 'string', 'max:50'],
            'costo_unitario' => ['nullable', 'numeric', 'min:0'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Datos de validación incorrectos.',
            'data' => $validator->errors(),
        ], 422));
    }
}
