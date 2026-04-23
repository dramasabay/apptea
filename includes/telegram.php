<?php
/**
 * TeaStore Telegram Notification System
 * Uses Telegram Bot API to send order alerts to a group/channel
 */

function sendTelegramNotification(string $message): bool {
    $botToken = getSetting('telegram_bot_token', '');
    $chatId   = getSetting('telegram_chat_id', '');
    if (!$botToken || !$chatId) return false;

    $url  = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = [
        'chat_id'    => $chatId,
        'text'       => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $err = curl_errno($ch);
    curl_close($ch);
    if ($err) return false;
    $json = json_decode($res, true);
    return $json['ok'] ?? false;
}

function notifyNewOrder(array $order, array $items): void {
    if (getSetting('telegram_notify_orders', '1') !== '1') return;

    $pm    = $order['payment_method'] === 'stripe' ? '💳 Stripe' : ($order['payment_method'] === 'paypal' ? '🅿️ PayPal' : ucfirst($order['payment_method']));
    $emoji = in_array($order['payment_method'],['stripe','paypal']) ? '✅' : '🆕';

    $itemLines = '';
    foreach ($items as $item) {
        $itemLines .= "  • " . htmlspecialchars($item['product_name']) . " x{$item['qty']} — \${$item['price']}\n";
    }

    $msg = "{$emoji} <b>New Order #{$order['order_number']}</b>\n\n"
         . "👤 <b>Customer:</b> {$order['name']}\n"
         . "📞 <b>Phone:</b> {$order['phone']}\n"
         . "📍 <b>Address:</b> {$order['address']}\n"
         . "💳 <b>Payment:</b> {$pm}\n\n"
         . "<b>Items:</b>\n{$itemLines}\n"
         . "💰 <b>Total: \${$order['total']}</b>\n"
         . "🕐 " . date('d M Y H:i');

    sendTelegramNotification($msg);
}

function notifyOrderStatus(string $orderNum, string $status): void {
    $map = ['processing'=>'⚙️','shipped'=>'🚚','delivered'=>'✅','cancelled'=>'❌'];
    $e   = $map[$status] ?? '📦';
    sendTelegramNotification("{$e} Order <b>#{$orderNum}</b> status updated to <b>" . ucfirst($status) . "</b>");
}

function testTelegramConnection(): array {
    $botToken = getSetting('telegram_bot_token', '');
    $chatId   = getSetting('telegram_chat_id', '');
    if (!$botToken || !$chatId) return ['ok' => false, 'error' => 'Bot token or chat ID not configured'];

    // First verify token
    $url = "https://api.telegram.org/bot{$botToken}/getMe";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,CURLOPT_SSL_VERIFYPEER=>false]);
    $res  = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($res, true);
    if (!($json['ok'] ?? false)) return ['ok'=>false,'error'=>'Invalid bot token'];

    $botName = $json['result']['username'] ?? 'unknown';
    $sent    = sendTelegramNotification("🍵 <b>TeaStore Notification Test</b>\nBot @{$botName} is connected successfully!\n✅ Order alerts are active.");
    if ($sent) return ['ok'=>true,'bot'=>$botName];
    return ['ok'=>false,'error'=>'Could not send to chat ID — make sure bot is added to the group'];
}
