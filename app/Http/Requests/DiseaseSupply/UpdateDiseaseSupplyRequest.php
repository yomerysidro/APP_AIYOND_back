<?php

namespace App\Http\Requests\DiseaseSupply;

use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateDiseaseSupplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $presence = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'id_enfermedad' => [$presence, 'integer', 'exists:mysql.enfermedades,id_enfermedad'],
            'id_insumo' => [$presence, 'integer', 'exists:mysql.insumos,id_insumo'],
            'frecuencia_aplicacion' => ['sometimes', 'nullable', 'string', 'max:150'],
            'organico' => [$presence, 'boolean'],
            'dosis_referencial_ha' => ['sometimes', 'nullable', 'numeric', 'gt:0', $this->finiteNumberRule()],
        ];
    }

    private function finiteNumberRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_numeric($value) && ! is_finite((float) $value)) {
                $fail("El campo {$attribute} debe ser un número finito.");
            }
        };
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
