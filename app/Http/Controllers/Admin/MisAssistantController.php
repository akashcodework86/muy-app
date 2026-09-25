<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MisAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MisAssistantController extends Controller
{
    public function index(): View
    {
        return view('admin.mis-assistant.index');
    }

    public function ask(Request $request, MisAssistantService $assistant): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:500'],
        ]);

        $startedAt = microtime(true);
        $answer = $assistant->answer($validated['question']);

        logger()->info('State MIS assistant question', [
            'user_id' => $request->user()?->id,
            'intent' => $answer['intent'] ?? 'unknown',
            'question' => $validated['question'],
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return response()->json($answer);
    }
}
