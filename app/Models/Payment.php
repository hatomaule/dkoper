<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['loan_id', 'installment_id', 'recorded_by', 'approved_by', 'amount', 'nominal_source', 'payment_method', 'proof_path', 'status', 'approved_at'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function loan(): BelongsTo { return $this->belongsTo(Loan::class); }
    public function installment(): BelongsTo { return $this->belongsTo(Installment::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
