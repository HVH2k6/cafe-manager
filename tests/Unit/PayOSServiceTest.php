<?php

use App\Services\PayOSService;

/*
 * Expected HMACs below were computed independently with:
 *   printf '%s' '<signing string>' | openssl dgst -sha256 -hmac '<checksum key>'
 */
const TEST_CHECKSUM_KEY = '1a54716c8f0efb2744fb28b6e38b25da7f67a925d98bc1c18bd8faaecadd7675';

function payos(): PayOSService
{
    return new PayOSService('client-id', 'api-key', TEST_CHECKSUM_KEY);
}

/**
 * The payment request of step 1 of the integration guide.
 *
 * @return array{amount: int, cancelUrl: string, description: string, orderCode: int, returnUrl: string}
 */
function samplePaymentRequest(): array
{
    return [
        'orderCode' => 1,
        'amount' => 10000,
        'description' => 'Thanh toan don 123',
        'cancelUrl' => 'https://your-domain.com/cancel',
        'returnUrl' => 'https://your-domain.com/success',
    ];
}

test('the payment request is signed over its fields in alphabetical order, not URL-encoded', function () {
    expect(payos()->paymentRequestSigningString(samplePaymentRequest()))
        ->toBe('amount=10000&cancelUrl=https://your-domain.com/cancel&description=Thanh toan don 123&orderCode=1&returnUrl=https://your-domain.com/success');
});

test('the payment request signature matches an independently computed HMAC-SHA256', function () {
    expect(payos()->createSignature(samplePaymentRequest()))
        ->toBe('cdd229de93718beda17cc2b5baf00028b0a77562eebd6e378f00be87d2e3be58');
});

test('the signature does not depend on the order the fields are given in', function () {
    expect(payos()->createSignature(array_reverse(samplePaymentRequest(), true)))
        ->toBe(payos()->createSignature(samplePaymentRequest()));
});

test('the VietQR image URL encodes the transfer description', function () {
    expect(payos()->buildQrImageUrl('970422', 'VQRQAMIDG1780', 10000, 'Thanh toan don 123'))
        ->toBe('https://img.vietqr.io/image/970422-VQRQAMIDG1780-payos.jpg?amount=10000&addInfo=Thanh%20toan%20don%20123&accountName=PAYOS');
});

describe('webhook signature', function () {
    /**
     * @return array<string, mixed>
     */
    function webhookData(): array
    {
        return [
            'orderCode' => 1,
            'amount' => 10000,
            'description' => 'Thanh toan don 123',
            'accountNumber' => 'VQRQAMIDG1780',
            'reference' => 'FT26271945740991',
            'transactionDateTime' => '2026-09-27 00:10:12',
            'currency' => 'VND',
            'paymentLinkId' => '41dcfdd1c94f4ffca8f010e5749abb87',
            'code' => '00',
            'desc' => 'success',
            'counterAccountBankId' => null,
            'counterAccountName' => 'HA VAN HUNG',
        ];
    }

    test('keys are sorted and null becomes an empty string', function () {
        expect(payos()->webhookSigningString(webhookData()))->toBe(
            'accountNumber=VQRQAMIDG1780&amount=10000&code=00&counterAccountBankId=&counterAccountName=HA VAN HUNG&currency=VND'
            .'&desc=success&description=Thanh toan don 123&orderCode=1&paymentLinkId=41dcfdd1c94f4ffca8f010e5749abb87'
            .'&reference=FT26271945740991&transactionDateTime=2026-09-27 00:10:12'
        );
    });

    test('a correctly signed webhook is accepted', function () {
        expect(payos()->verifyWebhookSignature(webhookData(), '72d66c5660fa09ae9dc87c65e0b3dff084b4a8525efd7ecf8a4e089cf9ec0863'))->toBeTrue();
    });

    test('a tampered webhook is rejected', function () {
        $tampered = [...webhookData(), 'amount' => 1000000];

        expect(payos()->verifyWebhookSignature($tampered, '72d66c5660fa09ae9dc87c65e0b3dff084b4a8525efd7ecf8a4e089cf9ec0863'))->toBeFalse()
            ->and(payos()->verifyWebhookSignature(webhookData(), ''))->toBeFalse();
    });

    test('nested values are signed as JSON', function () {
        expect(payos()->webhookSigningString(['b' => ['x' => 1, 'url' => 'https://a/b'], 'a' => true]))
            ->toBe('a=true&b={"x":1,"url":"https://a/b"}');
    });
});
