<?php
/**
 * Stripe Payment Helper – TeaStore
 * Requires: Stripe PHP SDK (via Composer) or manual API calls
 * Install: composer require stripe/stripe-php
 * Or use manual cURL (this file uses cURL for zero-dependency setup)
 */

function stripeRequest(string $method, string $endpoint, array $data = []): array {
    $secretKey = getSetting('stripe_secret_key', '');
    if (!$secretKey) return ['error' => ['message' => 'Stripe secret key not configured.']];

    $ch = curl_init('https://api.stripe.com/v1' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $secretKey . ':',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?: ['error' => ['message' => 'Invalid Stripe response']];
}

/**
 * Create a Stripe PaymentIntent
 * Returns ['client_secret' => ..., 'payment_intent_id' => ...]
 */
function stripeCreatePaymentIntent(float $amount, string $currency = 'USD', string $orderRef = ''): array {
    $cents = (int) round($amount * 100);
    $data  = [
        'amount'   => $cents,
        'currency' => strtolower($currency),
        'automatic_payment_methods[enabled]' => 'true',
        'metadata[order_ref]' => $orderRef,
    ];
    $result = stripeRequest('POST', '/payment_intents', $data);
    if (isset($result['error'])) {
        return ['ok' => false, 'error' => $result['error']['message']];
    }
    return [
        'ok'                => true,
        'client_secret'     => $result['client_secret'],
        'payment_intent_id' => $result['id'],
    ];
}

/**
 * Retrieve a PaymentIntent status
 */
function stripeGetPaymentIntent(string $intentId): array {
    $result = stripeRequest('GET', '/payment_intents/' . $intentId);
    if (isset($result['error'])) {
        return ['ok' => false, 'error' => $result['error']['message']];
    }
    return ['ok' => true, 'status' => $result['status'], 'data' => $result];
}
