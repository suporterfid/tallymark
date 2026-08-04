<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use App\Infrastructure\Persistence\Eloquent\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasPublicId, TenantScoped;

    protected $fillable = [
        'public_id',
        'tenant_id',
        'name',
        'timezone',
        'site_key',
        'is_public',
        'exclude_rules',
        'sample',
        'validate_host',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'exclude_rules' => 'array',
            'sample' => 'integer',
            'validate_host' => 'boolean',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'site';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hosts(): HasMany
    {
        return $this->hasMany(SiteHost::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function sharedDashboard(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SharedDashboard::class);
    }
}
