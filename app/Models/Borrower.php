<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Borrower extends Model
{
    protected $fillable = ['user_id', 'borrower_code', 'full_name', 'phone', 'address', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function advanceTransactions(): HasMany
    {
        return $this->hasMany(BorrowerAdvanceTransaction::class);
    }
}
