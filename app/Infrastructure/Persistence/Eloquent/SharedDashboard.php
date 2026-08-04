<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SharedDashboard extends Model
{
    use HasPublicId;

    protected $fillable = ['is_enabled'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    protected function publicIdPrefix(): string
    {
        return 'dash';
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
