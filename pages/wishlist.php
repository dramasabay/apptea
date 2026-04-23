<?php
require_once __DIR__ . '/../includes/config.php';

if (!isLoggedIn()) {
    flash('error', 'Please login to access your wishlist');
    redirect(pretty_url('pages/login.php'));
}

$pageTitle = "Wishlist – TeaStore";

$stmt = $pdo->prepare("SELECT p.*, b.name AS brand_name FROM wishlist w JOIN products p ON w.product_id=p.id LEFT JOIN brands b ON p.brand_id=b.id WHERE w.user_id=? ORDER BY w.id DESC");
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<meta name="site-url" content="<?= SITE_URL ?>">

<style>
.wishlist-shell{padding:34px 0 60px}
.wishlist-heading{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:24px}
.wishlist-heading h1{font-size:40px;letter-spacing:-1px;font-weight:900}
.wishlist-chip{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;background:#fff5f4;border:1px solid #ffd7d2;color:var(--primary);font-size:12px;font-weight:800}
.wishlist-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}
.wishlist-card{background:#fff;border:1px solid #eaecef;border-radius:22px;overflow:hidden;box-shadow:0 14px 36px rgba(15,23,42,.05);transition:.2s}
.wishlist-card:hover{transform:translateY(-3px);box-shadow:0 20px 40px rgba(15,23,42,.08)}
.wishlist-card .product-img-wrap{aspect-ratio:1;background:#f8fafc}
.wishlist-card .product-info{padding:16px}
.wishlist-card .product-name{font-size:18px;font-weight:800;line-height:1.35;margin:8px 0 6px}
.wishlist-card .product-name a{color:#111827}
.wishlist-card .product-price{margin-top:10px}
.wishlist-actions{display:flex;gap:10px;margin-top:14px}
.wishlist-actions .btn{height:44px;border-radius:14px}
.wishlist-actions .btn-outline{border:1px solid var(--border);background:#fff;color:#dc2626;width:44px;display:inline-flex;align-items:center;justify-content:center}
.empty-state{background:#fff;border:1px solid #eaecef;border-radius:24px;padding:48px 22px;box-shadow:0 14px 36px rgba(15,23,42,.05)}
@media(max-width:1024px){.wishlist-grid{grid-template-columns:repeat(3,minmax(0,1fr));}}
@media(max-width:768px){.wishlist-heading h1{font-size:30px}.wishlist-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}}
@media(max-width:520px){.wishlist-grid{grid-template-columns:1fr}.wishlist-actions{flex-wrap:wrap}}
</style>

<div class="container wishlist-shell">
    <div class="wishlist-heading"><div><h1>My Wishlist ❤️</h1><p style="color:#64748b;margin-top:8px;font-size:14px;">Save favorites and move them to cart anytime.</p></div><div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><div class="wishlist-chip"><i class="fas fa-heart"></i> <?= count($items) ?> saved item<?= count($items)===1?"":"s" ?></div><div class="wishlist-chip" style="background:#f0fdf4;border-color:#bbf7d0;color:#15803d"><i class="fas fa-sack-dollar"></i> Budget <?= formatPrice(array_sum(array_map(fn($x)=>(float)($x['sale_price'] ?? $x['price'] ?? 0), $items))) ?></div></div></div>
    <?php if (empty($items)): ?>
    <div class="empty-state">
        <div class="icon">🤍</div>
        <h3>Your wishlist is empty</h3>
        <p>Save products you love to buy later.</p>
        <a href="<?= pretty_url('pages/shop.php') ?>" class="btn btn-primary" style="margin-top:20px;">Browse Products</a>
    </div>
    <?php else: ?>
    <div class="wishlist-grid">
        <?php foreach ($items as $p):
            $price = $p['sale_price'] ?? $p['price'];
            $hasDiscount = $p['sale_price'] !== null;
            $teaEmoji = $p['tea_type']==='green'?'🍃':($p['tea_type']==='black'?'🫖':'🍵');
        ?>
        <div class="wishlist-card" style="position:relative;">
            <a href="<?= pretty_url('pages/product.php') ?>?slug=<?= urlencode($p['slug']) ?>">
                <div class="product-img-wrap">
                    <?php if ($p['image']): ?>
                        <img src="<?= SITE_URL ?>/assets/img/products/<?= htmlspecialchars($p['image']) ?>" alt="">
                    <?php else: ?>
                        <div class="product-no-img"><?= $teaEmoji ?></div>
                    <?php endif; ?>
                </div>
            </a>
            <div class="product-info">
                <p class="product-meta"><?= htmlspecialchars($p['brand_name'] ?? '') ?></p>
                <h3 class="product-name"><a href="<?= pretty_url('pages/product.php') ?>?slug=<?= urlencode($p['slug']) ?>"><?= htmlspecialchars($p['name']) ?></a></h3>
                <div class="product-price">
                    <span class="price-current"><?= formatPrice($price) ?></span>
                    <?php if ($hasDiscount): ?><span class="price-old"><?= formatPrice($p['price']) ?></span><?php endif; ?>
                </div>
                <div class="wishlist-actions">
                    <button class="btn btn-primary" style="flex:1;justify-content:center;border:none;" type="button" onclick="addToCart(<?= $p['id'] ?>)"><i class="fas fa-bag-shopping"></i> Add to Cart</button>
                    <form method="POST" action="<?= pretty_url('pages/wishlist-action.php') ?>" style="display:inline;">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="action" value="remove">
                        <button type="submit" class="btn btn-outline" title="Remove"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
