<?php
$pageTitle = "Reports – TeaStore Admin";
require_once __DIR__ . '/../includes/config.php';
if (!canAccessAdmin()) { flash('error','Access required'); redirect(pretty_url('pages/login.php')); }

// CSV exports
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    $qs = [
        'orders'   => "SELECT o.order_number,o.name,o.phone,o.email,o.address,o.payment_method,o.payment_status,o.status,o.subtotal,o.shipping,o.total,o.created_at FROM orders o ORDER BY o.created_at DESC",
        'revenue'  => "SELECT DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue FROM orders WHERE status!='cancelled' GROUP BY DATE(created_at) ORDER BY date DESC",
        'products' => "SELECT p.name,p.price,p.sale_price,p.stock,b.name as brand,c.name as category,p.tea_type FROM products p LEFT JOIN brands b ON p.brand_id=b.id LEFT JOIN categories c ON p.category_id=c.id",
        'customers'=> "SELECT u.name,u.email,u.phone,u.created_at,COUNT(o.id) as orders,COALESCE(SUM(o.total),0) as total_spent FROM users u LEFT JOIN orders o ON o.user_id=u.id WHERE u.role='customer' GROUP BY u.id ORDER BY total_spent DESC",
    ];
    if (isset($qs[$type])) {
        $rows = $pdo->query($qs[$type])->fetchAll();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="teastore-'.$type.'-'.date('Y-m-d').'.csv"');
        if ($rows) {
            $f = fopen('php://output','w');
            fputcsv($f, array_keys($rows[0]));
            foreach ($rows as $r) fputcsv($f, $r);
            fclose($f);
        }
        exit;
    }
}

// Stats
try {
    $period = $_GET['period'] ?? '30';
    $period = in_array($period, ['7','30','90','365']) ? (int)$period : 30;
    
    $totalRevenue = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL $period DAY)")->fetchColumn();
    $totalOrders  = $pdo->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL $period DAY)")->fetchColumn();
    $avgOrder     = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
    $newCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer' AND created_at >= DATE_SUB(NOW(), INTERVAL $period DAY)")->fetchColumn();

    $dailyRevenue = $pdo->query("SELECT DATE(created_at) as day, COUNT(*) as orders, COALESCE(SUM(total),0) as rev FROM orders WHERE status!='cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL $period DAY) GROUP BY DATE(created_at) ORDER BY day ASC")->fetchAll();
    $topProducts  = $pdo->query("SELECT p.name, SUM(oi.qty) as sold, SUM(oi.qty * oi.price) as revenue FROM order_items oi JOIN products p ON p.id=oi.product_id JOIN orders o ON o.id=oi.order_id WHERE o.status!='cancelled' AND o.created_at >= DATE_SUB(NOW(), INTERVAL $period DAY) GROUP BY p.id ORDER BY sold DESC LIMIT 10")->fetchAll();
    $statusBreakdown = $pdo->query("SELECT status, COUNT(*) as cnt FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL $period DAY) GROUP BY status")->fetchAll();
    $paymentBreakdown = $pdo->query("SELECT payment_method, COUNT(*) as cnt, SUM(total) as total FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL $period DAY) GROUP BY payment_method")->fetchAll();
} catch(Throwable $e) {
    $totalRevenue=$totalOrders=$avgOrder=$newCustomers=0;
    $dailyRevenue=$topProducts=$statusBreakdown=$paymentBreakdown=[];
}

require_once __DIR__ . '/admin-header.php';
?>
<style>
.rep-card{background:#fff;border:1.5px solid #edf0f3;border-radius:18px;padding:20px 24px;box-shadow:0 2px 12px rgba(0,0,0,.04)}
.rep-stat{display:flex;flex-direction:column}
.rep-stat .val{font-size:28px;font-weight:900;letter-spacing:-1px;color:#0f172a}
.rep-stat .lbl{font-size:12px;color:#64748b;margin-top:4px;font-weight:600}
.rep-bar-wrap{display:flex;align-items:center;gap:12px;margin-bottom:8px}
.rep-bar-bg{flex:1;height:10px;background:#f1f5f9;border-radius:5px;overflow:hidden}
.rep-bar-fill{height:100%;border-radius:5px;background:linear-gradient(90deg,#eb1700,#ff6b4a);transition:.3s}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <div>
        <h1 style="font-size:22px;font-weight:900;color:#0f172a;margin:0">📊 Reports</h1>
        <p style="font-size:13px;color:#64748b;margin-top:3px">Sales insights and data exports</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <?php foreach (['7'=>'7 days','30'=>'30 days','90'=>'90 days','365'=>'1 year'] as $d=>$l): ?>
        <a href="?period=<?= $d ?>" class="btn btn-sm <?= $period==(int)$d?'btn-primary':'btn-ghost' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Summary Stats -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
    <?php $metrics = [
        ['💰','Revenue', formatPrice($totalRevenue)],
        ['📦','Orders', number_format($totalOrders)],
        ['📈','Avg Order', formatPrice($avgOrder)],
        ['👤','New Customers', number_format($newCustomers)],
    ]; foreach ($metrics as [$icon,$lbl,$val]): ?>
    <div class="rep-card rep-stat">
        <div style="font-size:22px;margin-bottom:6px"><?= $icon ?></div>
        <div class="val"><?= $val ?></div>
        <div class="lbl"><?= $lbl ?> (last <?= $period ?> days)</div>
    </div>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px">
    <!-- Top Products -->
    <div class="rep-card">
        <h3 style="font-size:14px;font-weight:800;margin:0 0 16px">🏆 Top Products (last <?= $period ?> days)</h3>
        <?php if (empty($topProducts)): ?>
        <p style="color:#94a3b8;font-size:13px">No data yet</p>
        <?php else:
        $maxSold = max(array_column($topProducts,'sold')) ?: 1;
        foreach ($topProducts as $i => $tp): ?>
        <div class="rep-bar-wrap">
            <div style="width:20px;font-size:11px;font-weight:700;color:#94a3b8;text-align:right"><?= $i+1 ?></div>
            <div style="flex:1">
                <div style="font-size:12px;font-weight:700;color:#1e293b;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($tp['name']) ?></div>
                <div class="rep-bar-bg"><div class="rep-bar-fill" style="width:<?= round($tp['sold']/$maxSold*100) ?>%"></div></div>
            </div>
            <div style="text-align:right;font-size:12px">
                <div style="font-weight:700;color:#0f172a"><?= $tp['sold'] ?> sold</div>
                <div style="color:#64748b"><?= formatPrice($tp['revenue']) ?></div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Order Status & Payment Breakdown -->
    <div style="display:flex;flex-direction:column;gap:16px">
        <div class="rep-card">
            <h3 style="font-size:14px;font-weight:800;margin:0 0 14px">📋 Order Status</h3>
            <?php
            $sColors=['pending'=>'#f59e0b','processing'=>'#3b82f6','shipped'=>'#8b5cf6','delivered'=>'#22c55e','cancelled'=>'#ef4444'];
            foreach ($statusBreakdown as $s): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <span style="display:flex;align-items:center;gap:6px;font-size:13px">
                    <span style="width:10px;height:10px;border-radius:50%;background:<?= $sColors[$s['status']] ?? '#94a3b8' ?>;display:inline-block"></span>
                    <?= ucfirst($s['status']) ?>
                </span>
                <strong style="font-size:13px"><?= $s['cnt'] ?></strong>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="rep-card">
            <h3 style="font-size:14px;font-weight:800;margin:0 0 14px">💳 Payment Method</h3>
            <?php foreach ($paymentBreakdown as $p): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <span style="font-size:13px"><?= strtoupper($p['payment_method']) ?></span>
                <div style="text-align:right">
                    <strong style="font-size:13px"><?= $p['cnt'] ?> orders</strong>
                    <div style="font-size:11px;color:#64748b"><?= formatPrice($p['total']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Daily Revenue Table -->
<?php if (!empty($dailyRevenue)): ?>
<div class="rep-card" style="margin-bottom:20px">
    <h3 style="font-size:14px;font-weight:800;margin:0 0 16px">📅 Daily Revenue</h3>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead><tr>
            <th style="text-align:left;padding:8px 12px;background:#f8fafc;border-bottom:2px solid #edf0f3;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b">Date</th>
            <th style="text-align:right;padding:8px 12px;background:#f8fafc;border-bottom:2px solid #edf0f3;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b">Orders</th>
            <th style="text-align:right;padding:8px 12px;background:#f8fafc;border-bottom:2px solid #edf0f3;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b">Revenue</th>
        </tr></thead>
        <tbody>
        <?php foreach (array_reverse($dailyRevenue) as $d): ?>
        <tr>
            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;color:#374151"><?= date('D, d M Y', strtotime($d['day'])) ?></td>
            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;text-align:right;font-weight:600"><?= $d['orders'] ?></td>
            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;text-align:right;font-weight:700;color:#eb1700"><?= formatPrice($d['rev']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Export CSV -->
<div class="rep-card">
    <h3 style="font-size:14px;font-weight:800;margin:0 0 12px">⬇️ Export Data as CSV</h3>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="?export=orders" class="btn btn-ghost btn-sm"><i class="fas fa-file-csv"></i> Orders CSV</a>
        <a href="?export=revenue" class="btn btn-ghost btn-sm"><i class="fas fa-file-csv"></i> Revenue CSV</a>
        <a href="?export=products" class="btn btn-ghost btn-sm"><i class="fas fa-file-csv"></i> Products CSV</a>
        <a href="?export=customers" class="btn btn-ghost btn-sm"><i class="fas fa-file-csv"></i> Customers CSV</a>
    </div>
</div>

<?php require_once __DIR__ . '/admin-footer.php'; ?>
