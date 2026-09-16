<?php

namespace App\Models\Plot;

use App\Models\Sector\Sector;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plot extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'parcelas';

    protected $primaryKey = 'id_parcela';

    public $timestamps = false;

    protected $fillable = ['id_usuario', 'nombre_parcela', 'total_hectareas', 'ubicacion'];

    protected function casts(): array
    {
        return ['total_hectareas' => 'decimal:2', 'fecha_registro' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(Sector::class, 'id_parcela', 'id_parcela');
    }
}
