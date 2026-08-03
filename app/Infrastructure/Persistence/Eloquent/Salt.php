<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class Salt extends Model
{
    protected $fillable = [
        'active_on',
        'value',
        'destroy_at',
    ];

    protected $hidden = [
        'value',
    ];

    protected function casts(): array
    {
        return [
            'active_on' => 'immutable_date',
            'destroy_at' => 'immutable_datetime',
        ];
    }
}
