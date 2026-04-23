<?php
/**
 * PayPal Orders API Helper – TeaStore
 * Uses PayPal REST API v2 (no SDK required)
 */

function paypalGetAccessToken(): string {
    $clientId = getSetting('paypal_client_id', '');
    $secret   = getSetting('paypal_secret', '');
    $mode     = getSetting('paypal_mode', 'sandbox');
    if (!$clientId || !$secret) return '';

    $base = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    $ch   = curl_init($base . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $clientId . ':' . $secret,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $response['access_token'] ?? '';
}

function paypalRequest(string $method, string $endpoint, array $body = []): array {
    $mode  = getSetting('paypal_mode', 'sandbox');
    $base  = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    $token = paypalGetAccessToken();
    if (!$token) return ['error' => 'Could not authenticate with PayPal'];

    $ch = curl_init($base . $endpoint);
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
        'PayPal-Request-Id: teastore-' . uniqid(),
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
    ]);
    if (!empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?: ['error' => 'Invalid PayPal response'];
}

/**
 * Create a PayPal Order
 * Returns ['ok' => true, 'order_id' => ..., 'approve_url' => ...]
 */
function paypalCreateOrder(float $amount, string $currency = 'USD', string $orderRef = ''): array {
    $body = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $orderRef,
            'amount'       => [
                'currency_code' => strtoupper($currency),
                'value'         => number_format($amount, 2, '.', ''),
            ],
        ]],
        'application_context' => [
            'brand_name'          => getSetting('site_name', 'TeaStore'),
            'shipping_preference' => 'NO_SHIPPING',
            'user_action'         => 'PAY_NOW',
        ],
    ];
    $result = paypalRequest('POST', '/v2/checkout/orders', $body);
    if (isset($result['error']) || empty($result['id'])) {
        return ['ok' => false, 'error' => $result['error'] ?? 'PayPal order creation failed'];
    }
    $approveUrl = '';
    foreach (($result['links'] ?? []) as $link) {
        if ($link['rel'] === 'approve') { $approveUrl = $link['href']; break; }
    }
    return ['ok' => true, 'order_id' => $result['id'], 'approve_url' => $approveUrl];
}

/**
 * Capture a PayPal Order after buyer approval
 */
function paypalCaptureOrder(string $paypalOrderId): array {
    $result = paypalRequest('POST', '/v2/checkout/orders/' . $paypalOrderId . '/capture');
    if (isset($result['error'])) {
        return ['ok' => false, 'error' => $result['error']];
    }
    $status = $result['status'] ?? '';
    return [
        'ok'     => $status === 'COMPLETED',
        'status' => $status,
        'data'   => $result,
    ];
}
