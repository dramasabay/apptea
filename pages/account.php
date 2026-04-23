<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = "My Account – TeaStore";
if (!isLoggedIn()) redirect(pretty_url('pages/login.php'));

$user = $pdo->prepare("SELECT * FROM users WHERE id=?");
$user->execute([$_SESSION['user_id']]);
$user = $user->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');

    if ($name) {
        $upd = $pdo->prepare("UPDATE users SET name=?,phone=?,address=? WHERE id=?");
        $upd->execute([$name, $phone, $address, $user['id']]);
        $_SESSION['user_name'] = $name;
        flash('success', 'Profile updated!');
        redirect(pretty_url('pages/account.php'));
    }
}

// Orders
$orders = $pdo->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$orders->execute([$user['id']]);
$orders = $orders->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<meta name="site-url" content="<?= SITE_URL ?>">

<div class="container" style="padding-top:30px;padding-bottom:60px;">
    <h1 class="page-title">My Account</h1>
    <div class="account-layout">
        <!-- Sidebar -->
        <aside class="account-sidebar">
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper($user['name'][0]) ?></div>
                <strong style="font-size:14px;"><?= htmlspecialchars($user['name']) ?></strong>
                <div style="font-size:12px;color:var(--text-light);margin-top:4px;"><?= htmlspecialchars($user['email']) ?></div>
            </div>
            <nav class="account-menu">
                <ul>
                    <li><a href="<?= pretty_url('pages/account.php') ?>" class="active"><i class="fas fa-user" style="width:18px;"></i> Profile</a></li>
                    <li><a href="<?= pretty_url('pages/orders.php') ?>"><i class="fas fa-box" style="width:18px;"></i> My Orders</a></li>
                    <li><a href="<?= pretty_url('pages/wishlist.php') ?>"><i class="fas fa-heart" style="width:18px;"></i> Wishlist</a></li>
                    <?php if (isAdmin()): ?><li><a href="<?= pretty_url('admin/index.php') ?>"><i class="fas fa-cog" style="width:18px;"></i> Admin Panel</a></li><?php endif; ?>
                    <li><a href="<?= pretty_url('pages/logout.php') ?>" style="color:var(--red);"><i class="fas fa-sign-out-alt" style="width:18px;"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Content -->
        <div>
            <!-- Profile -->
            <div style="background:var(--white);border:1px solid var(--border);border-radius:8px;padding:24px;margin-bottom:24px;">
                <h2 style="font-size:16px;font-weight:700;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border);">Profile Information</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+855 xx xxx xxxx">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email (read only)</label>
                        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly style="background:#f5f5f5;">
                    </div>
                    <div class="form-group">
                        <label>Default Address</label>
                        <textarea name="address" placeholder="Your delivery address..."><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>

            <!-- Recent Orders -->
            <div style="background:var(--white);border:1px solid var(--border);border-radius:8px;padding:24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border);">
                    <h2 style="font-size:16px;font-weight:700;">Recent Orders</h2>
                    <a href="<?= pretty_url('pages/orders.php') ?>" class="view-all">View All →</a>
                </div>
                <?php if (empty($orders)): ?>
                <div class="empty-state" style="padding:30px;">
                    <div class="icon">📦</div>
                    <p>No orders yet. <a href="<?= pretty_url('pages/shop.php') ?>" style="color:var(--primary)">Start shopping!</a></p>
                </div>
                <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr><th>Order</th><th>Date</th><th>Total</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($order['order_number']) ?></strong></td>
                            <td style="font-size:12px;"><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                            <td><?= formatPrice($order['total']) ?></td>
                            <td><span class="status-badge status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
