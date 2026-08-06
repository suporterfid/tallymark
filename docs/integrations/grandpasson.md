# GrandpaSSOn integration

TallyMark runs with local email/password authentication by default. Set both
`GRANDPASSON_OUTBOUND_ENABLED` and `GRANDPASSON_INBOUND_ENABLED` to `true` only
after registering the two distinct broker clients below. The collector remains
outside this integration.

## Broker provisioning

The browser login is an `oauth_clients` confidential RP client. Its redirect
URI must exactly equal `GRANDPASSON_REDIRECT_URI`:

```bash
php cron/seed_oauth_client.php --client-id=tallymark \
  --name="TallyMark" \
  --redirect-uri=https://analytics.example.com/auth/grandpasson/callback \
  --secret='<long-random-secret>'
```

Machine-token introspection needs a separate `service_clients` client. Pin its
audience to the TallyMark tenant public id:

```bash
php cron/admin.php client:create-service "TallyMark" \
  --scopes=analytics:read,analytics:write,analytics:callback \
  --aud=workspace/ten_example
```

Configure the browser credentials with `GRANDPASSON_BROWSER_CLIENT_ID` and
`GRANDPASSON_BROWSER_CLIENT_SECRET`; configure the service client separately
with `GRANDPASSON_MACHINE_CLIENT_ID` and
`GRANDPASSON_MACHINE_CLIENT_SECRET`. Never reuse either secret in application
code or logs.

## Browser login and tenant mapping

`/auth/grandpasson/login/{google|microsoft|github}` binds a random state to the
web session, then redirects to the broker. The callback verifies and consumes
that state before immediately redeeming the short-lived, single-use code at
`/session/exchange`. A broker error, malformed response, expired state, or
state mismatch fails closed.

The callback provisions or links a local user by broker subject email. It never
copies a platform-admin claim. Tenant memberships are created only when both a
broker tenant id matches a local TallyMark tenant public id and a broker group
is explicitly mapped for the active tenant in `config/grandpasson.php`'s
`group_role_map`:

```php
'group_role_map' => [
    'ten_example' => [
        'analytics-viewer' => 'read_only_viewer',
        'analytics-admin' => 'tenant_admin',
    ],
],
```

Only memberships whose `identity_provider` is `grandpasson` are synchronized.
A removed or downgraded group therefore cannot affect a local membership, and
an absent active-tenant mapping revokes the prior GrandpaSSOn membership.

## Machine tokens and revocation

Opaque `gpat_live_…` machine tokens are sent to `/oauth/introspect` as a form
containing `client_id`, `client_secret`, and `token`; HTTP Basic auth is not
used. Results are cached by SHA-256 token fingerprint for
`GRANDPASSON_INTROSPECTION_CACHE_SECONDS` (30 seconds by default), never past
the broker's `exp` claim. Consequently, a revocation can take up to the shorter
of that configured interval and the token's remaining lifetime to be observed
by TallyMark.

For existing tenant-scoped API routes, a valid machine token needs
`analytics:read` for `GET` requests or `analytics:write` for mutations, plus
an audience matching either `ten_…` or `workspace/ten_…`. Denials record the
reason, required scope, presented scopes/audiences, and token fingerprint in
the tenant audit log.
