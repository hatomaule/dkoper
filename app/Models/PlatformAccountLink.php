<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAccountLink extends Model
{
    protected $connection = 'platform';

    protected $table = 'platform_account_links';

    protected $fillable = [
        'cooperative_id',
        'tenant_user_id',
        'username',
        'phone',
        'borrower_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(PlatformCooperative::class, 'cooperative_id');
    }
}
