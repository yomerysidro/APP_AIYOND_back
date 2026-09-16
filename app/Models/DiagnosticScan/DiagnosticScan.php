<?php

namespace App\Models\DiagnosticScan;

use App\Models\Disease\Disease;
use App\Models\Sector\Sector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticScan extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'escaneos_diagnosticos';

    protected $primaryKey = 'id_escaneo_diagnostico';

    public $timestamps = false;

    protected $fillable = ['id_sector', 'id_enfermedad', 'fecha_hora', 'pct_confianza', 'area_afectada_ha', 'estado_tratamiento'];

    protected function casts(): array
    {
        return ['fecha_hora' => 'datetime', 'pct_confianza' => 'decimal:5', 'area_afectada_ha' => 'decimal:2'];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'id_sector', 'id_sector');
    }

    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class, 'id_enfermedad', 'id_enfermedad');
    }
}
