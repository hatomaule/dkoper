<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ProfitTransaction extends Model
{
    protected $fillable = ['user_id', 'created_by', 'loan_id', 'installment_id', 'type', 'amount', 'balance_after', 'description'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function loan(): BelongsTo { return $this->belongsTo(Loan::class); }
    public function installment(): BelongsTo { return $this->belongsTo(Installment::class); }
}
