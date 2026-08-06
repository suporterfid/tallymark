<?php

namespace App\Http\Controllers\Auth;

use App\Application\GrandpaSson\GrandpaSsonSession;
use App\Application\GrandpaSson\SessionExchangeClientInterface;
use App\Domain\Shared\Clock;
use App\Domain\Shared\TenantRole;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class GrandpaSsonLoginController extends Controller
{
    /** @var list<string> */
    private const PROVIDERS = ['google', 'microsoft', 'github'];

    public function redirect(Request $request, string $provider, Clock $clock): RedirectResponse
    {
        $this->ensureInboundEnabled();
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $baseUrl = rtrim((string) config('grandpasson.base_url'), '/');
        $clientId = (string) config('grandpasson.browser_client_id');
        $redirectUri = (string) config('grandpasson.redirect_uri');
        if ($baseUrl === '' || $clientId === '' || $redirectUri === '') {
            throw new RuntimeException('GrandpaSSOn browser login is not configured.');
        }

        $state = Str::random(64);
        $request->session()->put('grandpasson.login_state', [
            'value' => $state,
            'expires_at' => $clock->now()->getTimestamp() + max(1, (int) config('grandpasson.login_state_ttl_seconds', 600)),
        ]);

        return redirect()->away($baseUrl.'/login/'.$provider.'?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]));
    }

    public function callback(
        Request $request,
        Clock $clock,
        SessionExchangeClientInterface $exchangeClient,
    ): RedirectResponse {
        $this->ensureInboundEnabled();

        $stored = $request->session()->get('grandpasson.login_state');
        $providedState = (string) $request->query('state', '');
        $validState = is_array($stored)
            && is_string($stored['value'] ?? null)
            && is_numeric($stored['expires_at'] ?? null)
            && $clock->now()->getTimestamp() <= (int) $stored['expires_at']
            && $providedState !== ''
            && hash_equals($stored['value'], $providedState);

        if (! $validState) {
            $request->session()->forget('grandpasson.login_state');
            abort(403);
        }

        $request->session()->forget('grandpasson.login_state');
        $code = (string) $request->query('code', '');
        if ($code === '') {
            abort(403);
        }

        try {
            $session = $exchangeClient->exchange($code, $request->query('tenant'));
        } catch (\Throwable) {
            abort(403);
        }

        $user = $this->provisionUser($session);
        $this->syncMappedMemberships($user, $session);

        $request->session()->put('grandpasson.identity', [
            'user_id' => $user->id,
            'tenant_public_ids' => array_values(array_filter(array_map(
                static fn (array $tenant): ?string => is_string($tenant['id'] ?? null) ? $tenant['id'] : null,
                $session->tenants,
            ))),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/');
    }

    private function ensureInboundEnabled(): void
    {
        abort_unless((bool) config('grandpasson.inbound_enabled', false), 404);
    }

    private function provisionUser(GrandpaSsonSession $session): User
    {
        return User::query()->firstOrCreate(
            ['email' => Str::lower($session->email)],
            [
                'name' => $session->name !== '' ? $session->name : $session->email,
                'password' => Hash::make(Str::random(64)),
            ],
        );
    }

    private function syncMappedMemberships(User $user, GrandpaSsonSession $session): void
    {
        $roleMap = config('grandpasson.group_role_map', []);
        $tenantPublicId = $session->tenantId;
        if ($tenantPublicId === null || ! is_array($roleMap)) {
            return;
        }

        $rolesForTenant = $roleMap[$tenantPublicId] ?? null;
        $role = null;
        if (is_array($rolesForTenant)) {
            foreach ($session->groups as $group) {
                if (isset($rolesForTenant[$group])) {
                    $role = TenantRole::tryFrom((string) $rolesForTenant[$group]);
                    if ($role instanceof TenantRole) {
                        break;
                    }
                }
            }
        }

        $tenant = Tenant::query()->where('public_id', $tenantPublicId)->first();
        if ($tenant === null) {
            return;
        }

        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        if ($role === null) {
            if ($membership?->identity_provider === 'grandpasson') {
                $membership->delete();
            }

            return;
        }

        if ($membership === null) {
            TenantMembership::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => $role,
                'identity_provider' => 'grandpasson',
            ]);
        } elseif ($membership->identity_provider === 'grandpasson') {
            $membership->update(['role' => $role]);
        }
    }
}
