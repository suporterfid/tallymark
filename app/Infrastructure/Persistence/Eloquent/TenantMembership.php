<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Shared\TenantRole;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMembership extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'tenant_id',
        'user_id',
        'role',
        'identity_provider',
    ];

    protected function casts(): array
    {
        return [
            'role' => TenantRole::class,
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'mem';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
