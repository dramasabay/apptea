<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = "Order Confirmed – TeaStore";
$orderNum = sanitize($_GET['order'] ?? '');
$payment  = sanitize($_GET['payment'] ?? 'stripe');
$total    = (float)($_GET['total'] ?? 0);

$order = null;
if ($orderNum) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number=?");
    $stmt->execute([$orderNum]);
    $order = $stmt->fetch();
}
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container" style="padding-top:48px;padding-bottom:80px;max-width:620px;">
    <div style="text-align:center;margin-bottom:36px;">
        <div style="width:80px;height:80px;background:linear-gradient(135deg,#e6f7ee,#b2dfcd);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:36px;box-shadow:0 4px 20px rgba(35,164,90,0.2);">✅</div>
        <h1 style="font-size:30px;font-weight:700;margin-bottom:8px;letter-spacing:-.5px;">Order Placed!</h1>
        <p style="color:var(--text-light);font-size:16px;">Thank you for shopping at TeaStore 🍵</p>
    </div>

    <div style="background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:24px;margin-bottom:20px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--border);">
            <span style="color:var(--text-muted);font-size:13px;">Order Number</span>
            <strong style="font-size:14px;font-family:monospace;"><?= htmlspecialchars($orderNum) ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--border);">
            <span style="color:var(--text-muted);font-size:13px;">Payment</span>
            <strong style="font-size:14px;"><?= $payment === 'stripe' ? '💳 Stripe Stripe/PayPal' : '💵 Online Payment' ?></strong>
        </div>
        <?php if ($order): ?>
        <div style="display:flex;justify-content:space-between;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--border);">
            <span style="color:var(--text-muted);font-size:13px;">Status</span>
            <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>"><?= ucfirst($order['status']) ?></span>
        </div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;">
            <span style="color:var(--text-muted);font-size:13px;">Total Amount</span>
            <strong style="font-size:20px;color:var(--primary);"><?= formatPrice($total) ?></strong>
        </div>
    </div>

    <?php if ($payment === 'stripe'): ?>
    <div style="background:var(--bg);border-radius:var(--radius);padding:28px;text-align:center;margin-bottom:20px;border:1.5px solid var(--border);">
        <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:16px;">
            <div style="background:#e53935;border-radius:8px;padding:6px 14px;"><span style="color:#fff;font-weight:800;font-size:13px;">BAKONG</span></div>
            <div style="text-align:left;">
                <div style="font-size:13px;font-weight:700;">TeaStore</div>
                <div style="font-size:11px;color:var(--text-muted);">Powered by National Bank of Cambodia</div>
            </div>
        </div>
        <h3 style="font-size:16px;font-weight:700;margin-bottom:4px;">Complete Your Payment</h3>
        <p style="font-size:13px;color:var(--text-light);margin-bottom:16px;">Scan with your banking app to confirm this order</p>
        <div style="display:inline-block;background:var(--white);padding:20px;border-radius:16px;box-shadow:var(--shadow-md);">
            <div id="finalQR"></div>
        </div>
        <div style="font-size:24px;font-weight:700;color:var(--primary);margin:12px 0;"><?= formatPrice($total) ?></div>
        <p style="font-size:13px;color:var(--text-light);margin-bottom:14px;">Your payment was processed securely via Stripe or PayPal.</p>
        <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
            <span style="background:var(--white);border:1px solid var(--border);border-radius:6px;padding:5px 12px;font-size:12px;font-weight:600;">ABA</span>
            <span style="background:var(--white);border:1px solid var(--border);border-radius:6px;padding:5px 12px;font-size:12px;font-weight:600;">ACLEDA</span>
            <span style="background:var(--white);border:1px solid var(--border);border-radius:6px;padding:5px 12px;font-size:12px;font-weight:600;">Wing</span>
            <span style="background:var(--white);border:1px solid var(--border);border-radius:6px;padding:5px 12px;font-size:12px;font-weight:600;">Canadia</span>
            <span style="background:var(--white);border:1px solid var(--border);border-radius:6px;padding:5px 12px;font-size:12px;font-weight:600;">BRED</span>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrstripeejs/1.0.0/qrstripee.min.js"></script>
    <script>
    (function() {
        function pad(val, len) { return String(val).padStart(len, '0'); }
        function tlv(tag, value) { return tag + pad(value.length, 2) + value; }
        function crc16(str) {
            var crc = 0xFFFF;
            for (var i = 0; i < str.length; i++) {
                crc ^= str.charCodeAt(i) << 8;
                for (var j = 0; j < 8; j++) { crc = (crc & 0x8000) ? ((crc << 1) ^ 0x1021) : (crc << 1); }
            }
            return (crc & 0xFFFF).toString(16).toUpperCase().padStart(4, '0');
        }
        var acctInfo = tlv('00','com.clover.loyalty') + tlv('01','BROTEACH@BAKONG.COM.KH') + tlv('02','teastore@abc-bank.com.kh');
        var payload = tlv('00','01') + tlv('01','12') + tlv('29',acctInfo) + tlv('52','5995') + tlv('53','840');
        payload += tlv('54','<?= number_format($total, 2, '.', '') ?>');
        payload += tlv('58','KH') + tlv('59','BROTEACH PET STORE') + tlv('60','PHNOM PENH');
        payload += tlv('62', tlv('05','<?= $orderNum ?>'));
        payload += '6304'; payload += crc16(payload);
        new QRCode(document.getElementById('finalQR'), {
            text: payload, width:200, height:200,
            colorDark:'#000000', colorLight:'#ffffff', correctLevel:QRCode.CorrectLevel.M
        });
    })();
    </script>
    <?php else: ?>
    <div style="background:#fff8e1;border-radius:var(--radius);padding:20px;margin-bottom:20px;border:1px solid #ffe082;">
        <h3 style="font-size:15px;font-weight:700;margin-bottom:8px;color:#e65100;">💵 Online Payment</h3>
        <p style="font-size:13px;color:#795548;line-height:1.6;">Please prepare <strong><?= formatPrice($total) ?></strong> in cash. Our delivery team will contact you to confirm delivery time.</p>
    </div>
    <?php endif; ?>

    <div style="background:var(--bg);border-radius:var(--radius);padding:20px;margin-bottom:24px;">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:14px;">What happens next?</h3>
        <?php 
        $steps = $payment === 'stripe'
            ? [['🔍','Payment Verification','We verify your Stripe/PayPal payment (within 1 hour)'],['📦','Order Processing','We pick and pack your order carefully'],['🚚','Out for Delivery','Your order is on the way to you'],['✅','Delivered','Enjoy your tea supplies!']]
            : [['📞','Order Confirmation','Our team will call to confirm your order'],['📦','Order Processing','We pick and pack your order'],['🚚','Out for Delivery','Estimated 1-3 business days'],['✅','Delivered','Pay cash and enjoy!']];
        foreach ($steps as $i => [$icon, $title, $desc]):
        ?>
        <div style="display:flex;gap:14px;<?= $i < count($steps)-1 ? 'margin-bottom:14px;' : '' ?>">
            <div style="font-size:20px;flex-shrink:0;width:28px;"><?= $icon ?></div>
            <div>
                <div style="font-size:13px;font-weight:600;margin-bottom:2px;"><?= $title ?></div>
                <div style="font-size:12px;color:var(--text-muted);"><?= $desc ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
        <?php if (isLoggedIn()): ?>
        <a href="<?= pretty_url('pages/orders.php') ?>" class="btn btn-dark">View My Orders</a>
        <?php endif; ?>
        <a href="<?= SITE_URL ?>/index.php" class="btn btn-primary">Continue Shopping</a>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
