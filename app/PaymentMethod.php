<?php

namespace App;

/**
 * Mirrors the `payments.method` column in cafe_schema_final.sql.
 * payOS transfers are stored as QrPay.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case EWallet = 'e_wallet';
    case BankTransfer = 'bank_transfer';
    case QrPay = 'qr_pay';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tiền mặt',
            self::Card => 'Thẻ',
            self::EWallet => 'Ví điện tử',
            self::BankTransfer => 'Chuyển khoản',
            self::QrPay => 'Chuyển khoản QR (payOS)',
        };
    }

    /**
     * Name and explanation shown to customers choosing how they will pay (QR ordering).
     *
     * @return array{0: string, 1: string}
     */
    public function forCustomers(): array
    {
        return match ($this) {
            self::Cash => ['Tiền mặt', 'Nhân viên thu tại bàn'],
            self::Card => ['Thẻ tín dụng / ghi nợ', 'Visa, Mastercard, JCB, Napas — quẹt thẻ tại bàn'],
            self::EWallet => ['Ví điện tử', 'MoMo, ZaloPay — quét mã VietQR'],
            self::BankTransfer, self::QrPay => ['Chuyển khoản', 'Quét mã VietQR bằng app ngân hàng'],
        };
    }
}
