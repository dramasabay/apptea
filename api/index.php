<?php
/**
 * TeaStore REST API – Router
 * Base URL: /api/
 * All endpoints return JSON.
 *
 * Authentication:
 *   POST /api/auth/login   → { token, user }
 *   POST /api/auth/register
 *   GET  /api/auth/me      → Bearer token required
 *
 * Products:
 *   GET  /api/products           → list (supports ?q=&cat=&brand=&tea=&sale=1&new=1&page=&limit=)
 *   GET  /api/products/{id}      → single product with variants & options
 *
 * Categories:
 *   GET  /api/categories
 *
 * Brands:
 *   GET  /api/brands
 *
 * Cart (session or token auth):
 *   GET  /api/cart
 *   POST /api/cart               → { product_id, qty, variant_id? }
 *   DELETE /api/cart/{id}
 *   PUT  /api/cart/{id}          → { qty }
 *
 * Orders:
 *   GET  /api/orders             → user's orders (auth required)
 *   GET  /api/orders/{id}        → order detail
 *   POST /api/orders             → create order
 *
 * Payment:
 *   POST /api/payment/stripe-intent   → { amount, ref } → { client_secret }
 *   POST /api/payment/paypal-create   → { amount, ref } → { order_id }
 *   POST /api/payment/paypal-capture  → { paypal_order_id, order_ref }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/stripe.php';
require_once __DIR__ . '/../includes/paypal.php';

// ── Helper functions ─────────────────────────────────────────────────────────
function apiSuccess($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}
function apiError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}
function apiPaginate(array $rows, int $total, int $page, int $limit): void {
    http_response_code(200);
    echo json_encode([
        'success'     => true,
        'data'        => $rows,
        'pagination'  => ['total' => $total, 'page' => $page, 'limit' => $limit, 'pages' => (int)ceil($total/$limit)],
    ]);
    exit;
}

// ── Auth helpers ─────────────────────────────────────────────────────────────
function generateToken(int $userId): string {
    return base64_encode(json_encode(['uid' => $userId, 'ts' => time(), 'sig' => hash_hmac('sha256', $userId . time(), DB_PASS)]));
}
function validateToken(?string $token): ?int {
    if (!$token) return null;
    try {
        $data = json_decode(base64_decode($token), true);
        if (!$data || !isset($data['uid'], $data['sig'], $data['ts'])) return null;
        if (time() - $data['ts'] > 86400 * 30) return null; // 30-day expiry
        $expected = hash_hmac('sha256', $data['uid'] . $data['ts'], DB_PASS);
        if (!hash_equals($expected, $data['sig'])) return null;
        return (int)$data['uid'];
    } catch (Throwable $e) { return null; }
}
function getAuthUserId(): ?int {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $m)) return validateToken(trim($m[1]));
    return null;
}
function requireAuth(): int {
    $uid = getAuthUserId();
    if (!$uid) apiError('Unauthorized. Please login.', 401);
    return $uid;
}

// ── Router ───────────────────────────────────────────────────────────────────
$requestUri  = $_SERVER['REQUEST_URI'] ?? '/';
$scriptName  = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$path        = '/' . ltrim(str_replace($scriptName, '', parse_url($requestUri, PHP_URL_PATH)), '/');
$method      = $_SERVER['REQUEST_METHOD'];
$parts       = array_values(array_filter(explode('/', $path)));
// $parts[0] should be 'api', then route follows
// Strip leading 'api' if present
if (($parts[0] ?? '') === 'api') array_shift($parts);

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$resource  = $parts[0] ?? '';
$subRes    = $parts[1] ?? '';
$idOrSub2  = $parts[2] ?? '';

// ════════════════════════════════════════════════════════════════════════
// AUTH endpoints
// ════════════════════════════════════════════════════════════════════════
if ($resource === 'auth') {
    if ($subRes === 'login' && $method === 'POST') {
        $email    = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        if (!$email || !$password) apiError('Email and password required.');
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password'])) apiError('Invalid credentials.', 401);
        $token = generateToken((int)$user['id']);
        apiSuccess(['token' => $token, 'user' => ['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role']]]);
    }
    if ($subRes === 'register' && $method === 'POST') {
        $name     = trim($input['name'] ?? '');
        $email    = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $phone    = trim($input['phone'] ?? '');
        if (!$name || !$email || !$password) apiError('Name, email and password required.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) apiError('Invalid email address.');
        if (strlen($password) < 6) apiError('Password must be at least 6 characters.');
        $hash = password_hash($password, PASSWORD_BCRYPT);
        try {
            $pdo->prepare("INSERT INTO users (name,email,password,phone) VALUES (?,?,?,?)")->execute([$name,$email,$hash,$phone]);
            $uid   = (int)$pdo->lastInsertId();
            $token = generateToken($uid);
            apiSuccess(['token' => $token, 'user' => ['id'=>$uid,'name'=>$name,'email'=>$email,'role'=>'customer']], 201);
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) apiError('Email already registered.');
            apiError('Registration failed.', 500);
        }
    }
    if ($subRes === 'me' && $method === 'GET') {
        $uid  = requireAuth();
        $stmt = $pdo->prepare("SELECT id,name,email,phone,address,role,created_at FROM users WHERE id=?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        if (!$user) apiError('User not found.', 404);
        apiSuccess($user);
    }
    apiError('Auth endpoint not found.', 404);
}

// ════════════════════════════════════════════════════════════════════════
// PRODUCTS
// ════════════════════════════════════════════════════════════════════════
if ($resource === 'products') {
    if ($method === 'GET' && !$subRes) {
        $q       = trim($_GET['q']     ?? '');
        $cat     = trim($_GET['cat']   ?? '');
        $brand   = (int)($_GET['brand'] ?? 0);
        $tea     = trim($_GET['tea']   ?? '');
        $sale    = ($_GET['sale'] ?? '') === '1';
        $new     = ($_GET['new']  ?? '') === '1';
        $feat    = ($_GET['featured'] ?? '') === '1';
        $page    = max(1, (int)($_GET['page']  ?? 1));
        $limit   = max(1, min(48, (int)($_GET['limit'] ?? 16)));
        $offset  = ($page - 1) * $limit;

        $where = ['1=1']; $params = [];
        if ($q)     { $where[] = "(p.name LIKE ? OR p.description LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($cat)   { $where[] = "c.slug = ?"; $params[] = $cat; }
        if ($brand) { $where[] = "p.brand_id = ?"; $params[] = $brand; }
        if ($tea)   { $where[] = "p.tea_type = ?"; $params[] = $tea; }
        if ($sale)  { $where[] = "p.sale_price IS NOT NULL"; }
        if ($new)   { $where[] = "p.is_new = 1"; }
        if ($feat)  { $where[] = "p.featured = 1"; }

        $whereStr = implode(' AND ', $where);
        $total = (int)teastoreSafeQueryValue("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE $whereStr", $params);
        $rows  = teastoreSafeQueryAll("SELECT p.id,p.name,p.slug,p.short_desc,p.price,p.sale_price,p.stock,p.image,p.tea_type,p.featured,p.is_new,p.created_at,b.name AS brand_name,c.name AS category_name FROM products p LEFT JOIN brands b ON p.brand_id=b.id LEFT JOIN categories c ON p.category_id=c.id WHERE $whereStr ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset", $params);
        foreach ($rows as &$r) {
            $r['image_url'] = $r['image'] ? site_url('assets/img/products/' . $r['image']) : null;
        }
        apiPaginate($rows, $total, $page, $limit);
    }
    if ($method === 'GET' && $subRes) {
        $id   = (int)$subRes;
        $stmt = $pdo->prepare("SELECT p.*,b.name AS brand_name,c.name AS category_name FROM products p LEFT JOIN brands b ON p.brand_id=b.id LEFT JOIN categories c ON p.category_id=c.id WHERE p.id=? OR p.slug=?");
        $stmt->execute([$id, $subRes]);
        $product = $stmt->fetch();
        if (!$product) apiError('Product not found.', 404);
        $product['image_url'] = $product['image'] ? site_url('assets/img/products/' . $product['image']) : null;
        // Variants
        $variants = teastoreSafeQueryAll("SELECT * FROM product_variants WHERE product_id=?", [$product['id']]);
        $product['variants'] = $variants;
        // Option groups
        $groups = teastoreSafeQueryAll("SELECT * FROM product_option_groups WHERE product_id=? ORDER BY sort_order", [$product['id']]);
        foreach ($groups as &$g) {
            $g['items'] = teastoreSafeQueryAll("SELECT * FROM product_option_items WHERE group_id=? ORDER BY sort_order", [$g['id']]);
        }
        $product['option_groups'] = $groups;
        // Reviews
        $product['reviews'] = teastoreSafeQueryAll("SELECT r.*,u.name AS user_name FROM reviews r JOIN users u ON r.user_id=u.id WHERE r.product_id=? ORDER BY r.created_at DESC LIMIT 20", [$product['id']]);
        apiSuccess($product);
    }
    apiError('Method not allowed.', 405);
}

// ════════════════════════════════════════════════════════════════════════
// CATEGORIES
// ════════════════════════════════════════════════════════════════════════
if ($resource === 'categories' && $method === 'GET') {
    $rows = teastoreSafeQueryAll("SELECT c.*,COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id ORDER BY c.name");
    apiSuccess($rows);
}

// ════════════════════════════════════════════════════════════════════════
// BRANDS
// ════════════════════════════════════════════════════════════════════════
if ($resource === 'brands' && $method === 'GET') {
    $rows = teastoreSafeQueryAll("SELECT b.*,COUNT(p.id) AS product_count FROM brands b LEFT JOIN products p ON p.brand_id=b.id GROUP BY b.id ORDER BY b.name");
    apiSuccess($rows);
}

// ════════════════════════════════════════════════════════════════════════
// CART
// ════════════════════════════════════════════════════════════════════════
if ($resource === 'cart') {
    $uid = getAuthUserId();
    $sid = $uid ? null : (isset($_SERVER['HTTP_X_SESSION_ID']) ? $_SERVER['HTTP_X_SESSION_ID'] : session_id());

    if ($method === 'GET') {
        $items = $uid
            ? teastoreSafeQueryAll("SELECT c.*,p.name,p.image,p.slug,COALESCE(v.price,p.sale_price,p.price) AS unit_price FROM cart c JOIN products p ON c.product_id=p.id LEFT JOIN product_variants v ON c.variant_id=v.id WHERE c.user_id=?", [$uid])
            : teastoreSafeQueryAll("SELECT c.*,p.name,p.image,p.slug,COALESCE(v.price,p.sale_price,p.price) AS unit_price FROM cart c JOIN products p ON c.product_id=p.id LEFT JOIN product_variants v ON c.variant_id=v.id WHERE c.session_id=?", [$sid]);
        foreach ($items as &$i) {
            $i['image_url'] = $i['image'] ? site_url('assets/img/products/'.$i['image']) : null;
        }
        $subtotal = array_sum(array_map(fn($i) => $i['unit_price'] * $i['qty'], $items));
        apiSuccess(['items' => $items, 'subtotal' => $subtotal, 'count' => count($items)]);
    }
    if ($method === 'POST') {
        $pid = (int)($input['product_id'] ?? 0);
        $qty = max(1, (int)($input['qty'] ?? 1));
        $vid = (int)($input['variant_id'] ?? 0) ?: null;
        if (!$pid) apiError('product_id required.');
        $stmt = $pdo->prepare("SELECT id,stock FROM products WHERE id=?"); $stmt->execute([$pid]);
        $prod = $stmt->fetch();
        if (!$prod) apiError('Product not found.', 404);
        if ($uid) {
            $existing = teastoreSafeQueryAll("SELECT id,qty FROM cart WHERE user_id=? AND product_id=? AND (variant_id=? OR (variant_id IS NULL AND ? IS NULL))", [$uid,$pid,$vid,$vid]);
        } else {
            $existing = teastoreSafeQueryAll("SELECT id,qty FROM cart WHERE session_id=? AND product_id=? AND (variant_id=? OR (variant_id IS NULL AND ? IS NULL))", [$sid,$pid,$vid,$vid]);
        }
        if ($existing) {
            $pdo->prepare("UPDATE cart SET qty=qty+? WHERE id=?")->execute([$qty, $existing[0]['id']]);
        } else {
            if ($uid) $pdo->prepare("INSERT INTO cart (user_id,product_id,variant_id,qty) VALUES (?,?,?,?)")->execute([$uid,$pid,$vid,$qty]);
            else       $pdo->prepare("INSERT INTO cart (session_id,product_id,variant_id,qty) VALUES (?,?,?,?)")->execute([$sid,$pid,$vid,$qty]);
        }
        apiSuccess(['message' => 'Added to cart'], 201);
    }
    if ($method === 'PUT' && $subRes) {
        $cartId = (int)$subRes;
        $qty    = max(0, (int)($input['qty'] ?? 0));
        if ($qty === 0) {
            $pdo->prepare("DELETE FROM cart WHERE id=?")->execute([$cartId]);
        } else {
            $pdo->prepare("UPDATE cart SET qty=? WHERE id=?")->execute([$qty, $cartId]);
        }
        apiSuccess(['message' => 'Cart updated']);
    }
    if ($method === 'DELETE' && $subRes) {
        $pdo->prepare("DELETE FROM cart WHERE id=?")->execute([(int)$subRes]);
        apiSuccess(['message' => 'Item removed']);
    }
    apiError('Method not allowed.', 405);
}

// ════════════════════════════════════════════════════════════════════════
// ORDERS
// ════════════════════════════════════════════════════════════════════════
if ($resource === 'orders') {
    $uid = getAuthUserId();

    if ($method === 'GET' && !$subRes) {
        if (!$uid) apiError('Authentication required.', 401);
        $rows = teastoreSafeQueryAll("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC", [$uid]);
        apiSuccess($rows);
    }
    if ($method === 'GET' && $subRes) {
        if (!$uid) apiError('Authentication required.', 401);
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
        $stmt->execute([(int)$subRes, $uid]);
        $order = $stmt->fetch();
        if (!$order) apiError('Order not found.', 404);
        $order['items'] = teastoreSafeQueryAll("SELECT * FROM order_items WHERE order_id=?", [$order['id']]);
        apiSuccess($order);
    }
    if ($method === 'POST') {
        // Create order
        $name       = trim($input['name']    ?? '');
        $email      = trim($input['email']   ?? '');
        $phone      = trim($input['phone']   ?? '');
        $address    = trim($input['address'] ?? '');
        $notes      = trim($input['notes']   ?? '');
        $payment    = in_array($input['payment'] ?? '', ['stripe','paypal']) ? $input['payment'] : 'stripe';
        $piId       = trim($input['payment_intent_id'] ?? '');
        $ppOrderId  = trim($input['paypal_order_id']   ?? '');
        $cartItems  = $input['items'] ?? []; // [{product_id, qty, variant_id?, options_json?}]

        if (!$name || !$email || !$phone || !$address) apiError('name, email, phone, address required.');
        if (empty($cartItems)) apiError('Order must contain items.');

        $subtotal = 0; $orderItemsData = [];
        foreach ($cartItems as $ci) {
            $pid = (int)($ci['product_id'] ?? 0);
            $qty = max(1, (int)($ci['qty'] ?? 1));
            $vid = (int)($ci['variant_id'] ?? 0) ?: null;
            $stmt = $pdo->prepare("SELECT p.*,COALESCE(v.price,p.sale_price,p.price) AS final_price FROM products p LEFT JOIN product_variants v ON (v.id=? AND v.product_id=p.id) WHERE p.id=?");
            $stmt->execute([$vid, $pid]);
            $prod = $stmt->fetch();
            if (!$prod) apiError("Product $pid not found.");
            $price = (float)$prod['final_price'];
            $subtotal += $price * $qty;
            $orderItemsData[] = ['product_id'=>$pid,'name'=>$prod['name'],'variant_info'=>$ci['options_json']??'','qty'=>$qty,'price'=>$price];
        }
        $threshold = (float)getSetting('free_delivery_threshold', '49');
        $shipping  = $subtotal >= $threshold ? 0 : (float)getSetting('delivery_fee', '3.50');
        $total     = $subtotal + $shipping;
        $orderNum  = 'TS-' . strtoupper(substr(uniqid(), -8));
        $piField   = $payment === 'stripe' ? $piId : $ppOrderId;
        $payStatus = ($piId || $ppOrderId) ? 'paid' : 'pending';

        $pdo->prepare("INSERT INTO orders (user_id,order_number,subtotal,shipping,total,name,email,phone,address,payment_method,payment_status,payment_intent_id,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$uid,$orderNum,$subtotal,$shipping,$total,$name,$email,$phone,$address,$payment,$payStatus,$piField,$notes]);
        $orderId = $pdo->lastInsertId();
        foreach ($orderItemsData as $oi) {
            $pdo->prepare("INSERT INTO order_items (order_id,product_id,product_name,variant_info,qty,price) VALUES (?,?,?,?,?,?)")
                ->execute([$orderId,$oi['product_id'],$oi['name'],$oi['variant_info'],$oi['qty'],$oi['price']]);
            $pdo->prepare("UPDATE products SET stock=GREATEST(0,stock-?) WHERE id=?")->execute([$oi['qty'],$oi['product_id']]);
        }
        if ($uid) $pdo->prepare("DELETE FROM cart WHERE user_id=?")->execute([$uid]);

        apiSuccess(['order_number' => $orderNum, 'order_id' => $orderId, 'total' => $total, 'payment_status' => $payStatus], 201);
    }
    apiError('Method not allowed.', 405);
}

// ════════════════════════════════════════════════════════════════════════
// PAYMENT endpoints
// ════════════════════════════════════════════════════════════════════════
if ($resource === 'payment') {
    $currency = getSetting('currency_code', 'USD');

    if ($subRes === 'stripe-intent' && $method === 'POST') {
        $amount = (float)($input['amount'] ?? 0);
        $ref    = trim($input['ref'] ?? ('TS-' . date('YmdHis')));
        if ($amount <= 0) apiError('Invalid amount.');
        if (!getSetting('stripe_secret_key', '')) apiError('Stripe not configured.');
        $result = stripeCreatePaymentIntent($amount, $currency, $ref);
        if (!$result['ok']) apiError($result['error'] ?? 'Stripe error.');
        apiSuccess(['client_secret' => $result['client_secret'], 'payment_intent_id' => $result['payment_intent_id']]);
    }

    if ($subRes === 'paypal-create' && $method === 'POST') {
        $amount = (float)($input['amount'] ?? 0);
        $ref    = trim($input['ref'] ?? ('TS-' . date('YmdHis')));
        if ($amount <= 0) apiError('Invalid amount.');
        if (!getSetting('paypal_client_id', '')) apiError('PayPal not configured.');
        $result = paypalCreateOrder($amount, $currency, $ref);
        if (!$result['ok']) apiError($result['error'] ?? 'PayPal error.');
        apiSuccess(['order_id' => $result['order_id'], 'approve_url' => $result['approve_url']]);
    }

    if ($subRes === 'paypal-capture' && $method === 'POST') {
        $ppOrderId = trim($input['paypal_order_id'] ?? '');
        $orderRef  = trim($input['order_ref'] ?? '');
        if (!$ppOrderId) apiError('paypal_order_id required.');
        $result = paypalCaptureOrder($ppOrderId);
        if ($result['ok'] && $orderRef) {
            try { $pdo->prepare("UPDATE orders SET payment_status='paid',payment_intent_id=? WHERE order_number=?")->execute([$ppOrderId,$orderRef]); } catch (Throwable $e) {}
        }
        if (!$result['ok']) apiError('PayPal capture failed: ' . ($result['error'] ?? ''));
        apiSuccess(['status' => $result['status']]);
    }

    apiError('Payment endpoint not found.', 404);
}

// ════════════════════════════════════════════════════════════════════════
// Settings (public-facing only)
// ════════════════════════════════════════════════════════════════════════
if ($resource === 'settings' && $method === 'GET') {
    $publicKeys = ['site_name','site_tagline','currency_symbol','currency_code','free_delivery_threshold','delivery_fee','announcement_bar','announcement_bar_enabled'];
    $result = [];
    foreach ($publicKeys as $k) $result[$k] = getSetting($k);
    apiSuccess($result);
}

// 404
apiError('API endpoint not found. See /api/ for documentation.', 404);
