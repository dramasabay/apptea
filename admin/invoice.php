<?php
$pageTitle = "Invoice";
require_once __DIR__ . '/../includes/config.php';
if (!canAccessAdmin() && !isLoggedIn()) { http_response_code(403); exit('Access denied.'); }

$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) { echo 'Invalid order.'; exit; }

// Customers can only view their own invoices
if (!canAccessAdmin()) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id=?");
    $stmt->execute([$orderId]);
}
$order = $stmt->fetch();
if (!$order) { echo 'Order not found.'; exit; }

$orderItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
$orderItems->execute([$orderId]);
$orderItems = $orderItems->fetchAll();

$siteName = getSetting('site_name','TeaStore');
$siteAddr = getSetting('site_address','Phnom Penh, Cambodia');
$sitePhone = getSetting('site_phone','');
$siteEmail = getSetting('site_email','');

$statusColors = ['pending'=>'#f59e0b','processing'=>'#3b82f6','shipped'=>'#8b5cf6','delivered'=>'#22c55e','cancelled'=>'#ef4444'];
$statusColor = $statusColors[$order['status']] ?? '#6b7280';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoice <?= htmlspecialchars($order['order_number']) ?> – <?= htmlspecialchars($siteName) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1a1a1a;background:#f4f6f9;padding:30px 16px}
.invoice{max-width:780px;margin:0 auto;background:#fff;border-radius:16px;box-shadow:0 4px 30px rgba(0,0,0,.1);overflow:hidden}
.inv-header{background:linear-gradient(135deg,#eb1700,#ff4a26);color:#fff;padding:36px 40px;display:flex;justify-content:space-between;align-items:flex-start}
.inv-title{font-size:32px;font-weight:900;letter-spacing:-1px}
.inv-status{display:inline-block;padding:6px 16px;border-radius:20px;font-size:12px;font-weight:700;background:<?= $statusColor ?>;color:#fff;text-transform:uppercase;letter-spacing:.5px;margin-top:8px}
.inv-body{padding:36px 40px}
.inv-grid{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-bottom:36px}
.inv-section-label{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;margin-bottom:8px}
.inv-section-val{font-size:14px;line-height:1.7;color:#374151}
.inv-section-val strong{color:#111827;font-size:15px}
table{width:100%;border-collapse:collapse;margin-bottom:28px}
thead th{background:#f9fafb;padding:12px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;border-bottom:2px solid #e5e7eb}
tbody td{padding:14px 16px;border-bottom:1px solid #f3f4f6;font-size:14px;color:#374151;vertical-align:top}
.totals{display:flex;justify-content:flex-end}
.totals-box{min-width:260px}
.totals-row{display:flex;justify-content:space-between;padding:7px 0;font-size:14px;color:#6b7280;border-bottom:1px solid #f3f4f6}
.totals-row.grand{font-size:18px;font-weight:800;color:#111827;border-bottom:none;padding-top:14px}
.inv-footer{background:#f9fafb;padding:20px 40px;font-size:12px;color:#9ca3af;text-align:center;border-top:1px solid #e5e7eb}
.print-btn{position:fixed;bottom:28px;right:28px;background:#eb1700;color:#fff;border:none;padding:14px 24px;border-radius:30px;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 8px 24px rgba(235,23,0,.3);display:flex;align-items:center;gap:8px}
.print-btn:hover{opacity:.9}
@media print{body{background:#fff;padding:0}.invoice{box-shadow:none;border-radius:0}.print-btn{display:none}}
@media(max-width:600px){.inv-header,.inv-body,.inv-footer{padding:24px 20px}.inv-grid{grid-template-columns:1fr}.inv-title{font-size:24px}}
</style>
</head>
<body>
<div class="invoice">
    <div class="inv-header">
        <div>
            <div class="inv-title">🍵 <?= htmlspecialchars($siteName) ?></div>
            <div style="font-size:13px;opacity:.8;margin-top:4px"><?= htmlspecialchars($siteAddr) ?></div>
            <?php if ($sitePhone): ?><div style="font-size:13px;opacity:.8"><?= htmlspecialchars($sitePhone) ?></div><?php endif; ?>
        </div>
        <div style="text-align:right">
            <div style="font-size:13px;opacity:.7;margin-bottom:4px">INVOICE</div>
            <div style="font-size:22px;font-weight:800"><?= htmlspecialchars($order['order_number']) ?></div>
            <div style="font-size:13px;opacity:.7;margin-top:6px"><?= date('d F Y', strtotime($order['created_at'])) ?></div>
            <div class="inv-status"><?= ucfirst($order['status']) ?></div>
        </div>
    </div>

    <div class="inv-body">
        <div class="inv-grid">
            <div>
                <div class="inv-section-label">Bill To</div>
                <div class="inv-section-val">
                    <strong><?= htmlspecialchars($order['name']) ?></strong><br>
                    <?php if ($order['email']): ?><?= htmlspecialchars($order['email']) ?><br><?php endif; ?>
                    <?php if ($order['phone']): ?><?= htmlspecialchars($order['phone']) ?><br><?php endif; ?>
                    <?php if ($order['address']): ?><?= nl2br(htmlspecialchars($order['address'])) ?><?php endif; ?>
                </div>
            </div>
            <div>
                <div class="inv-section-label">Order Details</div>
                <div class="inv-section-val">
                    <strong>Payment:</strong> <?= strtoupper($order['payment_method']) ?><br>
                    <strong>Status:</strong> <span style="color:<?= $statusColor ?>;font-weight:700"><?= ucfirst($order['status']) ?></span><br>
                    <?php if (!empty($order['payment_status'])): ?><strong>Payment Status:</strong> <?= ucfirst($order['payment_status']) ?><br><?php endif; ?>
                    <strong>Date:</strong> <?= date('d M Y, g:i A', strtotime($order['created_at'])) ?>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr><th style="width:45%">Product</th><th>Options</th><th style="text-align:right">Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Amount</th></tr>
            </thead>
            <tbody>
                <?php foreach ($orderItems as $oi):
                    $optData = json_decode($oi['variant_info'] ?? '{}', true);
                    $optText = is_array($optData) ? ($optData['text'] ?? ($oi['variant_info'] ?? '')) : ($oi['variant_info'] ?? '');
                ?>
                <tr>
                    <td style="font-weight:600;color:#111827"><?= htmlspecialchars($oi['product_name']) ?></td>
                    <td style="font-size:12px;color:#9ca3af"><?= $optText ? htmlspecialchars($optText) : '—' ?></td>
                    <td style="text-align:right"><?= $oi['qty'] ?></td>
                    <td style="text-align:right"><?= formatPrice($oi['price']) ?></td>
                    <td style="text-align:right;font-weight:700"><?= formatPrice($oi['price'] * $oi['qty']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-box">
                <div class="totals-row"><span>Subtotal</span><span><?= formatPrice($order['subtotal'] ?? $order['total']) ?></span></div>
                <?php if (!empty($order['shipping']) && $order['shipping'] > 0): ?>
                <div class="totals-row"><span>Shipping</span><span><?= formatPrice($order['shipping']) ?></span></div>
                <?php else: ?>
                <div class="totals-row"><span>Shipping</span><span style="color:#22c55e;font-weight:700">FREE</span></div>
                <?php endif; ?>
                <?php if (!empty($order['discount']) && $order['discount'] > 0): ?>
                <div class="totals-row"><span>Discount</span><span style="color:#22c55e">–<?= formatPrice($order['discount']) ?></span></div>
                <?php endif; ?>
                <div class="totals-row grand"><span>Total</span><span style="color:#eb1700"><?= formatPrice($order['total']) ?></span></div>
            </div>
        </div>

        <?php if ($order['notes']): ?>
        <div style="background:#f9fafb;border-radius:10px;padding:14px 16px;margin-top:20px;font-size:13px;color:#6b7280">
            <strong style="color:#374151">📝 Note:</strong> <?= htmlspecialchars($order['notes']) ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="inv-footer">
        Thank you for shopping at <?= htmlspecialchars($siteName) ?>! 🍵 &nbsp;·&nbsp; <?= htmlspecialchars($siteAddr) ?>
        <?php if ($siteEmail): ?> &nbsp;·&nbsp; <?= htmlspecialchars($siteEmail) ?><?php endif; ?>
    </div>
</div>

<button class="print-btn" onclick="window.print()">
    🖨️ Print Invoice
</button>
</body>
</html>
