<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    protected $fillable = ['loan_id', 'sequence', 'scheduled_amount', 'paid_amount', 'outstanding_amount', 'status', 'paid_at'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function loan(): BelongsTo { return $this->belongsTo(Loan::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
}
