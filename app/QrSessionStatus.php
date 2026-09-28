<?php

namespace App;

/**
 * Mirrors the `qr_sessions.status` column in cafe_schema_final.sql.
 */
enum QrSessionStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Abandoned = 'abandoned';
    case Expired = 'expired';
}
