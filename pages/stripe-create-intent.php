<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/stripe.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid method']); exit;
}

$amount   = (float)($_POST['amount'] ?? 0);
$currency = getSetting('currency_code', 'USD');
$orderRef = sanitize($_POST['ref'] ?? ('TS-' . date('YmdHis')));

if ($amount <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid amount']); exit;
}

if (!getSetting('stripe_publishable_key', '') || !getSetting('stripe_secret_key', '')) {
    echo json_encode(['ok' => false, 'error' => 'Stripe is not configured. Add keys in Admin → Settings → Payment.']); exit;
}

$result = stripeCreatePaymentIntent($amount, $currency, $orderRef);
echo json_encode($result);
