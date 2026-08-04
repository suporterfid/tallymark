<?php

declare(strict_types=1);

namespace App\Application\TaskConnect;

use App\Domain\Shared\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GoalConversionTaskService
{
    private const CLAIM_LIMIT = 10;

    private const CLAIM_TTL_SECONDS = 300;

    public function __construct(
        private readonly TaskConnectTaskClientInterface $client,
        private readonly Clock $clock,
    ) {}

    public function queue(string $tickId, int $siteId, int $goalId, string $hour, int $count): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $goal = DB::table('goals')
            ->join('sites', 'sites.id', '=', 'goals.site_id')
            ->where('goals.id', $goalId)
            ->where('sites.id', $siteId)
            ->first(['goals.public_id as goal_public_id', 'goals.name as goal_name', 'sites.public_id as site_public_id']);
        if ($goal === null) {
            return;
        }

        $period = (new \DateTimeImmutable($hour, new \DateTimeZone('UTC')))->format(DATE_ATOM);
        $key = ['kind' => 'goal_conversion', 'tick_id' => $tickId, 'goal_id' => $goalId, 'period' => $hour];
        $now = $this->clock->now();
        $inserted = DB::table('taskconnect_submissions')->insertOrIgnore(array_merge($key, [
            'site_id' => $siteId,
            'task_name' => 'TallyMark goal '.$goal->goal_name.' '.$period,
            'target_url' => (string) config('taskconnect.goal_conversion_url'),
            'site_public_id' => (string) $goal->site_public_id,
            'goal_public_id' => (string) $goal->goal_public_id,
            'conversion_count' => $count,
            'idempotency_key' => 'tm-goal-'.hash('sha256', 'goal_conversion|'.$tickId.'|'.$goal->site_public_id.'|'.$goal->goal_public_id.'|'.$period),
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        if ($inserted === 0) {
            DB::table('taskconnect_submissions')->where($key)->update([
                'conversion_count' => DB::raw('conversion_count + '.(int) $count),
                'updated_at' => $now,
            ]);
        }
    }

    public function dispatchPending(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        foreach ($this->claimPending() as $submission) {
            try {
                $accepted = $this->client->submit([
                    'name' => $submission->task_name,
                    'method' => 'POST',
                    'url_or_path' => $submission->target_url,
                    'body' => json_encode([
                        'type' => 'goal_conversion',
                        'site_id' => $submission->site_public_id,
                        'goal_id' => $submission->goal_public_id,
                        'period' => (new \DateTimeImmutable($submission->period, new \DateTimeZone('UTC')))->format(DATE_ATOM),
                        'count' => (int) $submission->conversion_count,
                    ], JSON_THROW_ON_ERROR),
                    'content_type' => 'application/json',
                    'definition_status' => 'active',
                    'schedule' => [
                        'kind' => 'once',
                        'timezone' => 'UTC',
                        'at' => $this->clock->now()->add(new \DateInterval('PT1M'))->format(DATE_ATOM),
                    ],
                ], (string) $submission->idempotency_key);

                DB::table('taskconnect_submissions')->where([
                    'id' => $submission->id,
                    'claim_token' => $submission->claim_token,
                ])->update([
                    'status' => 'accepted',
                    'task_id' => $accepted->id,
                    'task_url' => $accepted->url,
                    'last_error' => null,
                    'claim_token' => null,
                    'claimed_at' => null,
                    'next_attempt_at' => null,
                    'updated_at' => $this->clock->now(),
                ]);
            } catch (\Throwable $exception) {
                $attempts = (int) $submission->attempts + 1;
                $delaySeconds = min(900, 30 * (2 ** min($attempts - 1, 5)));
                DB::table('taskconnect_submissions')->where([
                    'id' => $submission->id,
                    'claim_token' => $submission->claim_token,
                ])->update([
                    'status' => 'failed',
                    'attempts' => $attempts,
                    'next_attempt_at' => $this->clock->now()->add(new \DateInterval('PT'.$delaySeconds.'S')),
                    'claim_token' => null,
                    'claimed_at' => null,
                    'last_error' => mb_substr($exception->getMessage(), 0, 255),
                    'updated_at' => $this->clock->now(),
                ]);
            }
        }
    }

    /** @return list<object> */
    private function claimPending(): array
    {
        $now = $this->clock->now();
        $staleBefore = $now->sub(new \DateInterval('PT'.self::CLAIM_TTL_SECONDS.'S'));

        return DB::transaction(function () use ($now, $staleBefore): array {
            $submissions = DB::table('taskconnect_submissions')
                ->where(function ($query) use ($now, $staleBefore): void {
                    $query->where(function ($query) use ($now): void {
                        $query->whereIn('status', ['pending', 'failed'])
                            ->where(function ($query) use ($now): void {
                                $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now);
                            });
                    })->orWhere(function ($query) use ($staleBefore): void {
                        $query->where('status', 'submitting')->where('claimed_at', '<', $staleBefore);
                    });
                })
                ->orderBy('id')
                ->limit(self::CLAIM_LIMIT)
                ->lockForUpdate()
                ->get();

            foreach ($submissions as $submission) {
                $claimToken = Str::ulid()->toBase32();
                DB::table('taskconnect_submissions')->where('id', $submission->id)->update([
                    'status' => 'submitting',
                    'claim_token' => $claimToken,
                    'claimed_at' => $now,
                    'updated_at' => $now,
                ]);
                $submission->claim_token = $claimToken;
            }

            return $submissions->all();
        });
    }

    private function isEnabled(): bool
    {
        return (bool) config('taskconnect.enabled')
            && (string) config('taskconnect.goal_conversion_url') !== '';
    }
}
