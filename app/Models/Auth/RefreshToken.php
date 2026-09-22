<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    protected $connection = 'mysql';

    protected $table = 'refresh_tokens';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['token_hash', 'device_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
