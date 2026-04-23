<?php
$pageTitle = "Orders";
require_once __DIR__ . '/admin-header.php';
require_once __DIR__ . '/../includes/telegram.php';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = (int)$_POST['order_id'];
    $status  = sanitize($_POST['status']);
    $oRow    = $pdo->prepare("SELECT order_number FROM orders WHERE id=?");
    $oRow->execute([$orderId]);
    $oRow    = $oRow->fetch();
    $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$status, $orderId]);
    if ($oRow) notifyOrderStatus($oRow['order_number'], $status);
    flash('success', 'Order status updated to ' . ucfirst($status));
    redirect(pretty_url('admin/orders.php') . (isset($_GET['view']) ? '?view='.(int)$_GET['view'] : ''));
}

$statusFilter = sanitize($_GET['status'] ?? '');
$params = $statusFilter ? [$statusFilter] : [];
$where  = $statusFilter ? "WHERE status=?" : "WHERE 1";

$orders = $pdo->prepare("SELECT * FROM orders $where ORDER BY created_at DESC LIMIT 100");
$orders->execute($params);
$orders = $orders->fetchAll();

// View specific order
$viewOrder = null;
if (isset($_GET['view'])) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id=?");
    $stmt->execute([(int)$_GET['view']]);
    $viewOrder = $stmt->fetch();
    if ($viewOrder) {
        $orderItems = $pdo->prepare("SELECT oi.* FROM order_items oi WHERE oi.order_id=?");
        $orderItems->execute([$viewOrder['id']]);
        $orderItems = $orderItems->fetchAll();
    }
}

$statusColors = [
    'pending'    => 'badge-pending',
    'processing' => 'badge-processing',
    'shipped'    => 'badge-shipped',
    'delivered'  => 'badge-delivered',
    'cancelled'  => 'badge-cancelled',
];
function sBadge($s) {
    global $statusColors;
    return '<span class="badge '.($statusColors[$s]??'badge-pending').'">'.ucfirst($s).'</span>';
}
?>

<div class="page-header">
    <div>
        <h1>Orders</h1>
        <p><?= number_format(count($orders)) ?> orders<?= $statusFilter ? ' · '.ucfirst($statusFilter) : '' ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php foreach ([''=> 'All', 'pending'=>'Pending', 'processing'=>'Processing', 'shipped'=>'Shipped', 'delivered'=>'Delivered', 'cancelled'=>'Cancelled'] as $val => $label): ?>
        <a href="?<?= $val ? 'status='.$val : '' ?>" class="btn btn-sm <?= $statusFilter === $val ? 'btn-primary' : 'btn-ghost' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($viewOrder): ?>
<!-- Order Detail -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <div style="display:flex;align-items:center;gap:12px">
            <h3>Order <?= htmlspecialchars($viewOrder['order_number']) ?></h3>
            <?= sBadge($viewOrder['status']) ?>
            <?php 
            $pm = $viewOrder['payment_method'];
            $ps = $viewOrder['payment_status'] ?? 'pending';
            if ($pm === 'cod'): ?>
            <span class="badge" style="background:#fef3c7;color:#d97706;border:1px solid #fde68a">COD <?= ucfirst($ps) ?></span>
            <?php elseif (in_array($pm, ['stripe','paypal'])): ?>
            <span class="badge" style="background:#fff0f0;color:#dc2626;border:1px solid #fecaca"><?= strtoupper($pm) ?> <?= ucfirst($ps) ?></span>
            <?php else: ?>
            <span class="badge" style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0"><?= strtoupper($pm) ?> <?= ucfirst($ps) ?></span>
            <?php endif; ?>
        </div>
        <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
            <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-3);margin-bottom:10px">Customer</div>
                <div style="font-weight:700;font-size:15px;margin-bottom:4px"><?= htmlspecialchars($viewOrder['name']) ?></div>
                <div style="font-size:13px;color:var(--text-2)"><?= htmlspecialchars($viewOrder['email'] ?? '') ?></div>
                <div style="font-size:13px;color:var(--text-2)"><?= htmlspecialchars($viewOrder['phone'] ?? '') ?></div>
                <div style="font-size:13px;margin-top:6px;line-height:1.5"><?= htmlspecialchars($viewOrder['address'] ?? '') ?></div>
            </div>
            <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-3);margin-bottom:10px">Order Info</div>
                <div style="font-size:13px;margin-bottom:4px"><span style="color:var(--text-3)">Placed:</span> <?= date('M d, Y g:i A', strtotime($viewOrder['created_at'])) ?></div>
                <div style="font-size:13px;margin-bottom:4px"><span style="color:var(--text-3)">Payment:</span> <?= strtoupper($viewOrder['payment_method']) ?></div>
                <div style="font-size:13px;margin-bottom:4px"><span style="color:var(--text-3)">Subtotal:</span> <?= formatPrice($viewOrder['subtotal']) ?></div>
                <div style="font-size:13px;margin-bottom:4px"><span style="color:var(--text-3)">Shipping:</span> <?= $viewOrder['shipping'] > 0 ? formatPrice($viewOrder['shipping']) : 'FREE' ?></div>
                <div style="font-size:15px;font-weight:700;margin-top:6px">Total: <?= formatPrice($viewOrder['total']) ?></div>
                <?php if ($viewOrder['notes']): ?>
                <div style="font-size:12px;margin-top:8px;background:var(--bg);padding:8px 10px;border-radius:6px;color:var(--text-2)">📝 <?= htmlspecialchars($viewOrder['notes']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items -->
        <div class="table-wrap" style="margin-bottom:20px">
        <table class="admin-table">
            <thead><tr><th>Product</th><th>Options</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($orderItems as $oi):
                $optData = json_decode($oi['variant_info'] ?? '{}', true);
                $optText = is_array($optData) ? ($optData['text'] ?? ($oi['variant_info'] ?? '')) : ($oi['variant_info'] ?? '');
            ?>
            <tr>
                <td style="font-weight:600"><?= htmlspecialchars($oi['product_name']) ?></td>
                <td style="font-size:12px;color:var(--text-2)"><?= $optText ? htmlspecialchars($optText) : '—' ?></td>
                <td><?= $oi['qty'] ?></td>
                <td><?= formatPrice($oi['price']) ?></td>
                <td><strong><?= formatPrice($oi['price'] * $oi['qty']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Status update -->
        <form method="POST" style="display:flex;gap:10px;align-items:center">
            <input type="hidden" name="order_id" value="<?= $viewOrder['id'] ?>">
            <label style="font-size:13px;font-weight:600;color:var(--text-2)">Update Status:</label>
            <select name="status" class="status-select">
                <?php foreach (['pending','processing','shipped','delivered','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $viewOrder['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="update_status" value="1" class="btn btn-primary btn-sm">
                <i class="fas fa-save"></i> Update
            </button>
            <span style="font-size:12px;color:var(--text-3)">Telegram notification will be sent automatically</span>
            <a href="<?= SITE_URL ?>/admin/invoice.php?id=<?= $viewOrder['id'] ?>" target="_blank" class="btn btn-ghost btn-sm" style="margin-left:auto">
                🖨️ Print Invoice
            </a>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Orders Table -->
<div class="card">
    <div class="table-wrap">
    <table class="admin-table">
        <thead>
            <tr><th>Order #</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
        <tr>
            <td><code style="font-size:11px;background:var(--bg);padding:2px 6px;border-radius:4px"><?= htmlspecialchars($o['order_number']) ?></code></td>
            <td>
                <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($o['name'] ?? 'Guest') ?></div>
                <div style="font-size:11px;color:var(--text-3)"><?= htmlspecialchars($o['phone'] ?? '') ?></div>
            </td>
            <td><strong><?= formatPrice($o['total']) ?></strong></td>
            <td>
                <?php 
                $pm = $o['payment_method'];
                if ($pm === 'cod'): ?>
                <span class="badge" style="background:#fef3c7;color:#d97706;border:1px solid #fde68a;font-size:10px">COD</span>
                <?php elseif (in_array($pm, ['stripe','paypal'])): ?>
                <span class="badge" style="background:#fff0f0;color:#dc2626;border:1px solid #fecaca;font-size:10px"><?= strtoupper($pm) ?></span>
                <?php else: ?>
                <span class="badge" style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;font-size:10px"><?= strtoupper($pm) ?></span>
                <?php endif; ?>
            </td>
            <td><?= sBadge($o['status']) ?></td>
            <td style="font-size:12px;color:var(--text-3)"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
            <td>
                <a href="?view=<?= $o['id'] ?><?= $statusFilter ? '&status='.$statusFilter : '' ?>" class="btn btn-ghost btn-sm">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="<?= SITE_URL ?>/admin/invoice.php?id=<?= $o['id'] ?>" target="_blank" class="btn btn-ghost btn-sm" style="font-size:11px">
                    🖨️
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($orders)): ?>
        <tr><td colspan="7" style="text-align:center;padding:50px;color:var(--text-3)">
            <i class="fas fa-inbox" style="font-size:32px;display:block;margin-bottom:10px;opacity:.3"></i>
            No orders found
        </td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require_once __DIR__ . '/admin-footer.php'; ?>
