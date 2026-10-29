<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use SoftDeletes;

    protected $fillable = ['borrower_id', 'created_by', 'loan_code', 'principal_amount', 'administration_fee', 'net_disbursement', 'interest_amount', 'total_receivable', 'installment_count', 'status', 'approved_at', 'approved_by', 'rejected_at', 'rejected_by'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function borrower(): BelongsTo { return $this->belongsTo(Borrower::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function installments(): HasMany { return $this->hasMany(Installment::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
}
