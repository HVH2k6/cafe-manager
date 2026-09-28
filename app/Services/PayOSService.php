<?php

namespace App\Services;

use App\Exceptions\PayOSException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the payOS merchant API (https://payos.vn/docs/api/).
 * Credentials come from config('services.payos') and never leave the server.
 */
class PayOSService
{
    public function __construct(
        private readonly ?string $clientId,
        private readonly ?string $apiKey,
        private readonly ?string $checksumKey,
        private readonly string $baseUrl = 'https://api-merchant.payos.vn',
        private readonly int $timeoutSeconds = 15,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->clientId) && filled($this->apiKey) && filled($this->checksumKey);
    }

    /**
     * Signature of a payment request: HMAC-SHA256 (lowercase hex) over
     * "amount=…&cancelUrl=…&description=…&orderCode=…&returnUrl=…", values raw (not URL-encoded).
     *
     * @param  array{amount: int, cancelUrl: string, description: string, orderCode: int, returnUrl: string}  $data
     */
    public function createSignature(array $data): string
    {
        return hash_hmac('sha256', $this->paymentRequestSigningString($data), (string) $this->checksumKey);
    }

    /**
     * @param  array{amount: int, cancelUrl: string, description: string, orderCode: int, returnUrl: string}  $data
     */
    public function paymentRequestSigningString(array $data): string
    {
        return "amount={$data['amount']}&cancelUrl={$data['cancelUrl']}&description={$data['description']}"
            ."&orderCode={$data['orderCode']}&returnUrl={$data['returnUrl']}";
    }

    /**
     * Create a payment link. Returns payOS `data` (bin, accountNumber, checkoutUrl, qrCode, paymentLinkId, status…).
     *
     * @return array<string, mixed>
     *
     * @throws PayOSException
     */
    public function createPaymentLink(int $orderCode, int $amount, string $description, string $returnUrl, string $cancelUrl, ?int $expiredAt = null): array
    {
        $body = [
            'orderCode' => $orderCode,
            'amount' => $amount,
            'description' => $description,
            'cancelUrl' => $cancelUrl,
            'returnUrl' => $returnUrl,
        ];
        $body['signature'] = $this->createSignature($body);

        if ($expiredAt !== null) {
            $body['expiredAt'] = $expiredAt;
        }

        return $this->request('post', '/v2/payment-requests', $body);
    }

    /**
     * VietQR image for the transfer, e.g.
     * https://img.vietqr.io/image/970422-VQRQAMIDG1780-payos.jpg?amount=10000&addInfo=Thanh%20toan%20don%20123&accountName=PAYOS
     */
    public function buildQrImageUrl(string $bin, string $accountNumber, int $amount, string $description): string
    {
        return "https://img.vietqr.io/image/{$bin}-{$accountNumber}-payos.jpg"
            ."?amount={$amount}&addInfo=".rawurlencode($description).'&accountName=PAYOS';
    }

    /**
     * Current state of a payment link (status, amountPaid, transactions…), by orderCode or paymentLinkId.
     *
     * @return array<string, mixed>
     *
     * @throws PayOSException
     */
    public function getPaymentInfo(int|string $orderCodeOrLinkId): array
    {
        return $this->request('get', "/v2/payment-requests/{$orderCodeOrLinkId}");
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PayOSException
     */
    public function cancelPaymentLink(int|string $orderCodeOrLinkId, ?string $reason = null): array
    {
        return $this->request('post', "/v2/payment-requests/{$orderCodeOrLinkId}/cancel", array_filter(['cancellationReason' => $reason]));
    }

    /**
     * Register the webhook URL of the payment channel. payOS immediately sends a
     * test webhook to it, so the URL must already be reachable (e.g. via ngrok).
     *
     * @return array<string, mixed>
     *
     * @throws PayOSException
     */
    public function confirmWebhook(string $webhookUrl): array
    {
        return $this->request('post', '/confirm-webhook', ['webhookUrl' => $webhookUrl]);
    }

    /**
     * Check a webhook: keys of `data` sorted alphabetically, joined as key=value with "&"
     * (null → empty string, arrays/objects → JSON), HMAC-SHA256 with the checksum key.
     *
     * @param  array<string, mixed>  $data
     */
    public function verifyWebhookSignature(array $data, string $signature): bool
    {
        if (! filled($this->checksumKey) || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $this->webhookSigningString($data), $this->checksumKey), strtolower($signature));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function webhookSigningString(array $data): string
    {
        ksort($data);

        return collect($data)
            ->map(fn (mixed $value, string $key): string => $key.'='.match (true) {
                $value === null => '',
                is_bool($value) => $value ? 'true' : 'false',
                is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                default => (string) $value,
            })
            ->implode('&');
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws PayOSException
     */
    private function request(string $method, string $path, array $body = []): array
    {
        if (! $this->isConfigured()) {
            throw PayOSException::notConfigured();
        }

        try {
            /** @var Response $response */
            $response = Http::baseUrl($this->baseUrl)
                ->withHeaders(['x-client-id' => $this->clientId, 'x-api-key' => $this->apiKey])
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout($this->timeoutSeconds)
                ->{$method}($path, $method === 'get' ? null : $body);
        } catch (ConnectionException) {
            throw PayOSException::unreachable();
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new PayOSException("payOS trả về dữ liệu không hợp lệ (HTTP {$response->status()}).");
        }

        if (($json['code'] ?? null) !== '00') {
            throw new PayOSException($json['desc'] ?? 'payOS báo lỗi không xác định.', isset($json['code']) ? (string) $json['code'] : null);
        }

        return $json['data'] ?? [];
    }
}
