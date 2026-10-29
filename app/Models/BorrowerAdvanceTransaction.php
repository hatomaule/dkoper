<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowerAdvanceTransaction extends Model
{
    protected $fillable = ['borrower_id', 'loan_id', 'payment_id', 'created_by', 'amount', 'amount_delta', 'balance_after', 'description'];

    public function borrower(): BelongsTo { return $this->belongsTo(Borrower::class); }
    public function loan(): BelongsTo { return $this->belongsTo(Loan::class)->withTrashed(); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
