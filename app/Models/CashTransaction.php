<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class CashTransaction extends Model
{
    protected $fillable = ['created_by', 'type', 'source', 'direction', 'amount', 'balance_after', 'loan_id', 'reference', 'description'];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function loan(): BelongsTo { return $this->belongsTo(Loan::class)->withTrashed(); }
}
