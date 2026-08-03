<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use App\Infrastructure\Persistence\Eloquent\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasPublicId, TenantScoped;

    public const UPDATED_AT = null;

    protected $fillable = [
        'public_id',
        'tenant_id',
        'actor_user_id',
        'action',
        'resource_type',
        'resource_id',
        'summary_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'summary_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'aud';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
