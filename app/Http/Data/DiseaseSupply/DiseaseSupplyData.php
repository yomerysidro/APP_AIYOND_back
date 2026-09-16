<?php

namespace App\Http\Data\DiseaseSupply;

use App\Models\Disease\Disease;
use App\Models\DiseaseSupply\DiseaseSupply;
use App\Models\Supply\Supply;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

class DiseaseSupplyData
{
    public static function getDiseaseSupplies(): array
    {
        $relations = DiseaseSupply::with(['disease', 'supply'])->orderByDesc('id_enfermedad_insumo')->get();

        return ['success' => true, 'message' => 'Relaciones enfermedad-insumo obtenidas correctamente.', 'data' => $relations];
    }

    public static function getDiseaseSupplyById(int $id): array
    {
        $relation = DiseaseSupply::with(['disease', 'supply'])->find($id);

        if (! $relation) {
            return ['success' => false, 'message' => 'Relación enfermedad-insumo no encontrada.', 'data' => null];
        }

        return ['success' => true, 'message' => 'Relación enfermedad-insumo obtenida correctamente.', 'data' => $relation];
    }

    public static function getSuppliesByDisease(int $diseaseId): array
    {
        if (! Disease::whereKey($diseaseId)->exists()) {
            return ['success' => false, 'message' => 'Enfermedad no encontrada.', 'data' => null];
        }

        $relations = DiseaseSupply::with('supply')->where('id_enfermedad', $diseaseId)->orderByDesc('id_enfermedad_insumo')->get();

        return ['success' => true, 'message' => 'Insumos de la enfermedad obtenidos correctamente.', 'data' => $relations];
    }

    public static function createDiseaseSupply(array $data): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            $relation = new DiseaseSupply;
            $relation->fill(array_intersect_key($data, array_flip($relation->getFillable())));

            // Keep both referenced records present until the transaction completes.
            if (! Disease::whereKey($relation->id_enfermedad)->sharedLock()->first()) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Enfermedad no encontrada.', 'data' => null];
            }

            if (! Supply::whereKey($relation->id_insumo)->sharedLock()->first()) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Insumo no encontrado.', 'data' => null];
            }

            $duplicate = DiseaseSupply::where('id_enfermedad', $relation->id_enfermedad)
                ->where('id_insumo', $relation->id_insumo)->exists();

            if ($duplicate) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Este insumo ya está asociado a la enfermedad seleccionada.', 'data' => null];
            }

            $relation->save();
            $relation->refresh()->load(['disease', 'supply']);
            $connection->commit();

            return ['success' => true, 'message' => 'Insumo asociado a la enfermedad correctamente.', 'data' => $relation];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }

            // The database also protects the pair if simultaneous requests pass the precheck.
            if ($exception instanceof UniqueConstraintViolationException) {
                return ['success' => false, 'message' => 'Este insumo ya está asociado a la enfermedad seleccionada.', 'data' => null];
            }

            throw $exception;
        }
    }

    public static function updateDiseaseSupply(int $id, array $data): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            $relation = DiseaseSupply::lockForUpdate()->find($id);

            if (! $relation) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Relación enfermedad-insumo no encontrada.', 'data' => null];
            }
            $relation->fill(array_intersect_key($data, array_flip($relation->getFillable())));

            // Keep both referenced records present until the transaction completes.
            if (! Disease::whereKey($relation->id_enfermedad)->sharedLock()->first()) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Enfermedad no encontrada.', 'data' => null];
            }

            if (! Supply::whereKey($relation->id_insumo)->sharedLock()->first()) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Insumo no encontrado.', 'data' => null];
            }

            $duplicate = DiseaseSupply::where('id_enfermedad', $relation->id_enfermedad)
                ->where('id_insumo', $relation->id_insumo)
                ->where('id_enfermedad_insumo', '!=', $id)->exists();

            if ($duplicate) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Este insumo ya está asociado a la enfermedad seleccionada.', 'data' => null];
            }

            $relation->save();
            $relation->refresh()->load(['disease', 'supply']);
            $connection->commit();

            return ['success' => true, 'message' => 'Relación enfermedad-insumo actualizada correctamente.', 'data' => $relation];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }

            // The database also protects the pair if simultaneous requests pass the precheck.
            if ($exception instanceof UniqueConstraintViolationException) {
                return ['success' => false, 'message' => 'Este insumo ya está asociado a la enfermedad seleccionada.', 'data' => null];
            }

            throw $exception;
        }
    }

    public static function deleteDiseaseSupply(int $id): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            $relation = DiseaseSupply::lockForUpdate()->find($id);

            if (! $relation) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Relación enfermedad-insumo no encontrada.', 'data' => null];
            }
            $relation->delete();
            $connection->commit();

            return ['success' => true, 'message' => 'Relación enfermedad-insumo eliminada correctamente.', 'data' => null];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}
