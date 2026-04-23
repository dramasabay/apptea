<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['error' => 'No ID']); exit; }

// Get product
$stmt = $pdo->prepare("SELECT p.*, b.name AS brand_name, c.name AS cat_name FROM products p LEFT JOIN brands b ON p.brand_id=b.id LEFT JOIN categories c ON p.category_id=c.id WHERE p.id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) { echo json_encode(['error' => 'Not found']); exit; }

$p['effective_price'] = $p['sale_price'] ?? $p['price'];

// Get option groups with items
$groups = $pdo->prepare("SELECT * FROM product_option_groups WHERE product_id=? ORDER BY sort_order, id");
$groups->execute([$id]);
$groups = $groups->fetchAll();

foreach ($groups as &$g) {
    $items = $pdo->prepare("SELECT * FROM product_option_items WHERE group_id=? ORDER BY sort_order, id");
    $items->execute([$g['id']]);
    $g['items'] = $items->fetchAll();
}
unset($g);

// Get quantity discount tiers
$discountTiers = [];
try {
    $dtStmt = $pdo->prepare("SELECT * FROM product_quantity_discounts WHERE product_id=? ORDER BY min_qty");
    $dtStmt->execute([$id]);
    $discountTiers = $dtStmt->fetchAll();
} catch (Throwable $e) {}

echo json_encode([
    'product'        => $p,
    'option_groups'  => $groups,
    'discount_tiers' => $discountTiers,
]);
