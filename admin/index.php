<?php
$pageTitle = "Dashboard – TeaStore Admin";
require_once __DIR__ . '/admin-header.php';

try {
    $stats = [
        'products'  => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
        'orders'    => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'users'     => $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn(),
        'revenue'   => $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='cancelled'")->fetchColumn(),
        'pending'   => $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn(),
        'today'     => $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
        'today_rev' => $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(created_at)=CURDATE() AND status!='cancelled'")->fetchColumn(),
        'lowstock'  => $pdo->query("SELECT COUNT(*) FROM products WHERE stock<10 AND stock>=0")->fetchColumn(),
    ];
    $recentOrders  = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 8")->fetchAll();
    $ordersByStatus = $pdo->query("SELECT status,COUNT(*) as cnt FROM orders GROUP BY status ORDER BY cnt DESC")->fetchAll();
    $topProducts   = $pdo->query("SELECT p.name,p.image,p.tea_type,COALESCE(SUM(oi.qty),0) as sold FROM products p LEFT JOIN order_items oi ON p.id=oi.product_id GROUP BY p.id ORDER BY sold DESC LIMIT 5")->fetchAll();
    $lowStockItems = $pdo->query("SELECT id,name,stock FROM products WHERE stock<10 AND stock>=0 ORDER BY stock ASC LIMIT 5")->fetchAll();
    // Revenue last 7 days
    $weekRevenue = $pdo->query("SELECT DATE(created_at) as day, SUM(total) as rev, COUNT(*) as cnt FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status!='cancelled' GROUP BY DATE(created_at) ORDER BY day ASC")->fetchAll();
} catch (\Exception $e) {
    $stats = array_fill_keys(['products','orders','users','revenue','pending','today','today_rev','lowstock'], 0);
    $recentOrders = $ordersByStatus = $topProducts = $lowStockItems = $weekRevenue = [];
}

$statusColors = ['pending'=>'#f59e0b','processing'=>'#3b82f6','shipped'=>'#8b5cf6','delivered'=>'#22c55e','cancelled'=>'#ef4444'];
$statusEmoji  = ['pending'=>'⏳','processing'=>'⚙️','shipped'=>'🚚','delivered'=>'✅','cancelled'=>'❌'];
?>

<!-- ═══ PAGE HEADER ═══ -->
<div class="page-header">
    <div>
        <h1 style="font-size:22px;font-weight:800;letter-spacing:-.5px">Good <?=date('H')<12?'morning':'afternoon'?>, Admin 👋</h1>
        <p style="color:var(--text-2);font-size:13px;margin-top:3px"><?=date('l, F j, Y')?> · TeaStore Store Dashboard</p>
    </div>
    <div style="display:flex;gap:10px">
        <a href="<?= pretty_url('index.php') ?>" target="_blank" class="btn btn-ghost btn-sm"><i class="fas fa-external-link-alt"></i> View Store</a>
        <a href="<?= pretty_url('admin/product-form.php') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Product</a>
    </div>
</div>

<!-- ═══ STAT CARDS ═══ -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
    <?php
    $cards = [
        ['icon'=>'fa-box','label'=>'Products','val'=>number_format($stats['products']),'sub'=>$stats['lowstock']>0?"⚠️ {$stats['lowstock']} low stock":'✅ All stocked','color'=>'#eb1700','bg'=>'#fff0ee'],
        ['icon'=>'fa-shopping-bag','label'=>'Orders Today','val'=>number_format($stats['today']),'sub'=>formatPrice($stats['today_rev']).' today','color'=>'#3b82f6','bg'=>'#eff6ff'],
        ['icon'=>'fa-clock','label'=>'Pending Orders','val'=>number_format($stats['pending']),'sub'=>'Need your attention','color'=>'#f59e0b','bg'=>'#fffbeb'],
        ['icon'=>'fa-dollar-sign','label'=>'Total Revenue','val'=>formatPrice($stats['revenue']),'sub'=>number_format($stats['orders']).' orders total','color'=>'#22c55e','bg'=>'#f0fdf4'],
    ];
    foreach($cards as $c): ?>
    <div class="card" style="padding:20px;position:relative;overflow:hidden;transition:transform .15s,box-shadow .15s" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 32px rgba(0,0,0,.1)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
        <div style="position:absolute;top:-10px;right:-10px;width:70px;height:70px;border-radius:50%;background:<?=$c['bg']?>;opacity:.6"></div>
        <div style="width:38px;height:38px;border-radius:12px;background:<?=$c['bg']?>;display:flex;align-items:center;justify-content:center;margin-bottom:14px">
            <i class="fas <?=$c['icon']?>" style="color:<?=$c['color']?>;font-size:16px"></i>
        </div>
        <div style="font-size:24px;font-weight:800;color:<?=$c['color']?>;letter-spacing:-.5px;margin-bottom:2px"><?=$c['val']?></div>
        <div style="font-size:12px;font-weight:600;color:var(--text-2);margin-bottom:4px"><?=$c['label']?></div>
        <div style="font-size:11px;color:var(--text-3)"><?=$c['sub']?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ═══ MAIN GRID ═══ -->
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">

    <!-- Left: Recent Orders -->
    <div>
    <div class="card">
        <div class="card-header">
            <h3 style="font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px">
                <i class="fas fa-receipt" style="color:var(--p)"></i> Recent Orders
            </h3>
            <a href="<?= pretty_url('admin/orders.php') ?>" class="btn btn-ghost btn-sm">View All →</a>
        </div>
        <div style="overflow-x:auto">
        <table class="admin-table" style="width:100%">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($recentOrders as $o):
                $sc = $statusColors[$o['status']] ?? '#6b7280';
                $pm = $o['payment_method']==='stripe' ? '<span style="background:#fef2f2;color:#dc2626;padding:2px 7px;border-radius:6px;font-size:10px;font-weight:700">Stripe/PayPal</span>' : '<span style="background:#f0fdf4;color:#16a34a;padding:2px 7px;border-radius:6px;font-size:10px;font-weight:700">Online</span>';
            ?>
            <tr>
                <td><code style="font-size:11px;background:var(--bg);padding:3px 7px;border-radius:6px;color:var(--text)"><?=htmlspecialchars($o['order_number'])?></code></td>
                <td>
                    <div style="font-weight:600;font-size:13px"><?=htmlspecialchars($o['name']??'Guest')?></div>
                    <div style="font-size:11px;color:var(--text-3)"><?=htmlspecialchars($o['phone']??'')?></div>
                </td>
                <td style="font-weight:700;font-size:13px"><?=formatPrice($o['total'])?></td>
                <td><?=$pm?></td>
                <td>
                    <span style="background:<?=$sc?>18;color:<?=$sc?>;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap">
                        <?=($statusEmoji[$o['status']]??'📦')?> <?=ucfirst($o['status'])?>
                    </span>
                </td>
                <td style="font-size:11px;color:var(--text-3);white-space:nowrap"><?=date('M j, g:ia',strtotime($o['created_at']))?></td>
                <td><a href="?view=<?=$o['id']?>" class="btn btn-ghost btn-sm" style="padding:5px 10px;font-size:11px">View</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($recentOrders)): ?>
            <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-3)">No orders yet</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Revenue chart (last 7 days) -->
    <?php if(!empty($weekRevenue)): ?>
    <div class="card" style="margin-top:20px;padding:20px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
            <h3 style="font-size:14px;font-weight:700"><i class="fas fa-chart-bar" style="color:var(--p);margin-right:6px"></i>Revenue – Last 7 Days</h3>
        </div>
        <?php
        $maxRev = max(array_column($weekRevenue,'rev'));
        // Fill in missing days
        $days = []; for($i=6;$i>=0;$i--) $days[date('Y-m-d',strtotime("-{$i} days"))] = ['rev'=>0,'cnt'=>0];
        foreach($weekRevenue as $r) { if(isset($days[$r['day']])) $days[$r['day']] = $r; }
        ?>
        <div style="display:flex;align-items:flex-end;gap:8px;height:120px">
            <?php foreach($days as $day=>$r):
                $pct = $maxRev > 0 ? ($r['rev']/$maxRev)*100 : 0;
                $isToday = $day === date('Y-m-d');
            ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end">
                <div style="font-size:9px;color:var(--text-3);margin-bottom:4px;white-space:nowrap"><?=$r['rev']>0?formatPrice($r['rev']):''?></div>
                <div style="width:100%;background:<?=$isToday?'var(--p)':'#e5e7eb'?>;border-radius:6px 6px 0 0;transition:all .3s;min-height:4px;height:<?=max(4,$pct)?>%" title="<?=$r['cnt']?> orders · ".formatPrice($r['rev'])></div>
                <div style="font-size:9px;color:<?=$isToday?'var(--p)':'var(--text-3)'?>;font-weight:<?=$isToday?700:400?>;margin-top:6px"><?=date('D',strtotime($day))?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    </div>

    <!-- Right sidebar -->
    <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Order Status Breakdown -->
        <div class="card" style="padding:20px">
            <h3 style="font-size:13px;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:6px">
                <i class="fas fa-chart-pie" style="color:var(--p)"></i> Orders by Status
            </h3>
            <?php foreach($ordersByStatus as $row):
                $c = $statusColors[$row['status']] ?? '#6b7280';
                $pct = $stats['orders'] > 0 ? round($row['cnt']/$stats['orders']*100) : 0;
            ?>
            <div style="margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
                    <span style="font-weight:600;color:var(--text)"><?=($statusEmoji[$row['status']]??'📦')?> <?=ucfirst($row['status'])?></span>
                    <span style="color:var(--text-2)"><?=$row['cnt']?> (<?=$pct?>%)</span>
                </div>
                <div style="background:#f3f4f6;border-radius:6px;height:6px;overflow:hidden">
                    <div style="width:<?=$pct?>%;height:100%;background:<?=$c?>;border-radius:6px;transition:width .6s"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Low Stock Alert -->
        <?php if(!empty($lowStockItems)): ?>
        <div class="card" style="padding:20px;border:1.5px solid #fde68a">
            <h3 style="font-size:13px;font-weight:700;margin-bottom:14px;color:#92400e;display:flex;align-items:center;gap:6px">
                <i class="fas fa-exclamation-triangle" style="color:#f59e0b"></i> Low Stock Alert
            </h3>
            <?php foreach($lowStockItems as $p): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #fef3c7">
                <a href="<?= pretty_url('admin/product-form.php') ?>?id=<?=$p['id']?>" style="font-size:12px;font-weight:600;color:var(--text);hover:color:var(--p)"><?=htmlspecialchars($p['name'])?></a>
                <span style="font-size:11px;font-weight:800;padding:2px 8px;border-radius:6px;background:<?=$p['stock']===0?'#fef2f2':'#fffbeb'?>;color:<?=$p['stock']===0?'#dc2626':'#d97706'?>"><?=$p['stock']===0?'OUT':'Stock: '.$p['stock']?></span>
            </div>
            <?php endforeach; ?>
            <a href="<?= pretty_url('admin/products.php') ?>" class="btn btn-ghost btn-sm" style="margin-top:12px;width:100%;justify-content:center;font-size:11px">Manage Inventory →</a>
        </div>
        <?php endif; ?>

        <!-- Top Products -->
        <div class="card" style="padding:20px">
            <h3 style="font-size:13px;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:6px">
                <i class="fas fa-fire" style="color:var(--p)"></i> Top Products
            </h3>
            <?php foreach($topProducts as $i=>$p):
                $emoji = $p['tea_type']==='cat'?'🫖':($p['tea_type']==='dog'?'🍃':'🍵');
            ?>
            <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
                <div style="width:20px;font-size:11px;font-weight:800;color:var(--text-3);text-align:center"><?=$i+1?></div>
                <div style="width:32px;height:32px;border-radius:8px;background:var(--bg);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0">
                    <?php if($p['image']): ?>
                    <img src="<?=SITE_URL?>/assets/img/products/<?=htmlspecialchars($p['image'])?>" style="width:100%;height:100%;object-fit:cover">
                    <?php else: echo $emoji; endif; ?>
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($p['name'])?></div>
                    <div style="font-size:10px;color:var(--text-3)"><?=$p['sold']?> sold</div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($topProducts)): ?><p style="font-size:12px;color:var(--text-3);text-align:center;padding:12px 0">No sales data yet</p><?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="card" style="padding:20px">
            <h3 style="font-size:13px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:6px">
                <i class="fas fa-bolt" style="color:var(--p)"></i> Quick Actions
            </h3>
            <div style="display:flex;flex-direction:column;gap:8px">
                <a href="<?= pretty_url('admin/product-form.php') ?>" class="btn btn-primary btn-sm" style="justify-content:center"><i class="fas fa-plus"></i> New Product</a>
                <a href="<?= pretty_url('admin/orders.php') ?>?status=pending" class="btn btn-ghost btn-sm" style="justify-content:center;background:#fffbeb;color:#d97706;border:1px solid #fde68a"><i class="fas fa-clock"></i> View Pending Orders</a>
                <a href="<?= pretty_url('admin/settings.php') ?>?tab=notifications" class="btn btn-ghost btn-sm" style="justify-content:center;background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe"><i class="fab fa-telegram"></i> Telegram Settings</a>
                <a href="<?= pretty_url('admin/settings.php') ?>?tab=tools&action=backup" class="btn btn-ghost btn-sm" style="justify-content:center"><i class="fas fa-download"></i> Backup Database</a>
            </div>
        </div>

    </div><!-- right sidebar -->
</div><!-- main grid -->

<?php require_once __DIR__ . '/admin-footer.php'; ?>
