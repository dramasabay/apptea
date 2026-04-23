<?php
require_once __DIR__ . '/../includes/config.php';

$expectsJson = (
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
);

if (!isLoggedIn()) {
    if ($expectsJson) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please login first']);
        exit;
    }
    flash('error', 'Please login to access your wishlist');
    redirect(pretty_url('pages/login.php'));
}

$action = $_POST['action'] ?? 'toggle';
$productId = (int)($_POST['product_id'] ?? 0);
$wishlisted = false;
$message = 'Unknown action';

if ($productId > 0) {
    if ($action === 'toggle') {
        $check = $pdo->prepare("SELECT id FROM wishlist WHERE user_id=? AND product_id=?");
        $check->execute([$_SESSION['user_id'], $productId]);
        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM wishlist WHERE user_id=? AND product_id=?")->execute([$_SESSION['user_id'], $productId]);
            $wishlisted = false;
            $message = 'Removed from wishlist';
            flash('success', $message);
        } else {
            $pdo->prepare("INSERT INTO wishlist (user_id,product_id) VALUES (?,?)")->execute([$_SESSION['user_id'], $productId]);
            $wishlisted = true;
            $message = 'Added to wishlist';
            flash('success', $message);
        }
    } elseif ($action === 'remove') {
        $pdo->prepare("DELETE FROM wishlist WHERE user_id=? AND product_id=?")->execute([$_SESSION['user_id'], $productId]);
        $wishlisted = false;
        $message = 'Removed from wishlist';
        flash('success', $message);
    }
}

$count = getWishlistCount();
if ($expectsJson) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'wishlisted' => $wishlisted, 'count' => $count, 'message' => $message]);
    exit;
}

$redirect = $_SERVER['HTTP_REFERER'] ?? pretty_url('pages/wishlist.php');
redirect($redirect);
