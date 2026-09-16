<?php

namespace App\Http\Data\DiagnosticScan;

use App\Models\DiagnosticScan\DiagnosticScan;
use App\Models\Disease\Disease;
use App\Models\Plot\Plot;
use App\Models\Sector\Sector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class DiagnosticScanData
{
    private static function ownedScans(int $userId): Builder
    {
        return DiagnosticScan::whereHas('sector.plot', fn ($query) => $query->where('id_usuario', $userId));
    }

    private static function history(Builder $query): array
    {
        return ['success' => true, 'message' => 'Diagnósticos obtenidos correctamente.',
            'data' => $query->with(['sector', 'disease'])->orderByDesc('fecha_hora')->orderByDesc('id_escaneo_diagnostico')->get()];
    }

    private static function failure(string $message): array
    {
        return ['success' => false, 'message' => $message, 'data' => null];
    }

    public static function getDiagnosticScans(int $userId): array
    {
        return self::history(self::ownedScans($userId));
    }

    public static function getDiagnosticScanById(int $id, int $userId): array
    {
        $scan = self::ownedScans($userId)->with(['sector', 'disease'])->whereKey($id)->first();

        return $scan
            ? ['success' => true, 'message' => 'Diagnóstico obtenido correctamente.', 'data' => $scan]
            : self::failure('Diagnóstico no encontrado.');
    }

    public static function getDiagnosticScansBySector(int $sectorId, int $userId): array
    {
        if (! Sector::whereKey($sectorId)->whereHas('plot', fn ($query) => $query->where('id_usuario', $userId))->exists()) {
            return self::failure('Sector no encontrado.');
        }

        return self::history(self::ownedScans($userId)->where('id_sector', $sectorId));
    }

    public static function getDiagnosticScansByPlot(int $plotId, int $userId): array
    {
        if (! Plot::whereKey($plotId)->where('id_usuario', $userId)->exists()) {
            return self::failure('Parcela no encontrada.');
        }

        return self::history(self::ownedScans($userId)->whereHas('sector', fn ($query) => $query->where('id_parcela', $plotId)));
    }

    public static function createDiagnosticScan(int $sectorId, array $data, int $userId): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;
        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            // Keep the same parent-first lock order as SectorData.
            $plot = Plot::where('id_usuario', $userId)
                ->whereHas('sectors', fn ($query) => $query->where('id_sector', $sectorId))
                ->lockForUpdate()->first();
            $sector = $plot?->sectors()->whereKey($sectorId)->lockForUpdate()->first();
            if (! $sector) {
                $connection->rollBack();

                return self::failure('Sector no encontrado.');
            }
            if (! Disease::whereKey($data['id_enfermedad'])->sharedLock()->first()) {
                $connection->rollBack();

                return self::failure('Enfermedad no encontrada.');
            }
            $attributes = array_intersect_key($data, array_flip(['id_enfermedad', 'pct_confianza', 'area_afectada_ha', 'fecha_hora', 'estado_tratamiento']));
            if (! isset($attributes['fecha_hora'])) {
                unset($attributes['fecha_hora']);
            }
            $attributes['estado_tratamiento'] = $attributes['estado_tratamiento'] ?? 'pendiente';
            $scan = new DiagnosticScan($attributes);
            $scan->id_sector = $sectorId;
            if (self::areaExceedsSector($scan, $sector)) {
                $connection->rollBack();

                return self::failure('El área afectada no puede superar la superficie del sector.');
            }
            // Persist the same decimal values used for comparison.
            $scan->area_afectada_ha = $scan->area_afectada_ha;
            $scan->pct_confianza = $scan->pct_confianza;
            $scan->save();
            $scan->refresh()->load(['sector', 'disease']);
            $connection->commit();

            return ['success' => true, 'message' => 'Diagnóstico registrado correctamente.', 'data' => $scan];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    private static function areaExceedsSector(DiagnosticScan $scan, Sector $sector): bool
    {
        return $scan->area_afectada_ha !== null
            && bccomp($scan->area_afectada_ha, $sector->superficie_hectareas, 2) > 0;
    }

    private static function lockedScan(int $id, int $userId): ?DiagnosticScan
    {
        $plot = Plot::where('id_usuario', $userId)
            ->whereHas('sectors.diagnosticScans', fn ($query) => $query->where('id_escaneo_diagnostico', $id))
            ->lockForUpdate()->first();
        if (! $plot) {
            return null;
        }
        $sector = $plot->sectors()
            ->whereHas('diagnosticScans', fn ($query) => $query->where('id_escaneo_diagnostico', $id))
            ->lockForUpdate()->first();
        if (! $sector) {
            return null;
        }
        $scan = $sector->diagnosticScans()->whereKey($id)->lockForUpdate()->first();

        return $scan?->setRelation('sector', $sector);
    }

    public static function updateDiagnosticScan(int $id, array $data, int $userId): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;
        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            $scan = self::lockedScan($id, $userId);
            if (! $scan) {
                $connection->rollBack();

                return self::failure('Diagnóstico no encontrado.');
            }
            $scan->fill(array_intersect_key($data, array_flip(['area_afectada_ha', 'estado_tratamiento'])));
            if (self::areaExceedsSector($scan, $scan->sector)) {
                $connection->rollBack();

                return self::failure('El área afectada no puede superar la superficie del sector.');
            }
            $scan->area_afectada_ha = $scan->area_afectada_ha;
            $scan->save();
            $scan->refresh()->load(['sector', 'disease']);
            $connection->commit();

            return ['success' => true, 'message' => 'Diagnóstico actualizado correctamente.', 'data' => $scan];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public static function deleteDiagnosticScan(int $id, int $userId): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;
        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            $scan = self::lockedScan($id, $userId);
            if (! $scan) {
                $connection->rollBack();

                return self::failure('Diagnóstico no encontrado.');
            }
            $scan->delete();
            $connection->commit();

            return ['success' => true, 'message' => 'Diagnóstico eliminado correctamente.', 'data' => null];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }
}
