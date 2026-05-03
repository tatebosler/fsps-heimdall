<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Estimate extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'estimated_entry_at',
    ];

    protected $casts = [
        'estimated_entry_at' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
