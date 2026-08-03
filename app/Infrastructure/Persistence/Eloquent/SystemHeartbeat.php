<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class SystemHeartbeat extends Model
{
    protected $fillable = [
        'name',
        'status',
        'last_seen_at',
        'last_error_at',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'immutable_datetime',
            'last_error_at' => 'immutable_datetime',
        ];
    }
}
