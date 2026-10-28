<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformCooperative extends Model
{
    protected $connection = 'platform';

    protected $table = 'platform_cooperatives';

    protected $fillable = ['name', 'slug', 'database_name', 'connection_config', 'status'];

    protected function casts(): array
    {
        return ['connection_config' => 'encrypted:array'];
    }

    public function accountLinks(): HasMany
    {
        return $this->hasMany(PlatformAccountLink::class, 'cooperative_id');
    }
}
