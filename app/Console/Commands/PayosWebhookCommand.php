<?php

namespace App\Console\Commands;

use App\Exceptions\PayOSException;
use App\Services\PayOSService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Point the payOS payment channel's webhook at this app, e.g. through ngrok:
 *   ngrok http 8000
 *   php artisan payos:webhook https://abcd-1-2-3-4.ngrok-free.app
 */
#[Signature('payos:webhook {url : Public base URL of the app (e.g. the ngrok https URL) or the full webhook URL}')]
#[Description('Register the payOS webhook URL (payOS sends a test webhook to it right away)')]
class PayosWebhookCommand extends Command
{
    public function handle(PayOSService $payos): int
    {
        $url = rtrim((string) $this->argument('url'), '/');
        $path = '/'.ltrim(parse_url(route('payos.webhook'), PHP_URL_PATH), '/');
        $webhookUrl = str_ends_with($url, $path) ? $url : $url.$path;

        if (! str_starts_with($webhookUrl, 'https://')) {
            $this->error('payOS chỉ gửi webhook tới địa chỉ https (ví dụ URL https của ngrok).');

            return self::FAILURE;
        }

        $this->line("Đăng ký webhook: <info>{$webhookUrl}</info>");

        try {
            $payos->confirmWebhook($webhookUrl);
        } catch (PayOSException $exception) {
            $this->error("payOS từ chối: {$exception->getMessage()}");
            $this->line('Kiểm tra: ngrok đang chạy và trỏ đúng cổng của app, app đang chạy, key payOS trong .env đúng.');

            return self::FAILURE;
        }

        $this->info('Đã đăng ký. payOS sẽ gửi webhook thanh toán tới địa chỉ trên.');

        return self::SUCCESS;
    }
}
