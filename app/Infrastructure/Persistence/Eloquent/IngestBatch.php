<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class IngestBatch extends Model
{
    protected $fillable = [
        'filename',
        'status',
        'claim_token',
        'claim_expires_at',
        'accepted_lines',
        'malformed_lines',
        'next_line',
        'staged_at',
    ];

    protected function casts(): array
    {
        return [
            'claim_expires_at' => 'immutable_datetime',
            'staged_at' => 'immutable_datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(IngestEvent::class);
    }
}
