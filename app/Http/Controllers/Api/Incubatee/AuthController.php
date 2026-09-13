<?php

namespace App\Http\Controllers\Api\Incubatee;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\IncubateeAppPayloadService;
use App\Support\IncubateeLoginPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, IncubateeAppPayloadService $payload): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ]);

        $phone = IncubateeLoginPhone::fromInput($validated['phone']);
        if ($phone === null) {
            throw ValidationException::withMessages([
                'phone' => ['Invalid mobile number or password.'],
            ]);
        }

        $user = User::query()
            ->where('role', 'incubatee')
            ->where('phone', $phone)
            ->first();

        if ($user === null || ! Hash::check((string) $validated['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['Invalid mobile number or password.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'phone' => ['This account has been disabled. Contact the administrator.'],
            ]);
        }

        if ($user->cfa_submission_id === null || $user->cfaSubmission === null) {
            throw ValidationException::withMessages([
                'phone' => ['No CFA profile is linked to this account.'],
            ]);
        }

        $token = $user->createToken('incubatee-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $payload->userSummary($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }
}
