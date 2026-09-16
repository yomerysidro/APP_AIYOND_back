<?php

namespace App\Models\User;

use App\Models\Plot\Plot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $connection = 'mysql';

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    protected $fillable = [
        'nombre', 'apellido', 'correo', 'password', 'dni',
        'telefono', 'sexo', 'fecha_nacimiento', 'rol',
    ];

    protected $hidden = ['password'];

    public function plots(): HasMany
    {
        return $this->hasMany(Plot::class, 'id_usuario', 'id_usuario');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    protected function casts(): array
    {
        return ['fecha_nacimiento' => 'date'];
    }
}
