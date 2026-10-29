<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Borrower extends Model
{
    protected $fillable = ['user_id', 'borrower_code', 'full_name', 'phone', 'address', 'status'];

    protected static function booted(): void
    {
        static::saved(fn (Borrower $borrower) => app(\App\Services\FidesAccountDirectory::class)->syncBorrower($borrower));
        static::deleted(fn (Borrower $borrower) => app(\App\Services\FidesAccountDirectory::class)->syncBorrower($borrower));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class)->withTrashed();
    }

    public function advanceTransactions(): HasMany
    {
        return $this->hasMany(BorrowerAdvanceTransaction::class);
    }

    public function scopeCustomerAccount(Builder $query): Builder
    {
        return $query
            ->whereRaw('LOWER(TRIM(full_name)) <> ?', ['admin'])
            ->where(function (Builder $query) {
                $query->whereNull('user_id')
                    ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('role', 'nasabah'));
            });
    }
}
