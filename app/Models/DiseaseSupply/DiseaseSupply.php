<?php

namespace App\Models\DiseaseSupply;

use App\Models\Disease\Disease;
use App\Models\Supply\Supply;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiseaseSupply extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'enfermedad_insumo';

    protected $primaryKey = 'id_enfermedad_insumo';

    public $timestamps = false;

    protected $fillable = ['id_insumo', 'id_enfermedad', 'frecuencia_aplicacion', 'organico', 'dosis_referencial_ha'];

    protected function casts(): array
    {
        return ['organico' => 'boolean', 'dosis_referencial_ha' => 'decimal:2'];
    }

    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class, 'id_enfermedad', 'id_enfermedad');
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class, 'id_insumo', 'id_insumo');
    }
}
