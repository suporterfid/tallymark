<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Goal extends Model
{
    use HasPublicId;

    protected $fillable = ['name', 'event_name', 'url_pattern', 'is_enabled'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    protected function publicIdPrefix(): string
    {
        return 'goal';
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
