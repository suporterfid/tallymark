<?php

declare(strict_types=1);

namespace App\Application\Goals;

use App\Infrastructure\Persistence\Eloquent\Goal;
use App\Infrastructure\Persistence\Eloquent\Site;
use Illuminate\Support\Facades\DB;

final class GoalManager
{
    /** @param array{name:string,event_name:?string,url_pattern:?string} $attributes */
    public function create(Site $site, array $attributes): Goal
    {
        return DB::transaction(fn (): Goal => $site->goals()->create($attributes));
    }

    /** @param array{name?:string,is_enabled?:bool} $attributes */
    public function update(Goal $goal, array $attributes): Goal
    {
        return DB::transaction(function () use ($goal, $attributes): Goal {
            $goal->update($attributes);

            return $goal->fresh();
        });
    }

    public function delete(Goal $goal): void
    {
        DB::transaction(fn () => $goal->delete());
    }
}
