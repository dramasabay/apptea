<?php
$pageTitle = "Products – TeaStore Admin";
require_once __DIR__ . '/admin-header.php';

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([(int)$_GET['delete']]);
    flash('success', 'Product deleted');
    redirect(pretty_url('admin/products.php'));
}

$q = trim($_GET['q'] ?? '');
$where = $q ? "WHERE p.name LIKE ? OR p.slug LIKE ?" : "WHERE 1";
$params = $q ? ["%$q%", "%$q%"] : [];

$stmt = $pdo->prepare("SELECT p.*, b.name AS brand_name, c.name AS cat_name FROM products p LEFT JOIN brands b ON p.brand_id=b.id LEFT JOIN categories c ON p.category_id=c.id $where ORDER BY p.created_at DESC LIMIT 100");
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<style>
.products-toolbar{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:16px 18px;margin-bottom:18px;display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap;box-shadow:0 16px 36px rgba(15,23,42,.05)}
.products-search{flex:1;display:flex;gap:10px;min-width:min(100%,520px)}
.products-search input{flex:1;border:1.5px solid var(--border);border-radius:16px;padding:12px 14px;font-size:14px;outline:none;font-family:inherit;background:#fff}
.products-search input:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-light)}
.products-count{font-size:13px;color:var(--text-2);font-weight:700}
.product-cell{display:flex;align-items:center;gap:14px;min-width:260px}
.product-meta-title{font-size:14px;font-weight:800;line-height:1.35;color:var(--text)}
.product-meta-sub{font-size:12px;color:var(--text-3);margin-top:4px}
.stock-chip{display:inline-flex;align-items:center;justify-content:center;min-width:54px;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:800}
.stock-chip.ok{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.stock-chip.low{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa}
.stock-chip.out{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}
.tea-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#f8fafc;border:1px solid #e5e7eb;font-size:12px;font-weight:800;color:#334155}
.price-stack{display:flex;flex-direction:column;gap:3px}.price-stack strong{font-size:14px}.price-stack .old{font-size:12px;color:#94a3b8;text-decoration:line-through}
.row-actions{display:flex;flex-wrap:wrap;gap:8px;min-width:240px}.row-actions a{display:inline-flex;align-items:center;gap:6px;padding:8px 11px;border-radius:12px;font-size:12px;font-weight:800;transition:.2s}.row-actions .options{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa}.row-actions .edit{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}.row-actions .view{background:#f8fafc;color:#334155;border:1px solid #e2e8f0}.row-actions .delete{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}.row-actions a:hover{transform:translateY(-1px)}
@media(max-width:860px){.products-toolbar{padding:14px}.products-search{min-width:100%}.row-actions{min-width:0}.product-cell{min-width:220px}}
</style>

<div class="page-header">
    <div>
        <h1>📦 Products</h1>
        <p>Manage product catalog, pricing, add-ons, and storefront visibility.</p>
    </div>
    <a href="<?= pretty_url('admin/product-form.php') ?>" class="btn btn-primary"><i class="fas fa-plus"></i> Add Product</a>
</div>

<div class="products-toolbar">
    <form method="GET" class="products-search">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by product name or slug...">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
        <?php if ($q): ?><a href="<?= pretty_url('admin/products.php') ?>" class="btn btn-secondary">Clear</a><?php endif; ?>
    </form>
    <div class="products-count"><?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?></div>
</div>

<div class="table-wrap">
<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Product</th>
            <th>Category</th>
            <th>Brand</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Pet</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($products as $p):
            $teaEmoji = $p['tea_type'] === 'green' ? '🍃' : ($p['tea_type'] === 'black' ? '🫖' : '🍵');
            $stockClass = $p['stock'] > 8 ? 'ok' : ($p['stock'] > 0 ? 'low' : 'out');
        ?>
        <tr>
            <td>#<?= (int)$p['id'] ?></td>
            <td>
                <div class="product-cell">
                    <?php if (!empty($p['image'])): ?>
                        <img class="product-thumb" src="<?= SITE_URL ?>/assets/img/products/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                    <?php else: ?>
                        <div class="product-thumb"><?= $teaEmoji ?></div>
                    <?php endif; ?>
                    <div>
                        <div class="product-meta-title"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="product-meta-sub">
                            <?php if ($p['is_new']): ?><span class="badge badge-paid" style="margin-right:6px">New</span><?php endif; ?>
                            <?php if ($p['featured']): ?><span class="badge badge-processing" style="margin-right:6px">Featured</span><?php endif; ?>
                            <?= htmlspecialchars($p['slug']) ?>
                        </div>
                    </div>
                </div>
            </td>
            <td><?= htmlspecialchars($p['cat_name'] ?? '–') ?></td>
            <td><?= htmlspecialchars($p['brand_name'] ?? '–') ?></td>
            <td>
                <div class="price-stack">
                    <strong><?= formatPrice($p['sale_price'] ?: $p['price']) ?></strong>
                    <?php if ($p['sale_price']): ?><span class="old"><?= formatPrice($p['price']) ?></span><?php endif; ?>
                </div>
            </td>
            <td><span class="stock-chip <?= $stockClass ?>"><?= (int)$p['stock'] ?></span></td>
            <td><span class="tea-chip"><?= $teaEmoji ?> <?= ucfirst($p['tea_type']) ?></span></td>
            <td>
                <div class="row-actions">
                    <a class="options" href="<?= pretty_url('admin/product-options.php') ?>?product_id=<?= (int)$p['id'] ?>"><i class="fas fa-sliders-h"></i> Options</a>
                    <a class="edit" href="<?= pretty_url('admin/product-form.php') ?>?id=<?= (int)$p['id'] ?>"><i class="fas fa-pen"></i> Edit</a>
                    <a class="view" href="<?= pretty_url('pages/product.php') ?>?slug=<?= urlencode($p['slug']) ?>" target="_blank"><i class="fas fa-eye"></i> View</a>
                    <a class="delete" href="?delete=<?= (int)$p['id'] ?>" onclick="return confirm('Delete this product?')"><i class="fas fa-trash"></i> Delete</a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($products)): ?>
            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-3)">No products found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
</div>

<?php require_once __DIR__ . '/admin-footer.php'; ?>
