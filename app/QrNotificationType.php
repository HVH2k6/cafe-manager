<?php

namespace App;

/**
 * Mirrors the `qr_notifications.type` column in cafe_schema_final.sql.
 */
enum QrNotificationType: string
{
    case OrderReceived = 'order_received';
    case OrderPreparing = 'order_preparing';
    case OrderReady = 'order_ready';
    case CallStaff = 'call_staff';
    case PaymentLink = 'payment_link';
}
