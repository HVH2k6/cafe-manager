<?php

namespace App\Console\Commands;

use App\Models\QrSession;
use App\QrSessionStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Closes QR sessions nobody used for QrSession::LIFETIME_MINUTES (scheduled
 * every 5 minutes). A session following an order that is still open stays
 * active; otherwise it becomes abandoned (cart left unsent), expired (never
 * ordered) or completed (its order is closed).
 */
#[Signature('qr:expire-sessions')]
#[Description('Đóng các phiên gọi món QR đã quá 30 phút không dùng')]
class ExpireQrSessions extends Command
{
    public function handle(): int
    {
        $closed = 0;

        QrSession::with('order')
            ->where('status', QrSessionStatus::Active)
            ->where('expires_at', '<', now())
            ->chunkById(200, function ($sessions) use (&$closed): void {
                foreach ($sessions as $session) {
                    if ($session->order?->status->isOpen()) {
                        continue;
                    }

                    $status = match (true) {
                        $session->order !== null => QrSessionStatus::Completed,
                        ($session->cart_data['items'] ?? []) !== [] => QrSessionStatus::Abandoned,
                        default => QrSessionStatus::Expired,
                    };

                    $session->update(['status' => $status, 'completed_at' => $status === QrSessionStatus::Completed ? now() : null]);
                    $closed++;
                }
            });

        $this->info("Đã đóng {$closed} phiên QR.");

        return self::SUCCESS;
    }
}
