<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

// Prevent any HTML errors from corrupting JSON response
set_exception_handler(function($e) {
    echo json_encode(['success' => false, 'count' => 0, 'msg' => 'Server error: ' . $e->getMessage()]); exit;
});
set_error_handler(function($errno, $errstr) {
    echo json_encode(['success' => false, 'count' => 0, 'msg' => 'Server error: ' . $errstr]); exit;
});

// Ensure options_json column exists (safe for older DB installs)
try {
    $pdo->exec("ALTER TABLE cart ADD COLUMN IF NOT EXISTS options_json TEXT DEFAULT NULL");
} catch (Throwable $e) { /* column already exists or DB doesn't support IF NOT EXISTS */ }

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$uid    = $_SESSION['user_id'] ?? null;
$sid    = session_id();

function respond(bool $success, int $count = 0, string $msg = ''): void {
    echo json_encode(['success' => $success, 'count' => $count, 'msg' => $msg]); exit;
}

function cartCount(PDO $pdo, ?int $uid, string $sid): int {
    $s = $uid
        ? $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM cart WHERE user_id=?")
        : $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM cart WHERE session_id=?");
    $s->execute([$uid ?? $sid]);
    return (int)$s->fetchColumn();
}

// ─── ADD ─────────────────────────────────────────────────────────────────────
if ($action === 'add') {
    $pid         = (int)($_POST['product_id'] ?? 0);
    $qty         = max(1, (int)($_POST['qty'] ?? 1));
    $vid         = (isset($_POST['variant_id']) && $_POST['variant_id'] !== '') ? (int)$_POST['variant_id'] : null;
    $optionsText = trim($_POST['options_text'] ?? '');
    $optionsRaw  = trim($_POST['options'] ?? '[]');

    if (!$pid) respond(false, 0, 'Invalid product');

    $p = $pdo->prepare("SELECT id, stock, price, sale_price FROM products WHERE id=?");
    $p->execute([$pid]);
    $prod = $p->fetch();
    if (!$prod) respond(false, 0, 'Product not found');

    // Parse selected options — handle {groupId: [{id, price}]} or {groupId: [{id, price_add}]}
    $selectedOptions = json_decode($optionsRaw, true) ?? [];
    if (!is_array($selectedOptions)) $selectedOptions = [];

    // Verify all option item IDs from DB (never trust client prices)
    $optionsExtra = 0.0;
    foreach ($selectedOptions as $groupId => $items) {
        if (!is_array($items)) continue;
        foreach ($items as $item) {
            $iid = (int)($item['id'] ?? 0);
            if (!$iid) continue;
            $oi = $pdo->prepare("SELECT price_add FROM product_option_items WHERE id=?");
            $oi->execute([$iid]);
            $row = $oi->fetch();
            if ($row) $optionsExtra += (float)$row['price_add'];
        }
    }

    $optionsStore = json_encode([
        'text'    => $optionsText,
        'options' => $selectedOptions,
        'extra'   => round($optionsExtra, 2),
    ]);

    // Find existing cart line with same product + variant + options
    try {
        if ($uid) {
            $check = $vid === null
                ? $pdo->prepare("SELECT id, qty FROM cart WHERE user_id=? AND product_id=? AND variant_id IS NULL AND COALESCE(options_json,'')=?")
                : $pdo->prepare("SELECT id, qty FROM cart WHERE user_id=? AND product_id=? AND variant_id=? AND COALESCE(options_json,'')=?");
            $check->execute($vid === null ? [$uid, $pid, $optionsStore] : [$uid, $pid, $vid, $optionsStore]);
        } else {
            $check = $vid === null
                ? $pdo->prepare("SELECT id, qty FROM cart WHERE session_id=? AND product_id=? AND variant_id IS NULL AND COALESCE(options_json,'')=?")
                : $pdo->prepare("SELECT id, qty FROM cart WHERE session_id=? AND product_id=? AND variant_id=? AND COALESCE(options_json,'')=?");
            $check->execute($vid === null ? [$sid, $pid, $optionsStore] : [$sid, $pid, $vid, $optionsStore]);
        }
        $existing = $check->fetch();

        if ($existing) {
            $pdo->prepare("UPDATE cart SET qty=qty+?, options_json=? WHERE id=?")
                ->execute([$qty, $optionsStore, $existing['id']]);
        } else {
            if ($uid) {
                $pdo->prepare("INSERT INTO cart (user_id,product_id,variant_id,qty,options_json) VALUES (?,?,?,?,?)")
                    ->execute([$uid, $pid, $vid, $qty, $optionsStore]);
            } else {
                $pdo->prepare("INSERT INTO cart (session_id,product_id,variant_id,qty,options_json) VALUES (?,?,?,?,?)")
                    ->execute([$sid, $pid, $vid, $qty, $optionsStore]);
            }
        }
    } catch (Throwable $e) {
        // Fallback: try without options_json if column doesn't exist yet
        try {
            if ($uid) {
                $pdo->prepare("INSERT INTO cart (user_id,product_id,variant_id,qty) VALUES (?,?,?,?)")
                    ->execute([$uid, $pid, $vid, $qty]);
            } else {
                $pdo->prepare("INSERT INTO cart (session_id,product_id,variant_id,qty) VALUES (?,?,?,?)")
                    ->execute([$sid, $pid, $vid, $qty]);
            }
        } catch (Throwable $e2) {
            respond(false, 0, 'Could not add to cart: ' . $e2->getMessage());
        }
    }

    respond(true, cartCount($pdo, $uid, $sid), 'Added to cart');
}

// ─── REMOVE ──────────────────────────────────────────────────────────────────
if ($action === 'remove') {
    $id = (int)($_POST['id'] ?? 0);
    if ($uid) $pdo->prepare("DELETE FROM cart WHERE id=? AND user_id=?")->execute([$id, $uid]);
    else      $pdo->prepare("DELETE FROM cart WHERE id=? AND session_id=?")->execute([$id, $sid]);
    respond(true, cartCount($pdo, $uid, $sid));
}

// ─── UPDATE QTY ──────────────────────────────────────────────────────────────
if ($action === 'update') {
    $id  = (int)($_POST['id'] ?? 0);
    $qty = max(1, (int)($_POST['qty'] ?? 1));
    $pdo->prepare("UPDATE cart SET qty=? WHERE id=?")->execute([$qty, $id]);
    respond(true, cartCount($pdo, $uid, $sid));
}

// ─── COUNT ONLY ──────────────────────────────────────────────────────────────
if ($action === 'count') {
    respond(true, cartCount($pdo, $uid, $sid));
}

respond(false, 0, 'Unknown action: ' . htmlspecialchars($action));
