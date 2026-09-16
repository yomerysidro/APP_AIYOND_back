<?php

namespace App\Http\Data\Plot;

use App\Models\Plot\Plot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PlotData
{
    public static function getPlots(int $userId): array
    {
        $plots = Plot::where('id_usuario', $userId)->orderByDesc('id_parcela')->get();

        return ['success' => true, 'message' => 'Parcelas obtenidas correctamente.', 'data' => $plots];
    }

    public static function getPlotById(int $id, int $userId): array
    {
        $plot = Plot::where('id_parcela', $id)->where('id_usuario', $userId)->first();

        if (! $plot) {
            return ['success' => false, 'message' => 'Parcela no encontrada.', 'data' => null];
        }

        return ['success' => true, 'message' => 'Parcela obtenida correctamente.', 'data' => $plot];
    }

    public static function createPlot(array $data, int $userId): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            $attributes = array_intersect_key($data, array_flip(['nombre_parcela', 'total_hectareas', 'ubicacion']));
            $attributes['id_usuario'] = $userId;
            $plot = Plot::create($attributes);
            $plot->refresh();
            $connection->commit();

            return ['success' => true, 'message' => 'Parcela registrada correctamente.', 'data' => $plot];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function updatePlot(int $id, array $data, int $userId): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            $plot = Plot::where('id_parcela', $id)->where('id_usuario', $userId)->lockForUpdate()->first();

            if (! $plot) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Parcela no encontrada.', 'data' => null];
            }

            if (array_key_exists('total_hectareas', $data)) {
                $occupiedArea = $plot->sectors()->lockForUpdate()->get()->reduce(
                    fn (string $sum, $sector) => bcadd($sum, $sector->superficie_hectareas, 2), '0.00',
                );
                $newArea = (new Plot(['total_hectareas' => $data['total_hectareas']]))->total_hectareas;

                if (bccomp($newArea, $occupiedArea, 2) < 0) {
                    throw ValidationException::withMessages([
                        'total_hectareas' => 'Las hectáreas de la parcela no pueden ser menores que la superficie total de sus sectores.',
                    ]);
                }

                $data['total_hectareas'] = $newArea;
            }

            $plot->fill(array_intersect_key($data, array_flip(['nombre_parcela', 'total_hectareas', 'ubicacion'])));
            $plot->save();
            $plot->refresh();
            $connection->commit();

            return ['success' => true, 'message' => 'Parcela actualizada correctamente.', 'data' => $plot];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function deletePlot(int $id, int $userId): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            $plot = Plot::where('id_parcela', $id)->where('id_usuario', $userId)->lockForUpdate()->first();

            if (! $plot) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Parcela no encontrada.', 'data' => null];
            }

            $plot->delete();
            $connection->commit();

            return ['success' => true, 'message' => 'Parcela eliminada correctamente.', 'data' => null];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}
