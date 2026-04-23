<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = "Shop – TeaStore";

$perPage = max(4, min(48, (int)(getSetting('shop_per_page', '8'))));
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$q = $_GET['q'] ?? '';
$pet = $_GET['tea'] ?? '';
$catSlug = $_GET['cat'] ?? '';
$brandId = (int)($_GET['brand'] ?? 0);
$sale = $_GET['sale'] ?? '';
$featured = $_GET['featured'] ?? '';
$isNew = $_GET['new'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$minPrice = (float)($_GET['min_price'] ?? 0);
$maxPrice = (float)($_GET['max_price'] ?? 1000);

// Build query
$where = ['1=1'];
$params = [];

if ($q) { $where[] = 'p.name LIKE ?'; $params[] = "%$q%"; }
if ($pet) { $where[] = 'p.tea_type IN (?,?)'; $params[] = $pet; $params[] = 'both'; }
if ($sale) { $where[] = 'p.sale_price IS NOT NULL'; }
if ($featured) { $where[] = 'p.featured=1'; }
if ($isNew) { $where[] = 'p.is_new=1'; }
if ($brandId) { $where[] = 'p.brand_id=?'; $params[] = $brandId; }
if ($minPrice > 0) { $where[] = 'COALESCE(p.sale_price,p.price) >= ?'; $params[] = $minPrice; }
if ($maxPrice < 1000) { $where[] = 'COALESCE(p.sale_price,p.price) <= ?'; $params[] = $maxPrice; }

$catObj = null;
if ($catSlug) {
    $catStmt = $pdo->prepare("SELECT * FROM categories WHERE slug=?");
    $catStmt->execute([$catSlug]);
    $catObj = $catStmt->fetch();
    if ($catObj) {
        // Include subcategories
        $subIds = $pdo->prepare("SELECT id FROM categories WHERE parent_id=?");
        $subIds->execute([$catObj['id']]);
        $ids = array_column($subIds->fetchAll(), 'id');
        $ids[] = $catObj['id'];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where[] = "p.category_id IN ($placeholders)";
        $params = array_merge($params, $ids);
    }
}

$orderBy = match($sort) {
    'price_asc' => 'COALESCE(p.sale_price,p.price) ASC',
    'price_desc' => 'COALESCE(p.sale_price,p.price) DESC',
    'name' => 'p.name ASC',
    default => 'p.created_at DESC'
};

$whereStr = implode(' AND ', $where);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereStr");
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

$stmt = $pdo->prepare("SELECT p.*, b.name AS brand_name FROM products p LEFT JOIN brands b ON p.brand_id=b.id WHERE $whereStr ORDER BY $orderBy LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Sidebar data
$categories = $pdo->query("
    SELECT c.*, p.name AS parent_name,
           COUNT(DISTINCT CASE
               WHEN p2.category_id = c.id THEN p2.id
               WHEN child.id IS NOT NULL AND p2.category_id = child.id THEN p2.id
               ELSE NULL
           END) AS pcount
    FROM categories c
    LEFT JOIN categories p ON p.id = c.parent_id
    LEFT JOIN categories child ON child.parent_id = c.id
    LEFT JOIN products p2 ON (p2.category_id = c.id OR p2.category_id = child.id)
    GROUP BY c.id
    ORDER BY COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.name
")->fetchAll();
$brands = $pdo->query("SELECT * FROM brands ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';

function productCard($p) {
    $siteUrl = SITE_URL;
    $price = $p['sale_price'] ?? $p['price'];
    $hasDiscount = $p['sale_price'] !== null;
    $discount = $hasDiscount ? round((1 - $p['sale_price']/$p['price'])*100) : 0;
    $teaEmoji = $p['tea_type'] === 'green' ? '🍃' : ($p['tea_type'] === 'black' ? '🫖' : '🍵');
    ob_start(); ?>
    <div class="product-card" onclick="openModal(<?= (int)$p['id'] ?>)">
        <div class="product-badge">
            <?php if ($p['is_new']): ?><span class="badge badge-new">New</span><?php endif; ?>
            <?php if ($hasDiscount): ?><span class="badge badge-sale">-<?= $discount ?>%</span><?php endif; ?>
        </div>
        <div class="product-img-wrap">
            <?php if ($p['image']): ?>
                <img src="<?= $siteUrl ?>/assets/img/products/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
            <?php else: ?>
                <div class="product-no-img"><?= $teaEmoji ?></div>
            <?php endif; ?>
            <div class="product-hover-overlay">
                <button class="product-hover-btn product-hover-btn-primary btn-quick-add" onclick="event.stopPropagation();openModal(<?= (int)$p['id'] ?>)" type="button"><i class="fas fa-plus"></i> Quick add</button>
                <a class="product-hover-btn btn-view-detail" href="<?= $siteUrl ?>/product?slug=<?= urlencode($p['slug']) ?>" onclick="event.stopPropagation()"><i class="fas fa-eye"></i> View details</a>
            </div>
            <button class="product-plus-btn" onclick="event.stopPropagation();openModal(<?= (int)$p['id'] ?>)" title="Quick add" type="button">+</button>
        </div>
        <div class="product-info">
            <?php if (!empty($p['brand_name'])): ?><div class="product-meta"><?= htmlspecialchars($p['brand_name']) ?></div><?php endif; ?>
            <div class="product-name-text"><?= htmlspecialchars($p['name']) ?></div>
            <div class="product-price-row">
                <div class="product-price">
                    <span class="price-current"><?= formatPrice($price) ?></span>
                    <?php if ($hasDiscount): ?><span class="price-old"><?= formatPrice($p['price']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}
?>

<meta name="site-url" content="<?= SITE_URL ?>">

<div class="container" style="padding-top:30px;padding-bottom:40px;">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="<?= SITE_URL ?>/index.php">Home</a>
        <span>›</span>
        <?php if ($catObj): ?>
            <a href="<?= pretty_url('pages/shop.php') ?>">Shop</a><span>›</span>
            <span><?= htmlspecialchars($catObj['name']) ?></span>
        <?php else: ?>
            <span>Shop</span>
        <?php endif; ?>
    </div>

    <div class="shop-layout">
        <!-- Sidebar -->
        <aside class="shop-sidebar">
            <div class="filter-box">
                <h3>Pet Type</h3>
                <ul>
                    <li><a href="?<?= http_build_query(array_merge($_GET, ['tea'=>'', 'page'=>1])) ?>" class="<?= !$pet ? 'active' : '' ?>">All Teas</a></li>
                    <li><a href="?<?= http_build_query(array_merge($_GET, ['tea'=>'dog', 'page'=>1])) ?>" class="<?= $pet==='dog' ? 'active' : '' ?>">🍃 Green Tea</a></li>
                    <li><a href="?<?= http_build_query(array_merge($_GET, ['tea'=>'cat', 'page'=>1])) ?>" class="<?= $pet==='cat' ? 'active' : '' ?>">🫖 Black Tea</a></li>
                </ul>
            </div>
            <div class="filter-box">
                <h3>Categories</h3>
                <ul>
                    <?php $parentCats = array_filter($categories, fn($c) => !$c['parent_id']); ?>
                    <?php foreach ($parentCats as $c): ?>
                    <li>
                        <a href="?cat=<?= $c['slug'] ?>" class="<?= $catSlug===$c['slug'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($c['name']) ?> <small>(<?= $c['pcount'] ?>)</small>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="filter-box">
                <h3>Brands</h3>
                <ul>
                    <?php foreach ($brands as $b): ?>
                    <li><a href="?brand=<?= $b['id'] ?>" class="<?= $brandId===$b['id'] ? 'active' : '' ?>"><?= htmlspecialchars($b['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="filter-box">
                <h3>Filter</h3>
                <ul>
                    <li><a href="?sale=1" class="<?= $sale ? 'active' : '' ?>">🏷️ On Sale</a></li>
                    <li><a href="?new=1" class="<?= $isNew ? 'active' : '' ?>">✨ New Arrivals</a></li>
                    <li><a href="?featured=1" class="<?= $featured ? 'active' : '' ?>">⭐ Featured</a></li>
                </ul>
            </div>
            <div class="filter-box">
                <h3>Price Range</h3>
                <form method="GET">
                    <?php foreach ($_GET as $k=>$v): if ($k!=='min_price'&&$k!=='max_price'&&$k!=='page'): ?>
                    <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                    <?php endif; endforeach; ?>
                    <div style="display:flex;gap:8px;margin-bottom:10px;">
                        <input type="number" name="min_price" value="<?= $minPrice ?>" placeholder="Min $" style="border:1px solid #e0e0e0;border-radius:6px;padding:7px 10px;font-size:13px;width:50%;outline:none;">
                        <input type="number" name="max_price" value="<?= $maxPrice < 1000 ? $maxPrice : '' ?>" placeholder="Max $" style="border:1px solid #e0e0e0;border-radius:6px;padding:7px 10px;font-size:13px;width:50%;outline:none;">
                    </div>
                    <button type="submit" class="btn btn-dark btn-sm btn-full">Apply</button>
                </form>
            </div>
            <a href="<?= pretty_url('pages/shop.php') ?>" style="display:block;text-align:center;font-size:12px;color:var(--text-light);margin-top:10px;">Clear all filters</a>
        </aside>

        <!-- Main -->
        <div>
            <h1 class="page-title">
                <?php if ($q): ?>Search: "<?= htmlspecialchars($q) ?>"
                <?php elseif ($catObj): ?><?= htmlspecialchars($catObj['name']) ?>
                <?php elseif ($pet === 'dog'): ?>Dog Products
                <?php elseif ($pet === 'cat'): ?>Cat Products
                <?php elseif ($sale): ?>On Sale
                <?php else: ?>All Products<?php endif; ?>
            </h1>

            <div class="shop-toolbar">
                <span class="result-count"><?= $totalProducts ?> products found</span>
                <form method="GET" style="display:inline;">
                    <?php foreach ($_GET as $k=>$v): if ($k!=='sort'): ?>
                    <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                    <?php endif; endforeach; ?>
                    <select name="sort" class="sort-select" onchange="this.form.submit()">
                        <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest</option>
                        <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price: High to Low</option>
                        <option value="name" <?= $sort==='name'?'selected':'' ?>>Name A-Z</option>
                    </select>
                </form>
            </div>

            <div class="product-grid shop-grid">
                <?php foreach ($products as $p) echo productCard($p); ?>
                <?php if (empty($products)): ?>
                <div class="no-products">
                    <div style="font-size:50px;margin-bottom:16px;">🔍</div>
                    <h3>No products found</h3>
                    <p style="margin-top:8px;">Try adjusting your filters or <a href="<?= pretty_url('pages/shop.php') ?>" style="color:var(--primary)">browse all products</a></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>">‹</a>
                <?php endif; ?>
                <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
                <<?= $i===$page?'span class="active"':'a href="?'.http_build_query(array_merge($_GET,['page'=>$i])).'"' ?>><?= $i ?></<?= $i===$page?'span':'a' ?>>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>">›</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
