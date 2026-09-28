<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    /**
     * Receipt for staff, sized for 80 mm thermal paper, with the e-receipt QR
     * for when there is no printer. An unpaid order prints as a provisional bill.
     */
    public function show(Order $order): View
    {
        return $this->receipt($order, isPublic: false);
    }

    /**
     * E-receipt the customer opens on their phone by scanning the QR code.
     * No login: the signed URL is the proof, and it cannot be guessed for other orders.
     */
    public function showPublic(Order $order): View
    {
        return $this->receipt($order, isPublic: true);
    }

    /**
     * Signed link to the e-receipt, on the address customers' phones can reach.
     */
    public static function publicUrl(Order $order): string
    {
        $path = URL::signedRoute('receipts.public', ['order' => $order], absolute: false);
        $root = rtrim((string) (config('app.public_url') ?: request()->root()), '/');

        return $root.$path;
    }

    private function receipt(Order $order, bool $isPublic): View
    {
        return view('orders.receipt', [
            'order' => $order->load(['diningTable', 'creator', 'items.product', 'items.bundle.items.product', 'payment']),
            'isPublic' => $isPublic,
            'eReceiptUrl' => $isPublic ? null : self::publicUrl($order),
        ]);
    }
}
