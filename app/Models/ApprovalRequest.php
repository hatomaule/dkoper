<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    protected $fillable = ['request_type', 'requested_by', 'reviewed_by', 'approvable_type', 'approvable_id', 'payload', 'status', 'reviewed_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function approvable(): MorphTo { return $this->morphTo(); }
}
