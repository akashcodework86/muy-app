<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LiveChatController extends Controller
{
    private const ONLINE_WINDOW_MINUTES = 3;

    private const ALLOWED_ROLES = ['state_admin', 'state_staff', 'hub_admin', 'district_staff'];

    public function contacts(Request $request): JsonResponse
    {
        $me = $this->staffUserOrNull($request);
        if (! $me) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $onlineCutoff = now()->subMinutes(self::ONLINE_WINDOW_MINUTES);

        $rows = User::query()
            ->where('is_active', true)
            ->whereIn('role', self::ALLOWED_ROLES)
            ->where('id', '!=', (int) $me->id)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $onlineCutoff)
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'last_seen_at']);

        return response()->json([
            'contacts' => $rows->map(fn (User $u): array => [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'role' => (string) $u->role,
                'last_seen_at' => optional($u->last_seen_at)->toIso8601String(),
            ])->values(),
            'now' => now()->toIso8601String(),
        ]);
    }

    public function thread(Request $request, User $user): JsonResponse
    {
        $me = $this->staffUserOrNull($request);
        if (! $me || ! $this->canChatWith($me, $user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! $this->isOnline($me) || ! $this->isOnline($user)) {
            $this->clearPair((int) $me->id, (int) $user->id);

            return response()->json([
                'messages' => [],
                'typing_user_id' => null,
                'online' => false,
            ]);
        }

        $pair = $this->pairKey((int) $me->id, (int) $user->id);
        $messages = Cache::get($pair.':messages', []);
        if (! is_array($messages)) {
            $messages = [];
        }

        $typingUserId = null;
        $typingOther = (int) Cache::get($pair.':typing:'.(int) $user->id, 0);
        if ($typingOther > 0) {
            $typingUserId = (int) $user->id;
        }

        return response()->json([
            'messages' => array_values($messages),
            'typing_user_id' => $typingUserId,
            'online' => true,
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $me = $this->staffUserOrNull($request);
        if (! $me) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'text' => ['required', 'string', 'max:500'],
        ]);

        $to = User::query()->findOrFail((int) $validated['to_user_id']);
        if (! $this->canChatWith($me, $to)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! $this->isOnline($me) || ! $this->isOnline($to)) {
            $this->clearPair((int) $me->id, (int) $to->id);

            return response()->json([
                'ok' => false,
                'message' => 'Both users must be online for session chat.',
            ], 422);
        }

        $pair = $this->pairKey((int) $me->id, (int) $to->id);
        $key = $pair.':messages';
        $list = Cache::get($key, []);
        if (! is_array($list)) {
            $list = [];
        }

        $id = ((int) Cache::increment($pair.':next_id')) ?: 1;
        $msg = [
            'id' => $id,
            'from_user_id' => (int) $me->id,
            'to_user_id' => (int) $to->id,
            'text' => trim((string) $validated['text']),
            'created_at' => now()->toIso8601String(),
            'seen_at' => null,
        ];
        $list[] = $msg;
        $list = array_slice($list, -200);
        Cache::put($key, $list, now()->addMinutes(20));

        return response()->json([
            'ok' => true,
            'message' => $msg,
        ]);
    }

    public function seen(Request $request): JsonResponse
    {
        $me = $this->staffUserOrNull($request);
        if (! $me) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'with_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $with = User::query()->findOrFail((int) $validated['with_user_id']);
        if (! $this->canChatWith($me, $with)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $pair = $this->pairKey((int) $me->id, (int) $with->id);
        $key = $pair.':messages';
        $list = Cache::get($key, []);
        if (! is_array($list) || $list === []) {
            return response()->json(['ok' => true]);
        }

        $nowIso = now()->toIso8601String();
        foreach ($list as &$row) {
            if ((int) ($row['to_user_id'] ?? 0) === (int) $me->id && empty($row['seen_at'])) {
                $row['seen_at'] = $nowIso;
            }
        }
        unset($row);
        Cache::put($key, $list, now()->addMinutes(20));

        return response()->json(['ok' => true]);
    }

    public function typing(Request $request): JsonResponse
    {
        $me = $this->staffUserOrNull($request);
        if (! $me) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'with_user_id' => ['required', 'integer', 'exists:users,id'],
            'typing' => ['required', 'boolean'],
        ]);
        $with = User::query()->findOrFail((int) $validated['with_user_id']);
        if (! $this->canChatWith($me, $with)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $pair = $this->pairKey((int) $me->id, (int) $with->id);
        $key = $pair.':typing:'.(int) $me->id;
        if ((bool) $validated['typing']) {
            Cache::put($key, 1, now()->addSeconds(5));
        } else {
            Cache::forget($key);
        }

        return response()->json(['ok' => true]);
    }

    private function staffUserOrNull(Request $request): ?User
    {
        $u = $request->user();
        if (! $u || ! in_array((string) $u->role, self::ALLOWED_ROLES, true)) {
            return null;
        }

        return $u;
    }

    private function canChatWith(User $a, User $b): bool
    {
        return (int) $a->id !== (int) $b->id
            && in_array((string) $a->role, self::ALLOWED_ROLES, true)
            && in_array((string) $b->role, self::ALLOWED_ROLES, true);
    }

    private function pairKey(int $a, int $b): string
    {
        $x = min($a, $b);
        $y = max($a, $b);

        return 'live-chat:pair:'.$x.':'.$y;
    }

    private function clearPair(int $a, int $b): void
    {
        $pair = $this->pairKey($a, $b);
        Cache::forget($pair.':messages');
        Cache::forget($pair.':typing:'.$a);
        Cache::forget($pair.':typing:'.$b);
        Cache::forget($pair.':next_id');
    }

    private function isOnline(User $user): bool
    {
        return $user->last_seen_at !== null
            && $user->last_seen_at->gte(now()->subMinutes(self::ONLINE_WINDOW_MINUTES));
    }
}

