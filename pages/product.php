<?php
require_once __DIR__ . '/../includes/config.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT p.*, b.name AS brand_name, c.name AS cat_name, c.slug AS cat_slug
    FROM products p
    LEFT JOIN brands b ON p.brand_id=b.id
    LEFT JOIN categories c ON p.category_id=c.id
    WHERE p.slug=?");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    header("HTTP/1.0 404 Not Found");
    include __DIR__ . '/../includes/header.php';
    echo '<div class="container"><div class="empty-state"><div class="icon">🔍</div><h3>Product Not Found</h3><p><a href="'.SITE_URL.'/pages/shop.php" style="color:var(--primary)">Browse all products</a></p></div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$pageTitle = $product['name'] . ' – TeaStore';
$price      = (float)($product['sale_price'] ?? $product['price']);
$hasDiscount = $product['sale_price'] !== null;
$discount   = $hasDiscount ? round((1 - $product['sale_price'] / $product['price']) * 100) : 0;
$teaEmoji   = $product['tea_type'] === 'green' ? '🍃' : ($product['tea_type'] === 'black' ? '🫖' : '🍵');

// Gallery images
$galleryImages = [];
try {
    $gStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id=? ORDER BY sort_order,id");
    $gStmt->execute([$product['id']]);
    $galleryImages = $gStmt->fetchAll();
} catch (Throwable $e) {}

// Build all images array: main image first, then gallery
$allImages = [];
if (!empty($product['image'])) $allImages[] = $product['image'];
foreach ($galleryImages as $gi) {
    if ($gi['filename'] !== ($product['image'] ?? '')) $allImages[] = $gi['filename'];
}

// Variants
$variants = $pdo->prepare("SELECT * FROM product_variants WHERE product_id=? ORDER BY attribute_name, id");
$variants->execute([$product['id']]);
$variants = $variants->fetchAll();
$variantGroups = [];
foreach ($variants as $v) $variantGroups[$v['attribute_name']][] = $v;

// Option groups
$optionGroupsStmt = $pdo->prepare("SELECT * FROM product_option_groups WHERE product_id=? ORDER BY sort_order, id");
$optionGroupsStmt->execute([$product['id']]);
$optionGroups = $optionGroupsStmt->fetchAll();
foreach ($optionGroups as &$g) {
    $iStmt = $pdo->prepare("SELECT * FROM product_option_items WHERE group_id=? ORDER BY sort_order, id");
    $iStmt->execute([$g['id']]);
    $g['items'] = $iStmt->fetchAll();
}
unset($g);

// Quantity discount tiers
$discountTiers = [];
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_quantity_discounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        min_qty INT NOT NULL DEFAULT 5,
        discount_pct DECIMAL(5,2) NOT NULL DEFAULT 5.00,
        sort_order INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $dtStmt = $pdo->prepare("SELECT * FROM product_quantity_discounts WHERE product_id=? ORDER BY min_qty");
    $dtStmt->execute([$product['id']]);
    $discountTiers = $dtStmt->fetchAll();
} catch (Throwable $e) {}

// Related products
$related = $pdo->prepare("SELECT p.*, b.name AS brand_name FROM products p LEFT JOIN brands b ON p.brand_id=b.id WHERE p.category_id=? AND p.id != ? ORDER BY RAND() LIMIT 4");
$related->execute([$product['category_id'], $product['id']]);
$related = $related->fetchAll();

// Reviews
$reviews = [];
try {
    $revStmt = $pdo->prepare("SELECT r.*, u.name as user_name FROM reviews r JOIN users u ON r.user_id=u.id WHERE r.product_id=? ORDER BY r.created_at DESC");
    $revStmt->execute([$product['id']]);
    $reviews = $revStmt->fetchAll();
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    if (!isLoggedIn()) { flash('error', 'Please login to leave a review'); redirect(pretty_url('pages/login.php')); }
    $rating  = (int)($_POST['rating'] ?? 5);
    $comment = sanitize($_POST['comment'] ?? '');
    try {
        $pdo->prepare("INSERT INTO reviews (product_id,user_id,rating,comment) VALUES (?,?,?,?)")
            ->execute([$product['id'], $_SESSION['user_id'], $rating, $comment]);
    } catch (Throwable $e) {}
    flash('success', 'Review submitted!');
    redirect(pretty_url('pages/product.php') . '?slug=' . urlencode($slug));
}

function renderMiniCard(array $p): string {
    $siteUrl = SITE_URL;
    $price   = $p['sale_price'] ?? $p['price'];
    $disc    = $p['sale_price'] ? round((1 - $p['sale_price']/$p['price']) * 100) : 0;
    $emoji   = $p['tea_type'] === 'green' ? '🍃' : ($p['tea_type'] === 'black' ? '🫖' : '🍵');
    ob_start(); ?>
    <div class="product-card" onclick="openModal(<?= (int)$p['id'] ?>)">
        <div class="product-badge">
            <?php if (!empty($p['is_new'])): ?><span class="badge badge-new">New</span><?php endif; ?>
            <?php if ($disc): ?><span class="badge badge-sale">-<?= $disc ?>%</span><?php endif; ?>
        </div>
        <div class="product-img-wrap">
            <?php if (!empty($p['image'])): ?>
                <img src="<?= $siteUrl ?>/assets/img/products/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
            <?php else: ?>
                <div class="product-no-img"><?= $emoji ?></div>
            <?php endif; ?>
            <div class="product-hover-overlay">
                <button class="product-hover-btn product-hover-btn-primary" onclick="event.stopPropagation();openModal(<?= (int)$p['id'] ?>)" type="button"><i class="fas fa-plus"></i> Quick add</button>
                <a class="product-hover-btn" href="<?= $siteUrl ?>/product?slug=<?= urlencode($p['slug']) ?>" onclick="event.stopPropagation()"><i class="fas fa-eye"></i> View</a>
            </div>
            <button class="product-plus-btn" onclick="event.stopPropagation();openModal(<?= (int)$p['id'] ?>)" type="button">+</button>
        </div>
        <div class="product-info">
            <?php if (!empty($p['brand_name'])): ?><div class="product-meta"><?= htmlspecialchars($p['brand_name']) ?></div><?php endif; ?>
            <div class="product-name-text"><?= htmlspecialchars($p['name']) ?></div>
            <div class="product-price-row">
                <div class="product-price">
                    <span class="price-current"><?= formatPrice($price) ?></span>
                    <?php if ($disc): ?><span class="price-old"><?= formatPrice($p['price']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}

require_once __DIR__ . '/../includes/header.php';
// Extra meta for cart endpoint on this page
?>
<meta name="site-url" content="<?= SITE_URL ?>">
<meta name="cart-action-url" content="<?= SITE_URL ?>/pages/cart-action.php">
<style>
/* ─── Layout ──────────────────────────────── */
.pdp-shell{padding:24px 0 60px}
.pdp-grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(300px,.95fr);gap:32px;align-items:start}
/* ─── Gallery ─────────────────────────────── */
.pdp-gallery-card{background:#fff;border:1px solid #edf0f3;border-radius:24px;padding:20px;box-shadow:0 12px 32px rgba(15,23,42,.05)}
.pdp-main-img{aspect-ratio:1/1;border-radius:18px;background:linear-gradient(180deg,#f8fafc,#eff2f7);display:flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:14px;cursor:zoom-in;position:relative}
.pdp-main-img img{width:100%;height:100%;object-fit:cover;transition:transform .3s ease}
.pdp-main-img:hover img{transform:scale(1.04)}
.pdp-main-img .no-img{font-size:110px}
.pdp-thumb-row{display:flex;gap:8px;flex-wrap:wrap}
.pdp-thumb{width:72px;height:72px;border-radius:12px;overflow:hidden;border:2.5px solid transparent;background:#f8fafc;cursor:pointer;flex-shrink:0;transition:.2s;display:flex;align-items:center;justify-content:center}
.pdp-thumb:hover{border-color:#cbd5e1}
.pdp-thumb.active{border-color:var(--primary);box-shadow:0 0 0 3px rgba(235,23,0,.12)}
.pdp-thumb img{width:100%;height:100%;object-fit:cover}
.pdp-thumb-emoji{font-size:30px}
/* Navigation arrows for gallery */
.pdp-gallery-nav{display:flex;justify-content:space-between;align-items:center;margin-top:10px;padding:0 2px}
.pdp-nav-btn{width:32px;height:32px;border-radius:50%;border:1.5px solid #e2e8f0;background:#fff;color:#374151;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;transition:.15s}
.pdp-nav-btn:hover{background:#f8fafc;border-color:#94a3b8}
.pdp-img-counter{font-size:12px;color:#94a3b8;font-weight:600}
/* Lightbox */
#imgLightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:99990;align-items:center;justify-content:center;backdrop-filter:blur(6px)}
#imgLightbox.open{display:flex}
#imgLightbox img{max-width:90vw;max-height:88vh;object-fit:contain;border-radius:12px;box-shadow:0 32px 80px rgba(0,0,0,.5)}
#imgLightbox .lb-close{position:fixed;top:18px;right:22px;width:40px;height:40px;background:rgba(255,255,255,.15);border:none;border-radius:50%;color:#fff;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center}
#imgLightbox .lb-prev,#imgLightbox .lb-next{position:fixed;top:50%;transform:translateY(-50%);width:46px;height:46px;background:rgba(255,255,255,.15);border:none;border-radius:50%;color:#fff;font-size:22px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.15s}
#imgLightbox .lb-prev{left:18px}#imgLightbox .lb-next{right:18px}
#imgLightbox .lb-prev:hover,#imgLightbox .lb-next:hover{background:rgba(255,255,255,.28)}
/* ─── Panel ───────────────────────────────── */
.pdp-panel{background:#fff;border:1px solid #edf0f3;border-radius:24px;padding:26px;box-shadow:0 12px 32px rgba(15,23,42,.05)}
.pdp-title{font-size:28px;line-height:1.2;letter-spacing:-.7px;font-weight:800;margin:8px 0 14px;color:#0f172a}
.pdp-meta-row{display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;font-size:13px;color:#64748b;margin-bottom:14px}
.pdp-pill{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border-radius:999px;background:#f1f5f9;border:1px solid #e2e8f0;font-size:11px;font-weight:700;color:#475569}
.pdp-stock-ok{color:#15803d;font-weight:700}
.pdp-price-row{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.pdp-price-main{font-size:36px;font-weight:900;letter-spacing:-1px;color:#0f172a}
.pdp-price-old{font-size:17px;color:#94a3b8;text-decoration:line-through}
.pdp-short-desc{font-size:14px;line-height:1.75;color:#526071;margin:0 0 18px}
/* Options */
.pdp-option-block{padding:16px;border:1.5px solid #edf0f3;border-radius:18px;background:#fff;margin-bottom:12px;transition:.2s}
.pdp-option-block.error{border-color:var(--primary);background:#fff8f8}
.pdp-option-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:12px}
.pdp-option-title{font-size:14px;font-weight:800;color:#172033}
.pdp-option-sub{font-size:11px;color:#7b8794;margin-top:2px}
.pdp-req-chip{background:#0f172a;color:#fff;font-size:10px;font-weight:700;padding:3px 9px;border-radius:20px;white-space:nowrap}
.pdp-opt-chip{background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;font-size:10px;font-weight:700;padding:3px 9px;border-radius:20px;white-space:nowrap}
.pdp-choice{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border:1.5px solid #e5e7eb;border-radius:14px;cursor:pointer;transition:.15s;background:#fff;margin-bottom:8px}
.pdp-choice:last-child{margin-bottom:0}
.pdp-choice:hover{border-color:#f5b8ae;background:#fff9f8}
.pdp-choice.active{border-color:var(--primary);box-shadow:0 0 0 3px rgba(235,23,0,.07);background:#fff8f8}
.pdp-choice-left{display:flex;align-items:center;gap:10px;min-width:0}
.pdp-choice-mark{width:18px;height:18px;border:2px solid #cbd5e1;border-radius:50%;flex-shrink:0;background:#fff;transition:.15s}
.pdp-choice.active .pdp-choice-mark{border-color:var(--primary);background:var(--primary)}
.pdp-choice.active .pdp-choice-mark::after{content:'';display:block;width:6px;height:6px;border-radius:50%;background:#fff;margin:4px auto}
.pdp-choice.multi .pdp-choice-mark{border-radius:6px}
.pdp-choice.multi.active .pdp-choice-mark::after{width:10px;height:8px;border-radius:2px;margin:3px auto;background:#fff}
.pdp-choice-name{font-size:13px;font-weight:600;color:#273142}
.pdp-choice-rec{font-size:10px;color:#16a34a;font-weight:700;margin-top:2px}
.pdp-choice-price{font-size:13px;font-weight:700;color:#0f172a;white-space:nowrap}
/* Buy row */
.pdp-buy-row{display:grid;grid-template-columns:116px 1fr;gap:12px;margin:18px 0 14px}
.pdp-qty{display:flex;align-items:center;justify-content:space-between;padding:6px;border:1.5px solid #e2e8f0;border-radius:16px;background:#fff}
.pdp-qty button{width:38px;height:38px;border:none;border-radius:12px;background:#f8fafc;color:#0f172a;font-size:18px;font-weight:800;cursor:pointer;transition:.15s}
.pdp-qty button:hover{background:#eef2f7}
.pdp-qty input{width:32px;border:none;background:transparent;text-align:center;font-size:17px;font-weight:700;outline:none;color:#111827}
.pdp-cart-btn{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:0 20px;border:none;border-radius:16px;background:var(--primary);color:#fff;cursor:pointer;box-shadow:0 16px 32px rgba(0,0,0,.18);font-size:15px;font-weight:700;min-height:54px;transition:.2s;font-family:inherit}
.pdp-cart-btn:hover{background:var(--primary-dark);transform:translateY(-1px);box-shadow:0 20px 36px rgba(0,0,0,.22)}
.pdp-cart-btn[disabled]{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none}
.pdp-cart-total{font-size:16px;font-weight:800}
.pdp-actions{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px}
.pdp-secondary-btn{display:inline-flex;align-items:center;gap:7px;padding:11px 15px;border:1.5px solid #e2e8f0;border-radius:14px;background:#fff;color:#273142;font-weight:600;font-size:13px;text-decoration:none;transition:.15s}
.pdp-secondary-btn:hover{background:#f8fafc}
.pdp-benefits{background:#f8fafc;border:1.5px solid #edf0f3;border-radius:16px;padding:16px 18px;margin-bottom:16px}
.pdp-benefits li{display:flex;align-items:center;gap:10px;font-size:13px;color:#475569;padding:5px 0;list-style:none}
.pdp-desc-block{padding-top:18px;border-top:1.5px solid #f1f5f9;margin-top:4px}
.pdp-desc-block h3{font-size:16px;font-weight:800;color:#172033;margin:0 0 10px}
.pdp-desc-block p,.pdp-desc-block div{font-size:14px;line-height:1.8;color:#526071}
/* Reviews */
.pdp-section-title{font-size:24px;font-weight:800;letter-spacing:-.6px;margin-bottom:18px;color:#172033}
.pdp-review-card{background:#fff;border:1px solid #edf0f3;border-radius:18px;padding:18px 20px;margin-bottom:14px;box-shadow:0 4px 12px rgba(15,23,42,.04)}
.pdp-review-meta{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:8px}
.pdp-review-name{font-size:13px;font-weight:700;color:#172033}
.pdp-review-body{font-size:14px;color:#526071;line-height:1.7}
.pdp-related .product-grid{grid-template-columns:repeat(4,1fr)}
@media(max-width:980px){
    .pdp-grid{grid-template-columns:1fr}
    .pdp-title{font-size:24px}
    .pdp-related .product-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:640px){
    .pdp-shell{padding:14px 0 42px}
    .pdp-gallery-card,.pdp-panel{border-radius:18px;padding:16px}
    .pdp-title{font-size:22px}
    .pdp-price-main{font-size:30px}
    .pdp-buy-row{grid-template-columns:1fr}
    .pdp-related .product-grid{grid-template-columns:repeat(2,1fr);gap:10px}
    .pdp-thumb{width:58px;height:58px}
}
</style>

<div class="container pdp-shell">
    <!-- Breadcrumb -->
    <div class="breadcrumb" style="margin-bottom:20px">
        <a href="<?= SITE_URL ?>/">Home</a><span>›</span>
        <a href="<?= pretty_url('pages/shop.php') ?>">Shop</a><span>›</span>
        <?php if ($product['cat_name']): ?>
        <a href="<?= pretty_url('pages/shop.php') ?>?cat=<?= urlencode($product['cat_slug'] ?? '') ?>"><?= htmlspecialchars($product['cat_name']) ?></a><span>›</span>
        <?php endif; ?>
        <span><?= htmlspecialchars($product['name']) ?></span>
    </div>

    <div class="pdp-grid">

        <!-- ─── Gallery ─────────────────────────────────── -->
        <div class="pdp-gallery-card">
            <!-- Main image -->
            <div class="pdp-main-img" id="pdpMainImg" onclick="openLightbox(pdpActiveIdx)">
                <?php if (!empty($allImages)): ?>
                <img id="pdpMainImgEl" src="<?= SITE_URL ?>/assets/img/products/<?= htmlspecialchars($allImages[0]) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                <?php else: ?>
                <span class="no-img"><?= $teaEmoji ?></span>
                <?php endif; ?>
            </div>

            <?php if (count($allImages) > 1): ?>
            <!-- Thumbnail strip -->
            <div class="pdp-thumb-row" id="pdpThumbRow">
                <?php foreach ($allImages as $idx => $img): ?>
                <div class="pdp-thumb<?= $idx === 0 ? ' active' : '' ?>" onclick="pdpSetImage(<?= $idx ?>)" data-idx="<?= $idx ?>">
                    <img src="<?= SITE_URL ?>/assets/img/products/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($product['name']) ?> <?= $idx+1 ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <!-- Nav + counter -->
            <div class="pdp-gallery-nav">
                <button class="pdp-nav-btn" onclick="pdpSetImage(pdpActiveIdx-1)" title="Previous">‹</button>
                <span class="pdp-img-counter"><span id="pdpImgCurrent">1</span> / <?= count($allImages) ?></span>
                <button class="pdp-nav-btn" onclick="pdpSetImage(pdpActiveIdx+1)" title="Next">›</button>
            </div>
            <?php elseif (count($allImages) === 1): ?>
            <div style="text-align:center;margin-top:10px">
                <span style="font-size:11px;color:#94a3b8">Click image to enlarge</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- ─── Product Panel ─────────────────────────────── -->
        <div class="pdp-panel">
            <!-- Badges -->
            <div style="display:flex;gap:7px;flex-wrap:wrap;margin-bottom:6px">
                <?php if ($product['is_new']): ?><span class="badge badge-new">New Arrival</span><?php endif; ?>
                <?php if ($hasDiscount): ?><span class="badge badge-sale">-<?= $discount ?>% Off</span><?php endif; ?>
            </div>

            <h1 class="pdp-title"><?= htmlspecialchars($product['name']) ?></h1>

            <div class="pdp-meta-row">
                <?php if ($product['brand_name']): ?>
                <span><strong style="color:#334155">Brand:</strong> <?= htmlspecialchars($product['brand_name']) ?></span>
                <?php endif; ?>
                <span><strong style="color:#334155">For:</strong> <span class="pdp-pill"><?= ucfirst($product['tea_type']) ?></span></span>
                <span><strong style="color:#334155">Stock:</strong>
                    <span class="<?= $product['stock'] > 0 ? 'pdp-stock-ok' : '' ?>">
                        <?= $product['stock'] > 0 ? '✓ In stock' : '✗ Out of stock' ?>
                    </span>
                </span>
            </div>

            <div class="pdp-price-row">
                <div class="pdp-price-main" id="pdpPriceMain"><?= formatPrice($price) ?></div>
                <?php if ($hasDiscount): ?><div class="pdp-price-old"><?= formatPrice($product['price']) ?></div><?php endif; ?>
            </div>

            <?php if ($product['short_desc']): ?>
            <p class="pdp-short-desc"><?= nl2br(htmlspecialchars($product['short_desc'])) ?></p>

            <?php if (!empty($discountTiers)): ?>
            <!-- ── Quantity Discount Tiers Widget ── -->
            <div class="pdp-discount-tiers" style="margin-bottom:18px;">
                <div style="font-size:13px;font-weight:800;color:var(--text,#111);margin-bottom:10px;">🔥 <strong>Buy more, save more!</strong> 🔥</div>
                <?php $baseP = (float)($product['sale_price'] ?? $product['price']); ?>
                <div style="border:1.5px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr>
                                <td style="background:var(--primary,#2d6a4f);color:#fff;font-weight:700;padding:10px 14px;width:110px;">Quantity</td>
                                <?php foreach ($discountTiers as $tier): ?>
                                <td style="background:#f9fafb;font-weight:600;color:var(--text,#111);padding:10px 14px;text-align:center;border-left:1px solid #e5e7eb;">Buy <?= (int)$tier['min_qty'] ?>+</td>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="background:var(--primary,#2d6a4f);color:#fff;font-weight:700;padding:10px 14px;">Discount</td>
                                <?php foreach ($discountTiers as $tier): ?>
                                <td style="padding:10px 14px;text-align:center;border-left:1px solid #e5e7eb;border-top:1px solid #e5e7eb;font-weight:600;color:var(--text,#111);"><?= (int)$tier['discount_pct'] ?>%</td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <td style="background:var(--primary,#2d6a4f);color:#fff;font-weight:700;padding:10px 14px;">Discount Price</td>
                                <?php foreach ($discountTiers as $tier):
                                    $discPct = (float)$tier['discount_pct'];
                                    $discPrice = round($baseP * (1 - $discPct/100), 2);
                                ?>
                                <td style="padding:10px 14px;text-align:center;border-left:1px solid #e5e7eb;border-top:1px solid #e5e7eb;font-weight:600;color:var(--text,#111);">$<?= number_format($discPrice,2) ?> /each</td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Variants -->
            <?php foreach ($variantGroups as $attrName => $opts): ?>
            <div class="pdp-option-block">
                <div class="pdp-option-head">
                    <div>
                        <div class="pdp-option-title"><?= htmlspecialchars($attrName) ?></div>
                        <div class="pdp-option-sub">Select one option</div>
                    </div>
                    <span class="pdp-req-chip">Required</span>
                </div>
                <?php foreach ($opts as $vi => $v): ?>
                <div class="pdp-choice<?= $vi===0?' active':'' ?>" data-variant-id="<?= (int)$v['id'] ?>" data-variant-price="<?= number_format((float)($v['price'] ?? $price),2,'.','') ?>" onclick="selectVariantCard(this)">
                    <div class="pdp-choice-left">
                        <div class="pdp-choice-mark"></div>
                        <div class="pdp-choice-name"><?= htmlspecialchars($v['attribute_value']) ?></div>
                    </div>
                    <div class="pdp-choice-price"><?= formatPrice($v['price'] ?? $price) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <input type="hidden" id="selectedVariant" value="<?= !empty($variants) ? (int)$variants[0]['id'] : '' ?>">

            <!-- Option Groups (add-ons: size, flavor, etc.) -->
            <?php foreach ($optionGroups as $group):
                $isMulti = (int)$group['max_select'] > 1;
                $minSel  = max(0, (int)$group['min_select']);
                $maxSel  = max(1, (int)$group['max_select']);
                $isReq   = (int)$group['is_required'] === 1;
                $sub = $isReq
                    ? 'Required · Select ' . ($minSel === $maxSel ? $minSel : "$minSel–$maxSel")
                    : 'Optional · Up to ' . $maxSel;
            ?>
            <div class="pdp-option-block"
                 data-group-id="<?= (int)$group['id'] ?>"
                 data-required="<?= $isReq ? 1 : 0 ?>"
                 data-min="<?= $minSel ?>"
                 data-max="<?= $maxSel ?>"
                 data-multi="<?= $isMulti ? 1 : 0 ?>">
                <div class="pdp-option-head">
                    <div>
                        <div class="pdp-option-title"><?= htmlspecialchars($group['name']) ?></div>
                        <div class="pdp-option-sub"><?= htmlspecialchars($sub) ?></div>
                    </div>
                    <span class="<?= $isReq ? 'pdp-req-chip' : 'pdp-opt-chip' ?>"><?= $isReq ? 'Required' : 'Optional' ?></span>
                </div>
                <?php foreach ($group['items'] as $item):
                    $pa = (float)$item['price_add'];
                    $priceStr = $pa > 0 ? '+'.formatPrice($pa) : ($pa < 0 ? '−'.formatPrice(abs($pa)) : 'Free');
                ?>
                <div class="pdp-choice<?= $isMulti?' multi':'' ?><?= (int)$item['is_default']===1?' active':'' ?>"
                     data-item-id="<?= (int)$item['id'] ?>"
                     data-group="<?= (int)$group['id'] ?>"
                     data-price-add="<?= number_format($pa,2,'.','') ?>"
                     onclick="toggleOptionChoice(this)">
                    <div class="pdp-choice-left">
                        <div class="pdp-choice-mark"></div>
                        <div>
                            <div class="pdp-choice-name"><?= htmlspecialchars($item['name']) ?></div>
                            <?php if ((int)$item['is_default']===1): ?><div class="pdp-choice-rec">Recommended</div><?php endif; ?>
                        </div>
                    </div>
                    <div class="pdp-choice-price"><?= $priceStr ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <!-- Qty + Add to cart -->
            <div class="pdp-buy-row">
                <div class="pdp-qty">
                    <button type="button" onclick="changeProductQty(-1)">−</button>
                    <input type="number" id="pdpQty" value="1" min="1" max="<?= (int)$product['stock'] ?>">
                    <button type="button" onclick="changeProductQty(1)">+</button>
                </div>
                <button class="pdp-cart-btn" type="button" id="pdpAddBtn" onclick="productPageAddToCart()" <?= $product['stock'] <= 0 ? 'disabled' : '' ?>>
                    <span><i class="fas fa-cart-plus"></i> <?= $product['stock'] > 0 ? 'Add to Cart' : 'Out of Stock' ?></span>
                    <span class="pdp-cart-total" id="pdpCartTotal"><?= formatPrice($price) ?></span>
                </button>
            </div>

            <!-- Secondary actions -->
            <div class="pdp-actions">
                <form method="POST" action="<?= SITE_URL ?>/pages/wishlist-action.php" style="display:inline">
                    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                    <input type="hidden" name="action" value="toggle">
                    <button type="submit" class="pdp-secondary-btn"><i class="fas fa-heart"></i> Save to Wishlist</button>
                </form>
                <a class="pdp-secondary-btn" href="<?= pretty_url('pages/cart.php') ?>"><i class="fas fa-bag-shopping"></i> View Cart</a>
            </div>

            <!-- Benefits -->
            <div class="pdp-benefits">
                <ul style="margin:0;padding:0">
                    <li><span>🚚</span><span>Free delivery on orders over <?= formatPrice(getSetting('free_delivery_threshold','49')) ?></span></li>
                    <li><span>✅</span><span>In stock and ready for local delivery</span></li>
                    <li><span>💳</span><span>Stripe &amp; PayPal accepted · SSL secured</span></li>
                </ul>
            </div>

            <!-- Full description -->
            <?php if ($product['description']): ?>
            <div class="pdp-desc-block">
                <h3>Product Description</h3>
                <div><?= nl2br(htmlspecialchars($product['description'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div><!-- pdp-grid -->

    <!-- Reviews -->
    <div style="margin-top:52px">
        <h2 class="pdp-section-title">Customer Reviews (<?= count($reviews) ?>)</h2>
        <?php if (empty($reviews)): ?>
        <div class="pdp-review-card"><p style="margin:0;color:#64748b">No reviews yet. Be the first!</p></div>
        <?php else: ?>
            <?php foreach ($reviews as $r): ?>
            <div class="pdp-review-card">
                <div class="pdp-review-meta">
                    <span class="pdp-review-name"><?= htmlspecialchars($r['user_name']) ?></span>
                    <span style="color:#f59e0b"><?= str_repeat('★',(int)$r['rating']) ?><?= str_repeat('☆',5-(int)$r['rating']) ?></span>
                    <span style="font-size:11px;color:#94a3b8"><?= date('M d, Y',strtotime($r['created_at'])) ?></span>
                </div>
                <div class="pdp-review-body"><?= htmlspecialchars($r['comment']) ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (isLoggedIn()): ?>
        <div class="pdp-review-card" style="margin-top:18px">
            <h3 style="font-size:16px;font-weight:800;margin:0 0 14px">Write a Review</h3>
            <form method="POST">
                <input type="hidden" name="action" value="review">
                <div class="form-group">
                    <label>Rating</label>
                    <select name="rating" style="width:100%;padding:10px 13px;border-radius:12px;border:1.5px solid #e2e8f0;font-size:14px;font-family:inherit">
                        <option value="5">⭐⭐⭐⭐⭐ Excellent</option>
                        <option value="4">⭐⭐⭐⭐ Good</option>
                        <option value="3">⭐⭐⭐ Average</option>
                        <option value="2">⭐⭐ Poor</option>
                        <option value="1">⭐ Terrible</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Comment</label>
                    <textarea name="comment" placeholder="Share your experience..." required style="border-radius:12px;border:1.5px solid #e2e8f0;padding:10px 13px;font-family:inherit;font-size:14px;width:100%;resize:vertical;box-sizing:border-box"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Submit Review</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- Related products -->
    <?php if (!empty($related)): ?>
    <div class="pdp-related" style="margin-top:54px">
        <div class="section-top"><h2 class="pdp-section-title" style="margin-bottom:0">You May Also Like</h2></div>
        <div class="product-grid" style="margin-top:20px">
            <?php foreach ($related as $rp) echo renderMiniCard($rp); ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ─── Lightbox ──────────────────────────────────── -->
<div id="imgLightbox">
    <button class="lb-close" onclick="closeLightbox()">✕</button>
    <button class="lb-prev" onclick="lbNav(-1)">‹</button>
    <img id="lbImg" src="" alt="">
    <button class="lb-next" onclick="lbNav(1)">›</button>
</div>

<script>
const PDP_BASE_PRICE  = <?= json_encode(number_format($price,2,'.','')) ?>;
const PDP_PRODUCT_ID  = <?= (int)$product['id'] ?>;
const PDP_STOCK       = <?= (int)$product['stock'] ?>;
const PDP_OPTION_GROUPS = <?= json_encode($optionGroups, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
// All image URLs for gallery/lightbox
const PDP_IMAGES = <?= json_encode(array_map(fn($f) => SITE_URL.'/assets/img/products/'.$f, $allImages), JSON_UNESCAPED_SLASHES) ?>;
// Quantity discount tiers
const PDP_DISCOUNT_TIERS = <?= json_encode($discountTiers, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

let pdpActiveIdx = 0;
let pdpSelected  = {};

// ─── Gallery ─────────────────────────────────────
function pdpSetImage(idx) {
    const total = PDP_IMAGES.length;
    if (total === 0) return;
    pdpActiveIdx = ((idx % total) + total) % total;
    const mainEl = document.getElementById('pdpMainImgEl');
    if (mainEl) mainEl.src = PDP_IMAGES[pdpActiveIdx];
    document.querySelectorAll('.pdp-thumb').forEach(t => {
        t.classList.toggle('active', parseInt(t.dataset.idx) === pdpActiveIdx);
    });
    const counter = document.getElementById('pdpImgCurrent');
    if (counter) counter.textContent = pdpActiveIdx + 1;
}

// Keyboard navigation
document.addEventListener('keydown', e => {
    if (document.getElementById('imgLightbox').classList.contains('open')) {
        if (e.key === 'ArrowLeft')  lbNav(-1);
        if (e.key === 'ArrowRight') lbNav(1);
        if (e.key === 'Escape')     closeLightbox();
    } else {
        if (e.key === 'ArrowLeft')  pdpSetImage(pdpActiveIdx - 1);
        if (e.key === 'ArrowRight') pdpSetImage(pdpActiveIdx + 1);
    }
});

// ─── Lightbox ─────────────────────────────────────
function openLightbox(idx) {
    if (PDP_IMAGES.length === 0) return;
    pdpActiveIdx = ((idx % PDP_IMAGES.length) + PDP_IMAGES.length) % PDP_IMAGES.length;
    document.getElementById('lbImg').src = PDP_IMAGES[pdpActiveIdx];
    document.getElementById('imgLightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('imgLightbox').classList.remove('open');
    document.body.style.overflow = '';
}
function lbNav(dir) {
    if (PDP_IMAGES.length === 0) return;
    pdpActiveIdx = ((pdpActiveIdx + dir + PDP_IMAGES.length) % PDP_IMAGES.length);
    document.getElementById('lbImg').src = PDP_IMAGES[pdpActiveIdx];
    pdpSetImage(pdpActiveIdx); // keep thumbnail in sync
}
document.getElementById('imgLightbox').addEventListener('click', e => {
    if (e.target === document.getElementById('imgLightbox')) closeLightbox();
});

// ─── Variants ─────────────────────────────────────
function getSelectedVariantPrice() {
    const active = document.querySelector('.pdp-choice[data-variant-id].active');
    return active ? parseFloat(active.dataset.variantPrice || PDP_BASE_PRICE) : parseFloat(PDP_BASE_PRICE);
}
function selectVariantCard(el) {
    document.querySelectorAll('.pdp-choice[data-variant-id]').forEach(n => n.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('selectedVariant').value = el.dataset.variantId || '';
    refreshPdpTotal();
}

// ─── Add-on options ───────────────────────────────
function toggleOptionChoice(el) {
    const groupId  = el.dataset.group;
    const itemId   = parseInt(el.dataset.itemId || '0', 10);
    const priceAdd = parseFloat(el.dataset.priceAdd || '0');
    const block    = el.closest('.pdp-option-block');
    const isMulti  = block.dataset.multi === '1';
    const maxSel   = parseInt(block.dataset.max || '1', 10);
    if (!pdpSelected[groupId]) pdpSelected[groupId] = [];
    let selected = pdpSelected[groupId];
    const exists = selected.findIndex(x => x.id === itemId);

    if (isMulti) {
        if (exists > -1) {
            selected.splice(exists, 1);
            el.classList.remove('active');
        } else {
            if (selected.length >= maxSel) {
                const removed = selected.shift();
                block.querySelector('.pdp-choice[data-item-id="' + removed.id + '"]')?.classList.remove('active');
            }
            selected.push({ id: itemId, price_add: priceAdd });
            el.classList.add('active');
        }
    } else {
        const isRequired = block.dataset.required === '1';
        const wasActive = el.classList.contains('active');
        block.querySelectorAll('.pdp-choice[data-group="' + groupId + '"]').forEach(n => n.classList.remove('active'));
        if (wasActive && !isRequired) {
            // Allow unselecting optional single-select
            pdpSelected[groupId] = [];
        } else {
            pdpSelected[groupId] = [{ id: itemId, price_add: priceAdd }];
            el.classList.add('active');
        }
    }
    block.classList.remove('error');
    refreshPdpTotal();
}

function initPdpDefaults() {
    document.querySelectorAll('.pdp-option-block[data-group-id]').forEach(block => {
        const gid = block.dataset.groupId;
        pdpSelected[gid] = [];
        block.querySelectorAll('.pdp-choice.active[data-item-id]').forEach(el => {
            pdpSelected[gid].push({ id: parseInt(el.dataset.itemId||'0',10), price_add: parseFloat(el.dataset.priceAdd||'0') });
        });
    });
    refreshPdpTotal();
}

function refreshPdpTotal() {
    const qty   = Math.max(1, parseInt(document.getElementById('pdpQty')?.value||'1',10));
    let total   = getSelectedVariantPrice();
    Object.values(pdpSelected).forEach(arr => arr.forEach(s => { total += parseFloat(s.price_add||0); }));
    
    // Apply quantity discount if applicable
    let discountPct = 0;
    if (PDP_DISCOUNT_TIERS && PDP_DISCOUNT_TIERS.length > 0) {
        for (let i = PDP_DISCOUNT_TIERS.length - 1; i >= 0; i--) {
            const tier = PDP_DISCOUNT_TIERS[i];
            if (qty >= parseInt(tier.min_qty)) {
                discountPct = parseFloat(tier.discount_pct);
                break;
            }
        }
    }
    
    // Apply discount to the base price + options
    if (discountPct > 0) {
        total = total * (1 - discountPct / 100);
    }
    
    const line  = total * qty;
    const main  = document.getElementById('pdpPriceMain');
    const cta   = document.getElementById('pdpCartTotal');
    if (main) main.textContent = '$' + total.toFixed(2);
    if (cta)  cta.textContent  = '$' + line.toFixed(2);
}

function changeProductQty(delta) {
    const inp = document.getElementById('pdpQty');
    let v = parseInt(inp.value||'1',10);
    v = Math.max(1, Math.min(PDP_STOCK||999, v + delta));
    inp.value = v;
    refreshPdpTotal();
}

function buildOptionsText() {
    const text = [];
    PDP_OPTION_GROUPS.forEach(group => {
        (pdpSelected[group.id] || []).forEach(sel => {
            const item = (group.items||[]).find(it => parseInt(it.id,10) === parseInt(sel.id,10));
            if (item) text.push(group.name + ': ' + item.name);
        });
    });
    return text.join('; ');
}

function validateSelections() {
    for (const block of document.querySelectorAll('.pdp-option-block[data-required="1"]')) {
        const gid    = block.dataset.groupId;
        const minSel = parseInt(block.dataset.min||'1',10);
        if ((pdpSelected[gid]||[]).length < minSel) {
            block.classList.add('error');
            block.scrollIntoView({ behavior:'smooth', block:'center' });
            setTimeout(() => block.classList.remove('error'), 2000);
            showToast('⚠️ Please select: ' + (block.querySelector('.pdp-option-title')?.textContent||'required option'), 'error');
            return false;
        }
    }
    return true;
}

async function productPageAddToCart() {
    if (!validateSelections()) return;
    const qty     = Math.max(1, parseInt(document.getElementById('pdpQty')?.value||'1',10));
    const variant = document.getElementById('selectedVariant')?.value || '';
    const btn     = document.getElementById('pdpAddBtn');
    
    // Calculate the final price with discount for accurate cart total display
    let unitPrice = getSelectedVariantPrice();
    Object.values(pdpSelected).forEach(arr => arr.forEach(s => { unitPrice += parseFloat(s.price_add||0); }));
    
    // Apply quantity discount
    let discountPct = 0;
    if (PDP_DISCOUNT_TIERS && PDP_DISCOUNT_TIERS.length > 0) {
        for (let i = PDP_DISCOUNT_TIERS.length - 1; i >= 0; i--) {
            const tier = PDP_DISCOUNT_TIERS[i];
            if (qty >= parseInt(tier.min_qty)) {
                discountPct = parseFloat(tier.discount_pct);
                break;
            }
        }
    }
    if (discountPct > 0) {
        unitPrice = unitPrice * (1 - discountPct / 100);
    }
    
    const total   = '$' + (unitPrice * qty).toFixed(2);
    const oldHTML = btn.innerHTML;
    btn.disabled  = true;
    btn.innerHTML = '<span><i class="fas fa-spinner fa-spin"></i> Adding...</span><span class="pdp-cart-total">' + total + '</span>';

    try {
        const fd = new FormData();
        fd.append('action', 'add');
        fd.append('product_id', PDP_PRODUCT_ID);
        fd.append('qty', qty);
        if (variant) fd.append('variant_id', variant);
        fd.append('options',      JSON.stringify(pdpSelected));
        fd.append('options_text', buildOptionsText());
        // Pass the calculated unit price and discount to cart-action.php
        fd.append('unit_price', unitPrice.toFixed(2));
        fd.append('discount_pct', discountPct);

        const url = document.querySelector('meta[name="cart-action-url"]')?.content
                  || (getSiteUrl() + '/pages/cart-action.php');
        const res  = await fetch(url, { method: 'POST', body: fd });
        const text = await res.text();
        let data = {};
        try { data = JSON.parse(text); } catch(e) {
            console.error('Non-JSON response:', text.slice(0,300));
            throw new Error('Server returned non-JSON');
        }
        if (data.success) {
            document.querySelectorAll('.cart-count').forEach(el => el.textContent = data.count);
            showToast('🛒 Added to cart!');
        } else {
            showToast('❌ ' + (data.msg || 'Could not add to cart'), 'error');
        }
    } catch (e) {
        console.error('addToCart error:', e);
        showToast('❌ Could not add to cart. Try again.', 'error');
    }
    btn.disabled  = false;
    btn.innerHTML = oldHTML;
}

document.addEventListener('DOMContentLoaded', initPdpDefaults);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
