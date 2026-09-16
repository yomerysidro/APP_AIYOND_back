<?php

namespace App\Http\Data\Sector;

use App\Models\Plot\Plot;
use App\Models\Sector\Sector;
use Illuminate\Support\Facades\DB;
use Throwable;

class SectorData
{
    public static function getSectors(int $userId): array
    {
        $sectors = Sector::whereHas('plot', fn ($query) => $query->where('id_usuario', $userId))
            ->orderByDesc('id_sector')->get();

        return ['success' => true, 'message' => 'Sectores obtenidos correctamente.', 'data' => $sectors];
    }

    public static function getSectorsByPlot(int $plotId, int $userId): array
    {
        $plot = Plot::where('id_parcela', $plotId)->where('id_usuario', $userId)->first();

        if (! $plot) {
            return ['success' => false, 'message' => 'Parcela no encontrada.', 'data' => null];
        }

        return ['success' => true, 'message' => 'Sectores de la parcela obtenidos correctamente.',
            'data' => $plot->sectors()->orderByDesc('id_sector')->get()];
    }

    public static function getSectorById(int $id, int $userId): array
    {
        $sector = Sector::where('id_sector', $id)
            ->whereHas('plot', fn ($query) => $query->where('id_usuario', $userId))->first();

        if (! $sector) {
            return ['success' => false, 'message' => 'Sector no encontrado.', 'data' => null];
        }

        return ['success' => true, 'message' => 'Sector obtenido correctamente.', 'data' => $sector];
    }

    public static function createSector(int $plotId, array $data, int $userId): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            // Always lock the parent first, as PlotData does, then lock its sectors.
            $plot = Plot::where('id_usuario', $userId)
                ->where('id_parcela', $plotId)
                ->lockForUpdate()->first();

            if (! $plot) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Parcela no encontrada.', 'data' => null];
            }

            $attributes = array_intersect_key($data, array_flip(['nombre_sector', 'superficie_hectareas', 'descripcion', 'ubicacion']));
            $sector = new Sector($attributes);
            $sector->id_parcela = $plotId;

            // Use current locked reads and decimal arithmetic, not floating-point sums.
            $occupiedArea = $plot->sectors()->lockForUpdate()->get()->reduce(
                fn (string $sum, Sector $other) => bcadd($sum, $other->superficie_hectareas, 2), '0.00',
            );
            $newArea = $sector->superficie_hectareas;

            if (bccomp($newArea, '0.00', 2) <= 0) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'La superficie debe ser mayor que cero con precisión de dos decimales.', 'data' => null];
            }

            if (bccomp(bcadd($occupiedArea, $newArea, 2), $plot->total_hectareas, 2) > 0) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'La superficie total de los sectores no puede superar las hectáreas de la parcela.', 'data' => null];
            }

            // Persist exactly the two-decimal value that was checked above.
            $sector->superficie_hectareas = $newArea;
            $sector->save();
            $sector->refresh();
            $connection->commit();

            return ['success' => true, 'message' => 'Sector registrado correctamente.', 'data' => $sector];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function updateSector(int $id, array $data, int $userId): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            // Always lock the parent first, as PlotData does, then lock its sectors.
            $plot = Plot::where('id_usuario', $userId)
                ->whereHas('sectors', fn ($query) => $query->where('id_sector', $id))
                ->lockForUpdate()->first();

            if (! $plot) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Sector no encontrado.', 'data' => null];
            }

            $sector = $plot->sectors()->where('id_sector', $id)->lockForUpdate()->first();

            if (! $sector) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Sector no encontrado.', 'data' => null];
            }

            $attributes = array_intersect_key($data, array_flip(['nombre_sector', 'superficie_hectareas', 'descripcion', 'ubicacion']));
            $sector->fill($attributes);

            if (array_key_exists('superficie_hectareas', $attributes)) {
                // Current locked reads share the parent-first order with DiagnosticScanData.
                $exceedsArea = $sector->diagnosticScans()->lockForUpdate()->get()
                    ->contains(fn ($scan) => $scan->area_afectada_ha !== null
                        && bccomp($scan->area_afectada_ha, $sector->superficie_hectareas, 2) > 0);
                if ($exceedsArea) {
                    $connection->rollBack();

                    return ['success' => false, 'message' => 'La superficie del sector no puede ser menor que el área afectada registrada en sus diagnósticos.', 'data' => null];
                }
            }

            // Use current locked reads and decimal arithmetic, not floating-point sums.
            $occupiedArea = $plot->sectors()->where('id_sector', '!=', $id)->lockForUpdate()->get()->reduce(
                fn (string $sum, Sector $other) => bcadd($sum, $other->superficie_hectareas, 2), '0.00',
            );
            $newArea = $sector->superficie_hectareas;

            if (bccomp($newArea, '0.00', 2) <= 0) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'La superficie debe ser mayor que cero con precisión de dos decimales.', 'data' => null];
            }

            if (bccomp(bcadd($occupiedArea, $newArea, 2), $plot->total_hectareas, 2) > 0) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'La superficie total de los sectores no puede superar las hectáreas de la parcela.', 'data' => null];
            }

            // Persist exactly the two-decimal value that was checked above.
            $sector->superficie_hectareas = $newArea;
            $sector->save();
            $sector->refresh();
            $connection->commit();

            return ['success' => true, 'message' => 'Sector actualizado correctamente.', 'data' => $sector];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public static function deleteSector(int $id, int $userId): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            // Always lock the parent first, as PlotData does, then lock its sectors.
            $plot = Plot::where('id_usuario', $userId)
                ->whereHas('sectors', fn ($query) => $query->where('id_sector', $id))
                ->lockForUpdate()->first();

            if (! $plot) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Sector no encontrado.', 'data' => null];
            }

            $sector = $plot->sectors()->where('id_sector', $id)->lockForUpdate()->first();

            if (! $sector) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Sector no encontrado.', 'data' => null];
            }

            $sector->delete();
            $connection->commit();

            return ['success' => true, 'message' => 'Sector eliminado correctamente.', 'data' => null];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}
