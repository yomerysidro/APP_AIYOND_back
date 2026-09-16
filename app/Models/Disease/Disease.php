<?php

namespace App\Models\Disease;

use App\Models\DiagnosticScan\DiagnosticScan;
use App\Models\DiseaseSupply\DiseaseSupply;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Disease extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'enfermedades';

    protected $primaryKey = 'id_enfermedad';

    public $timestamps = false;

    protected $fillable = ['nombre_enfermedad', 'nombre_cientifico', 'descripcion', 'sintomas'];

    public function diseaseSupplies(): HasMany
    {
        return $this->hasMany(DiseaseSupply::class, 'id_enfermedad', 'id_enfermedad');
    }

    public function diagnosticScans(): HasMany
    {
        return $this->hasMany(DiagnosticScan::class, 'id_enfermedad', 'id_enfermedad');
    }
}
