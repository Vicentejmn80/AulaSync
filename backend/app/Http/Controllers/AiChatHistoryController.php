<?php

namespace App\Http\Controllers;

use App\Services\AiChatHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiChatHistoryController extends Controller
{
    public function __construct(private AiChatHistoryService $history) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'messages' => $this->history->load($user?->id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $payload = $request->validate([
            'messages' => ['required', 'array', 'max:40'],
            'messages.*.role' => ['required', 'string', 'max:40'],
            'messages.*.text' => ['nullable', 'string', 'max:12000'],
            'messages.*.activity' => ['nullable', 'array'],
        ]);

        $messages = $this->history->store((int) $user->id, $payload['messages']);

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->history->forget($request->user()?->id);

        return response()->json(['success' => true]);
    }
}
