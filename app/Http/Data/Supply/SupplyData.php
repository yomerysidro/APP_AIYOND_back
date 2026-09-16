<?php

namespace App\Http\Data\Supply;

use App\Models\Supply\Supply;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class SupplyData
{
    public static function getSupplies(): array
    {
        $supplies = Supply::orderBy('nombre_insumo')->get();

        return ['success' => true, 'message' => 'Insumos obtenidos correctamente.', 'data' => $supplies];
    }

    public static function getSupplyById(int $id): array
    {
        $supply = Supply::find($id);

        if (! $supply) {
            return ['success' => false, 'message' => 'Insumo no encontrado.', 'data' => null];
        }

        return ['success' => true, 'message' => 'Insumo obtenido correctamente.', 'data' => $supply];
    }

    public static function createSupply(array $data): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            $supply = new Supply;
            $supply->fill(array_intersect_key($data, array_flip($supply->getFillable())));
            $supply->save();
            $supply->refresh();
            $connection->commit();

            return ['success' => true, 'message' => 'Insumo registrado correctamente.', 'data' => $supply];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function updateSupply(int $id, array $data): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            $supply = Supply::lockForUpdate()->find($id);

            if (! $supply) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Insumo no encontrado.', 'data' => null];
            }
            $supply->fill(array_intersect_key($data, array_flip($supply->getFillable())));
            $supply->save();
            $supply->refresh();
            $connection->commit();

            return ['success' => true, 'message' => 'Insumo actualizado correctamente.', 'data' => $supply];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function deleteSupply(int $id): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            $supply = Supply::lockForUpdate()->find($id);

            if (! $supply) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Insumo no encontrado.', 'data' => null];
            }
            $supply->delete();
            $connection->commit();

            return ['success' => true, 'message' => 'Insumo eliminado correctamente.', 'data' => null];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }

            if ($exception instanceof QueryException) {
                $driverCode = (int) ($exception->errorInfo[1] ?? 0);
                $restricted = ($connection->getDriverName() === 'mysql' && $driverCode === 1451)
                    || ($connection->getDriverName() === 'sqlite' && in_array($driverCode, [19, 787, 1811], true)
                        && str_contains($exception->getMessage(), 'FOREIGN KEY constraint failed'));

                if ($restricted) {
                    return ['success' => false, 'message' => 'El insumo no puede eliminarse porque está siendo utilizado por otros registros.', 'data' => null];
                }
            }

            throw $exception;
        }
    }
}
