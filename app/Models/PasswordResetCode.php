<?php

// app/Models/PasswordResetCode.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PasswordResetCode extends Model
{
    protected $fillable = [
        'user_id','code_hash','reset_token','expires_at','used_at'
    ];
    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($q) {
        return $q->whereNull('used_at')->where('expires_at', '>', now());
    }

    public function isActive(): bool {
        return is_null($this->used_at) && $this->expires_at instanceof Carbon && $this->expires_at->isFuture();
    }
}

