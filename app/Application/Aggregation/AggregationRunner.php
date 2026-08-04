<?php

declare(strict_types=1);

namespace App\Application\Aggregation;

use App\Domain\Aggregation\CardinalityGuard;
use App\Infrastructure\Persistence\Eloquent\IngestBatch;
use Illuminate\Support\Facades\DB;

final class AggregationRunner
{
    public function run(): void
    {
        while (($batch = IngestBatch::query()->where('status', 'staged')->orderBy('id')->first()) !== null) {
            DB::transaction(function () use ($batch): void {
                $batch = IngestBatch::query()->lockForUpdate()->findOrFail($batch->id);
                if ($batch->status !== 'staged') return;
                $events = $batch->events()->orderBy('line_number')->get()->pluck('payload')->all();
                $this->aggregate($events);
                $batch->events()->delete();
                $batch->update(['status' => 'aggregated']);
            });
        }
    }

    /** @param list<array<string,mixed>> $events */
    private function aggregate(array $events): void
    {
        $sessionMetrics = $this->sessionMetrics($events);
        $groups = [];
        foreach ($events as $event) {
            if (($event['is_bot'] ?? false) || $event['event'] !== 'pageview') continue;
            $hour = (new \DateTimeImmutable((string) $event['timestamp']))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:00:00');
            $key = $event['site_id'].'|'.$hour;
            $groups[$key]['site_id'] = (int) $event['site_id']; $groups[$key]['hour'] = $hour;
            $groups[$key]['events'][] = $event;
        }
        foreach ($groups as $group) {
            $visitors = array_unique(array_column($group['events'], 'visitor_id'));
            $pageviews = count($group['events']); $metrics = $sessionMetrics[$group['site_id'].'|'.$group['hour']] ?? ['sessions'=>0,'bounces'=>0,'duration_sum'=>0];
            $this->add('stats_hourly_totals', ['site_id' => $group['site_id'], 'hour' => $group['hour']], ['pageviews' => $pageviews, 'visitors' => count($visitors), 'sessions' => $metrics['sessions'], 'bounces' => $metrics['bounces'], 'duration_sum' => $metrics['duration_sum']]);
            $paths = [];
            foreach ($group['events'] as $event) {
                $path = (string) (parse_url((string) $event['url'], PHP_URL_PATH) ?: '/');
                $paths[$path][] = $event;
            }
            foreach ($paths as $pathEvents) {
                $this->addPage($group['site_id'], $group['hour'], $pathEvents[0], count(array_unique(array_column($pathEvents, 'visitor_id'))), count($pathEvents));
            }
            $this->dimensions($group['site_id'], $group['hour'], $group['events']);
        }
        $customGroups = [];
        foreach ($events as $event) {
            if (($event['is_bot'] ?? false) || $event['event'] === 'pageview') continue;
            $hour = (new \DateTimeImmutable((string) $event['timestamp']))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:00:00');
            $customGroups[$event['site_id'].'|'.$hour]['site_id'] = (int) $event['site_id']; $customGroups[$event['site_id'].'|'.$hour]['hour'] = $hour; $customGroups[$event['site_id'].'|'.$hour]['events'][] = $event;
        }
        foreach ($customGroups as $group) $this->dimension($group['site_id'], $group['hour'], $group['events'], 'stats_hourly_events', fn ($e) => ['event_name' => (string) ($e['name'] ?: $e['event'])], 'count');
    }

    /** @param list<array<string,mixed>> $events @return array<string,array{sessions:int,bounces:int,duration_sum:int}> */
    private function sessionMetrics(array $events): array
    {
        usort($events, static fn (array $a, array $b): int => strcmp((string) $a['timestamp'], (string) $b['timestamp'])); $metrics = [];
        foreach ($events as $event) {
            if (($event['is_bot'] ?? false) || $event['event'] !== 'pageview') continue;
            $at = new \DateTimeImmutable((string) $event['timestamp']); $hour = $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:00:00'); $day = $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d'); $site = (int) $event['site_id']; $key = $site.'|'.$hour;
            $state = DB::table('session_states')->where(['site_id'=>$site,'visitor_id'=>(string)$event['visitor_id'],'day'=>$day])->lockForUpdate()->first();
            if ($state === null || $at->getTimestamp() - (new \DateTimeImmutable($state->last_event_at))->getTimestamp() > 1800) {
                DB::table('session_states')->updateOrInsert(['site_id'=>$site,'visitor_id'=>(string)$event['visitor_id'],'day'=>$day],['hour'=>$hour,'last_event_at'=>$at->format('Y-m-d H:i:s'),'last_pageview_at'=>$at->format('Y-m-d H:i:s'),'pageviews'=>1]);
                $metrics[$key] = ($metrics[$key] ?? ['sessions'=>0,'bounces'=>0,'duration_sum'=>0]); $metrics[$key]['sessions']++; $metrics[$key]['bounces']++; continue;
            }
            $startKey = $site.'|'.$state->hour; $metrics[$startKey] = ($metrics[$startKey] ?? ['sessions'=>0,'bounces'=>0,'duration_sum'=>0]);
            if ((int)$state->pageviews === 1) $metrics[$startKey]['bounces']--;
            $metrics[$startKey]['duration_sum'] += $at->getTimestamp() - (new \DateTimeImmutable($state->last_pageview_at))->getTimestamp();
            DB::table('session_states')->where(['site_id'=>$site,'visitor_id'=>(string)$event['visitor_id'],'day'=>$day])->update(['last_event_at'=>$at->format('Y-m-d H:i:s'),'last_pageview_at'=>$at->format('Y-m-d H:i:s'),'pageviews'=>(int)$state->pageviews+1]);
        }
        return $metrics;
    }

    /** @param list<array<string,mixed>> $events */ private function dimensions(int $siteId, string $hour, array $events): void
    {
        $this->dimension($siteId,$hour,$events,'stats_hourly_referrers',fn($e)=>['referrer'=>($host=parse_url((string)($e['referrer'] ?? ''),PHP_URL_HOST)) ? implode('.',array_slice(explode('.',$host),-2)) : 'direct'],'pageviews');
        $this->dimension($siteId,$hour,$events,'stats_hourly_countries',fn($e)=>['country'=>'unknown'],'pageviews');
        $this->dimension($siteId,$hour,$events,'stats_hourly_devices',fn($e)=>['device'=>(string)($e['device']??'unknown'),'browser'=>(string)($e['browser']??'unknown'),'os'=>(string)($e['os']??'unknown')],'pageviews');
        $this->dimension($siteId,$hour,$events,'stats_hourly_campaigns',function($e){parse_str((string)parse_url((string)$e['url'],PHP_URL_QUERY),$q);return ['source'=>(string)($q['utm_source']??'direct'),'medium'=>(string)($q['utm_medium']??'none'),'campaign'=>(string)($q['utm_campaign']??'(none)')];},'pageviews');
    }
    /** @param list<array<string,mixed>> $events @param callable(array<string,mixed>):array<string,string> $keys */ private function dimension(int $siteId,string $hour,array $events,string $table,callable $keys,string $metric): void { $groups=[];foreach($events as $event){$key=$keys($event);$groups[json_encode($key)][]=$event;}foreach($groups as $encoded=>$rows){$key=json_decode($encoded,true);$this->add($table,array_merge(['site_id'=>$siteId,'hour'=>$hour],$key),[$metric=>count($rows),'visitors'=>count(array_unique(array_column($rows,'visitor_id')))]);}}

    /** @param array<string,int|string> $keys @param array<string,int> $metrics */ private function add(string $table, array $keys, array $metrics): void { $query = DB::table($table)->where($keys); if ($query->exists()) { $updates=[]; foreach ($metrics as $key=>$value) $updates[$key]=DB::raw($key.' + '.(int)$value); $query->update($updates); } else DB::table($table)->insert(array_merge($keys,$metrics)); }
    /** @param array<string,mixed> $event */ private function addPage(int $siteId,string $hour,array $event,int $visitors,int $pageviews): void { $path=(string)(parse_url((string)$event['url'],PHP_URL_PATH) ?: '/'); $existing=DB::table('stats_hourly_pages')->where(['site_id'=>$siteId,'hour'=>$hour])->pluck('path')->all(); $decision=(new CardinalityGuard((int)(getenv('TM_MAX_DISTINCT_PER_BUCKET') ?: 500)))->guard($path,$existing); if($decision->folded) DB::table('system_heartbeats')->updateOrInsert(['name'=>'cardinality-cap:'.$siteId],['status'=>'warning','last_seen_at'=>now(),'message'=>'Hourly dimension cap reached','updated_at'=>now(),'created_at'=>now()]); $this->add('stats_hourly_pages',['site_id'=>$siteId,'hour'=>$hour,'path'=>$decision->value],['pageviews'=>$pageviews,'visitors'=>$visitors,'bounces'=>0,'duration_sum'=>0]); }
}
