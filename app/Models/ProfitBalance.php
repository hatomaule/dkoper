<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ProfitBalance extends Model
{
    protected $fillable = ['user_id', 'earned_amount', 'withdrawn_amount', 'transferred_to_modal_amount', 'available_amount'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function transactions(): HasMany { return $this->hasMany(ProfitTransaction::class, 'user_id', 'user_id'); }
}
