<?php

namespace App\Http\Controllers;

use App\Actions\Qr\CallStaff;
use App\Models\QrNotification;
use App\QrNotificationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A staff member takes a customer's "Gọi nhân viên" call.
 */
class QrCallController extends Controller
{
    public function answer(Request $request, QrNotification $call, CallStaff $callStaff): JsonResponse|RedirectResponse
    {
        abort_unless($call->type === QrNotificationType::CallStaff, 404);

        $call = $callStaff->answer($call, $request->user());

        if ($request->expectsJson()) {
            return response()->json(['answered' => true, 'title' => $call->title]);
        }

        return back()->with('status', 'Đã nhận cuộc gọi: '.$call->message);
    }
}
