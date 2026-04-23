<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = "My Orders – TeaStore";
if (!isLoggedIn()) redirect(pretty_url('pages/login.php'));

try {
    $stmt = $pdo->prepare("SELECT o.id, o.order_number, o.status, o.total, o.payment_method, o.address, o.created_at, COUNT(oi.id) AS item_count FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id WHERE o.user_id=? GROUP BY o.id ORDER BY o.created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll();
} catch (\Exception $e) {
    // Fallback simpler query
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll();
    foreach ($orders as &$o) $o['item_count'] = 0;
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container" style="padding-top:32px;padding-bottom:64px;">
    <div class="breadcrumb">
        <a href="<?= SITE_URL ?>/index.php">Home</a><span>›</span>
        <a href="<?= pretty_url('pages/account.php') ?>">Account</a><span>›</span>
        <span>My Orders</span>
    </div>
    <h1 class="page-title">My Orders</h1>
    <p style="color:var(--text-light);font-size:14px;margin-bottom:28px;">Track and manage your orders</p>

    <?php if (empty($orders)): ?>
    <div class="empty-state">
        <div class="icon">📦</div>
        <h3>No orders yet</h3>
        <p>Your order history will appear here once you shop.</p>
        <a href="<?= pretty_url('pages/shop.php') ?>" class="btn btn-primary" style="margin-top:20px;">Start Shopping</a>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px;">
        <?php foreach ($orders as $order): ?>
        <div style="background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:20px;transition:var(--transition);" onmouseover="this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.boxShadow='none'">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;gap:12px;flex-wrap:wrap;">
                <div>
                    <div style="font-weight:700;font-size:15px;margin-bottom:4px;"><?= htmlspecialchars($order['order_number']) ?></div>
                    <div style="font-size:13px;color:var(--text-muted);"><?= date('D, M d, Y · g:i A', strtotime($order['created_at'])) ?></div>
                </div>
                <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>"><?= ucfirst($order['status']) ?></span>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <div style="display:flex;gap:20px;font-size:13px;color:var(--text-light);">
                    <span><strong style="color:var(--text);"><?= intval($order['item_count']) ?></strong> item<?= intval($order['item_count']) != 1 ? 's' : '' ?></span>
                    <span>
                        <?php
                        $pm = $order['payment_method'] ?? 'paypal';
                        echo $pm === 'stripe' ? '💳 Stripe' : ($pm === 'paypal' ? '🅿️ PayPal' : ucfirst($pm));
                        ?>
                    </span>
                </div>
                <div style="font-size:17px;font-weight:700;"><?= formatPrice($order['total']) ?></div>
            </div>
            <?php if (!empty($order['address'])): ?>
            <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);font-size:13px;color:var(--text-muted);">
                <i class="fas fa-map-marker-alt" style="margin-right:6px;color:var(--primary);"></i><?= htmlspecialchars($order['address']) ?>
            </div>
            <?php endif; ?>
            <div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--border);display:flex;justify-content:flex-end">
                <a href="<?= SITE_URL ?>/admin/invoice.php?id=<?= $order['id'] ?>" target="_blank" style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--primary);text-decoration:none;padding:6px 12px;border:1.5px solid var(--border);border-radius:20px;transition:.2s" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                    🖨️ View &amp; Print Invoice
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
