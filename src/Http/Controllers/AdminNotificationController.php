<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Contracts\AdminNotificationSource;
use Chencongbao\LaravelVbenAdmin\Support\AdminPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class AdminNotificationController extends Controller
{
    public function __construct(private readonly AdminNotificationSource $source) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->source->paginate(
            $request->user(),
            (int) ($data['page'] ?? 1),
            AdminPagination::perPage($data['per_page'] ?? null),
        ));
    }

    public function latest(Request $request): JsonResponse
    {
        $data = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:20']]);

        return response()->json(['data' => $this->source->latest($request->user(), (int) ($data['limit'] ?? 10))]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $this->source->unreadCount($request->user())]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $this->source->markAsRead($request->user(), $notification);

        return response()->json(['message' => 'ok']);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->source->markAllAsRead($request->user());

        return response()->json(['message' => 'ok']);
    }

    public function hide(Request $request, string $notification): JsonResponse
    {
        $this->source->hide($request->user(), $notification);

        return response()->json(['message' => 'ok']);
    }

    public function clear(Request $request): JsonResponse
    {
        $this->source->clear($request->user());

        return response()->json(['message' => 'ok']);
    }
}
