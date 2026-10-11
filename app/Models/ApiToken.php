<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiToken extends Model
{
    /** Days a sign-in stays valid. */
    public const LIFETIME_DAYS = 30;

    protected $fillable = ['user_id', 'token_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
