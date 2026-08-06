# TaskConnect integration

TaskConnect is optional. TallyMark remains fully functional when every
`TASKCONNECT_*` setting is absent or `TASKCONNECT_ENABLED=false`; in that
state it makes no outbound requests and creates no TaskConnect outbox rows.
The standalone collector (`public/px.php`) never calls TaskConnect.

## Configuration

Set these values only for an enabled installation:

```dotenv
TASKCONNECT_ENABLED=true
TASKCONNECT_BASE_URL=https://tasks.example.com
TASKCONNECT_TENANT_ID=ten_example
TASKCONNECT_ENVIRONMENT_ID=env_analytics
TASKCONNECT_GOAL_CONVERSION_URL=https://automation.example.com/conversions
TASKCONNECT_RUN_URL_TEMPLATE=https://tasks.example.com/tasks/{task_id}
```

Authenticate with exactly one of the following choices:

1. Set `TASKCONNECT_API_KEY` to the TaskConnect API key.
2. Set `GRANDPASSON_OUTBOUND_ENABLED=true`, `GRANDPASSON_BASE_URL`,
   `GRANDPASSON_OUTBOUND_CLIENT_ID`, and
   `GRANDPASSON_OUTBOUND_CLIENT_SECRET`. TallyMark requests a short-lived
   `client_credentials` token with `tasks:write` from `POST /oauth/token`
   and sends that bearer token to TaskConnect. The broker service client must
   be pinned to the target TaskConnect environment.

The broker path takes precedence when its outbound flag is enabled, so an
operator cannot accidentally fall back to an API key.

## Delegated work

TallyMark submits task definitions to:

```
POST /v1/tenants/{TASKCONNECT_TENANT_ID}/environments/{TASKCONNECT_ENVIRONMENT_ID}/tasks
```

Every submission has an `Idempotency-Key`. Digest delegation derives it from
`(report_id, period)`. Goal conversion automation derives it from the public
site id, public goal id, and UTC hourly period.

`TaskConnectDigestDelegator` is the application seam for a scheduled-report
caller. It returns the accepted TaskConnect task id and the optional link
from `TASKCONNECT_RUN_URL_TEMPLATE`; callers must display that link and must
describe the result as *accepted*, never as delivered.

Goal conversions are queued only by the application aggregation process,
after the collector has written its local buffer and the aggregate transaction
has committed. One row is queued for each goal and UTC hour in an aggregation
tick, never one task per conversion. Its JSON body is aggregate-only:

```json
{
  "type": "goal_conversion",
  "site_id": "site_...",
  "goal_id": "goal_...",
  "period": "2026-08-04T12:00:00+00:00",
  "count": 3
}
```

Visitor hashes and raw visitor identifiers are not included. A failed remote
submission leaves its local outbox row in `failed`; a bounded exponential
backoff controls the retry, with a transactional claim preventing concurrent
aggregate workers from sending the same row. The local analytics aggregate is
never rolled back for a remote TaskConnect failure.

## Health and cron

A platform operator or authorized GrandpaSSOn machine token can query:

```
GET /api/v1/tenants/{tenant_public_id}/health
```

It returns ingest and rollup freshness (healthy heartbeat in the last three
minutes), collector-buffer depth, and the accumulated shed-event count. This
endpoint exposes only operational state, not visitor data.

On shared hosting, configure the application cron separately from any
optional external monitor ping:

```cron
* * * * * /opt/alt/php83/usr/bin/php /home/account/app/artisan analytics:ingest >/dev/null 2>&1
* * * * * /opt/alt/php83/usr/bin/php /home/account/app/artisan analytics:rollup >/dev/null 2>&1
```

If an uptime monitor is desired, configure it independently to authenticate
to the health endpoint or to receive a cron ping. Do not put monitor tokens
in the collector configuration.
