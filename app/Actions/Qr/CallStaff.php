<?php

namespace App\Actions\Qr;

use App\Events\StaffCallAnswered;
use App\Events\StaffCalled;
use App\Models\QrNotification;
use App\Models\QrSession;
use App\Models\User;
use App\QrNotificationType;
use Illuminate\Database\Eloquent\Collection;

/**
 * "Gọi nhân viên": the customer's call pops up on every staff screen until
 * someone takes it; the customer then sees who is coming.
 */
class CallStaff
{
    /** A call not answered yet is repeated instead of piling up new ones within this time. */
    private const REPEAT_WINDOW_SECONDS = 60;

    public function handle(QrSession $session): QrNotification
    {
        $session->loadMissing('diningTable');

        $recent = $session->notifications()
            ->where('type', QrNotificationType::CallStaff)
            ->where('is_read', false)
            ->where('created_at', '>=', now()->subSeconds(self::REPEAT_WINDOW_SECONDS))
            ->first();

        if ($recent) {
            return $recent;
        }

        $call = QrNotification::create([
            'qr_session_id' => $session->id,
            'order_id' => $session->order_id,
            'type' => QrNotificationType::CallStaff,
            'title' => 'Gọi nhân viên',
            'message' => "Bàn {$session->diningTable->table_no} gọi nhân viên.",
        ]);

        StaffCalled::send($call);

        return $call;
    }

    /**
     * A staff member takes the call. Answering twice keeps the first answer.
     */
    public function answer(QrNotification $call, User $staff): QrNotification
    {
        if ($call->is_read) {
            return $call;
        }

        $call->update([
            'is_read' => true,
            'read_at' => now(),
            'title' => "{$staff->name} đang tới bàn bạn",
        ]);

        StaffCallAnswered::send($call, $staff->name);

        return $call;
    }

    /**
     * Calls still waiting for someone (older ones are considered stale).
     *
     * @return Collection<int, QrNotification>
     */
    public static function waiting(): Collection
    {
        return QrNotification::with('qrSession.diningTable')
            ->where('type', QrNotificationType::CallStaff)
            ->where('is_read', false)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->oldest('id')
            ->get();
    }
}
