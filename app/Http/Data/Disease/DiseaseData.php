<?php

namespace App\Http\Data\Disease;

use App\Models\Disease\Disease;

class DiseaseData
{
    public static function getDiseases(): array
    {
        $diseases = Disease::orderBy('nombre_enfermedad')->get();

        return ['success' => true, 'message' => 'Enfermedades obtenidas correctamente.', 'data' => $diseases];
    }

    public static function getDiseaseById(int $id): array
    {
        $disease = Disease::find($id);

        if (! $disease) {
            return ['success' => false, 'message' => 'Enfermedad no encontrada.', 'data' => null];
        }

        return ['success' => true, 'message' => 'Enfermedad obtenida correctamente.', 'data' => $disease];
    }
}
