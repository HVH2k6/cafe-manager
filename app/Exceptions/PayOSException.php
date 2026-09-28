<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * payOS could not be reached or answered with an error. The message is safe
 * to show to staff (it is payOS's own `desc` or a Vietnamese explanation).
 */
class PayOSException extends RuntimeException
{
    /**
     * payOS error code for "orderCode already exists".
     */
    public const DUPLICATE_ORDER_CODE = '231';

    public function __construct(string $message, public readonly ?string $payosCode = null)
    {
        parent::__construct($message);
    }

    public static function unreachable(): self
    {
        return new self('Không kết nối được payOS (hết thời gian chờ). Vui lòng thử lại.');
    }

    public static function notConfigured(): self
    {
        return new self('Chưa cấu hình payOS: điền PAYOS_CLIENT_ID, PAYOS_API_KEY, PAYOS_CHECKSUM_KEY trong file .env.');
    }

    public function isDuplicateOrderCode(): bool
    {
        return $this->payosCode === self::DUPLICATE_ORDER_CODE;
    }
}
