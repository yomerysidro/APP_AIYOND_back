<?php

namespace App\Http\Requests\Supply;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSupplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $presence = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'nombre_insumo' => [$presence, 'string', 'max:150'],
            'tipo_insumo' => [$presence, 'string', 'max:100'],
            'unidad_medida' => [$presence, 'string', 'max:50'],
            'costo_unitario' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:255'],
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
