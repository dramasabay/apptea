<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = "Shop by Brands – TeaStore";
$brands = $pdo->query("SELECT b.*, COUNT(p.id) AS pcount FROM brands b LEFT JOIN products p ON p.brand_id=b.id GROUP BY b.id ORDER BY b.name")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container" style="padding-top:28px;padding-bottom:60px">
    <h1 class="page-title" style="margin-bottom:6px">Shop by Brand</h1>
    <p style="font-size:14px;color:#64748b;margin-bottom:24px"><?= count($brands) ?> brands available</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px">
        <?php foreach ($brands as $b): ?>
        <a href="<?= pretty_url('pages/shop.php') ?>?brand=<?= $b['id'] ?>" class="brand-card-qa">
            <span class="brand-name"><?= htmlspecialchars($b['name']) ?></span>
            <span class="brand-pcount"><?= (int)$b['pcount'] ?> products</span>
            <span class="brand-shop-btn">Shop now →</span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
