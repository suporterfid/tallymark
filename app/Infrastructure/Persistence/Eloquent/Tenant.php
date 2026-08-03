<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'name',
        'slug',
    ];

    protected function publicIdPrefix(): string
    {
        return 'ten';
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
