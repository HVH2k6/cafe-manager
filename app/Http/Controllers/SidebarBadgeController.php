<?php

namespace App\Http\Controllers;

use App\Support\SidebarBadges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sidebar counts, fetched again after a realtime event (or every so often
 * without the WebSocket). Open to every logged-in user: each count is only
 * included when the user holds its permission.
 */
class SidebarBadgeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(SidebarBadges::for($request->user()));
    }
}
