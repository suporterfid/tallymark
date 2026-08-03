<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use App\Infrastructure\Persistence\Eloquent\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteHost extends Model
{
    use HasPublicId, TenantScoped;

    protected $fillable = [
        'public_id',
        'tenant_id',
        'site_id',
        'hostname',
    ];

    protected function publicIdPrefix(): string
    {
        return 'host';
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
