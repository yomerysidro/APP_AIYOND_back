<?php

namespace App\Models\Sector;

use App\Models\DiagnosticScan\DiagnosticScan;
use App\Models\Plot\Plot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'sectores';

    protected $primaryKey = 'id_sector';

    public $timestamps = false;

    protected $fillable = ['id_parcela', 'nombre_sector', 'superficie_hectareas', 'descripcion', 'ubicacion'];

    protected function casts(): array
    {
        return ['superficie_hectareas' => 'decimal:2', 'fecha_registro' => 'datetime'];
    }

    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class, 'id_parcela', 'id_parcela');
    }

    public function diagnosticScans(): HasMany
    {
        return $this->hasMany(DiagnosticScan::class, 'id_sector', 'id_sector');
    }
}
