<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/paypal.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid method']); exit;
}

$paypalOrderId = sanitize($_POST['paypal_order_id'] ?? '');
if (!$paypalOrderId) {
    echo json_encode(['ok' => false, 'error' => 'Missing PayPal order ID']); exit;
}

$result = paypalCaptureOrder($paypalOrderId);
if ($result['ok']) {
    // Mark associated store order as paid if token provided
    $orderRef = sanitize($_POST['order_ref'] ?? '');
    if ($orderRef) {
        try {
            $pdo->prepare("UPDATE orders SET payment_status='paid', payment_intent_id=? WHERE order_number=?")
                ->execute([$paypalOrderId, $orderRef]);
        } catch (Throwable $e) {}
    }
}
echo json_encode($result);
