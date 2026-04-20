<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveOpsController extends Controller
{
    /** Number of minutes before a user is considered offline. */
    private const ONLINE_WINDOW_MINUTES = 3;

    /** Soft window to classify users as "recently active" (e.g. 15 min). */
    private const RECENT_WINDOW_MINUTES = 15;

    public function presence(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['state_admin', 'hub_admin'], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $now = now();
        $onlineCutoff = $now->copy()->subMinutes(self::ONLINE_WINDOW_MINUTES);
        $recentCutoff = $now->copy()->subMinutes(self::RECENT_WINDOW_MINUTES);
        $todayStart = $now->copy()->startOfDay();

        $base = User::query()->where('is_active', true);

        // Scope hub_admin to users in their hub/district if applicable (soft scope — future hardening).
        if ($user->role === 'hub_admin' && $user->hub_id) {
            $base->where(function ($q) use ($user): void {
                $q->where('hub_id', $user->hub_id)
                  ->orWhereNull('hub_id');
            });
        }

        $byRoleOnline = (clone $base)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $onlineCutoff)
            ->selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->all();

        $totalByRole = (clone $base)
            ->selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->all();

        $activeToday = (clone $base)
            ->where('last_seen_at', '>=', $todayStart)
            ->count();

        $totalOnline = array_sum($byRoleOnline);

        $onlineList = (clone $base)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $onlineCutoff)
            ->orderByDesc('last_seen_at')
            ->limit(14)
            ->get(['id', 'name', 'role', 'district_id', 'hub_id', 'last_seen_at'])
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'initials' => mb_strtoupper(mb_substr(trim((string) $u->name), 0, 1)).(str_contains((string) $u->name, ' ') ? mb_strtoupper(mb_substr((string) explode(' ', (string) $u->name)[1] ?? '', 0, 1)) : ''),
                'role' => $u->role,
                'last_seen_at' => optional($u->last_seen_at)->toIso8601String(),
                'district_id' => $u->district_id,
                'hub_id' => $u->hub_id,
            ])
            ->values();

        return response()->json([
            'now' => $now->toIso8601String(),
            'online_window_minutes' => self::ONLINE_WINDOW_MINUTES,
            'total_online' => $totalOnline,
            'by_role_online' => [
                'state_admin' => (int) ($byRoleOnline['state_admin'] ?? 0),
                'hub_admin' => (int) ($byRoleOnline['hub_admin'] ?? 0),
                'district_staff' => (int) ($byRoleOnline['district_staff'] ?? 0),
                'incubatee' => (int) ($byRoleOnline['incubatee'] ?? 0),
            ],
            'by_role_total' => [
                'state_admin' => (int) ($totalByRole['state_admin'] ?? 0),
                'hub_admin' => (int) ($totalByRole['hub_admin'] ?? 0),
                'district_staff' => (int) ($totalByRole['district_staff'] ?? 0),
                'incubatee' => (int) ($totalByRole['incubatee'] ?? 0),
            ],
            'active_today' => $activeToday,
            'online_list' => $onlineList,
        ]);
    }

    public function activities(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['state_admin', 'hub_admin'], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $since = $request->query('since');
        $limit = (int) min(50, max(5, (int) $request->query('limit', 20)));

        $q = Activity::query()->orderByDesc('id');

        if (is_string($since) && $since !== '' && ctype_digit((string) $since)) {
            $q->where('id', '>', (int) $since);
        }

        if ($user->role === 'hub_admin' && $user->hub_id) {
            $q->where(function ($qq) use ($user): void {
                $qq->where('hub_id', $user->hub_id)
                   ->orWhereNull('hub_id');
            });
        }

        $rows = $q->limit($limit)->get([
            'id',
            'type',
            'actor_user_id',
            'actor_role',
            'actor_name',
            'subject_type',
            'subject_id',
            'district_id',
            'hub_id',
            'title',
            'meta',
            'created_at',
        ]);

        $districtNames = $rows->pluck('district_id')->filter()->unique()->values();
        $districtMap = $districtNames->isEmpty()
            ? []
            : \App\Models\District::query()
                ->whereIn('id', $districtNames->all())
                ->pluck('name', 'id')
                ->all();

        $items = $rows->map(fn (Activity $a): array => [
            'id' => $a->id,
            'type' => $a->type,
            'title' => $a->title,
            'actor_role' => $a->actor_role,
            'actor_name' => $a->actor_name,
            'district_name' => $a->district_id ? ($districtMap[$a->district_id] ?? null) : null,
            'created_at' => optional($a->created_at)->toIso8601String(),
            'meta' => $a->meta,
        ])->values();

        return response()->json([
            'items' => $items,
            'last_id' => (int) ($rows->first()->id ?? 0),
            'now' => now()->toIso8601String(),
        ]);
    }
}
