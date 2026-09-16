<?php

namespace App\Models\Supply;

use App\Models\DiseaseSupply\DiseaseSupply;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supply extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'insumos';

    protected $primaryKey = 'id_insumo';

    public $timestamps = false;

    protected $fillable = ['nombre_insumo', 'tipo_insumo', 'unidad_medida', 'costo_unitario', 'descripcion'];

    public function diseaseSupplies(): HasMany
    {
        return $this->hasMany(DiseaseSupply::class, 'id_insumo', 'id_insumo');
    }

    protected function casts(): array
    {
        return ['costo_unitario' => 'decimal:2'];
    }
}
