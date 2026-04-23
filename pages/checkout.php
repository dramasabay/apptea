<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/stripe.php';
require_once __DIR__ . '/../includes/paypal.php';
require_once __DIR__ . '/../includes/telegram.php';
$pageTitle = 'Checkout – ' . getSetting('site_name', 'TeaStore');

$sid = session_id();
$uid = $_SESSION['user_id'] ?? null;

// Load cart items
$stmt = $uid
    ? $pdo->prepare("SELECT c.*,p.name,p.image,p.tea_type,COALESCE(v.price,p.sale_price,p.price) AS base_price,c.options_json FROM cart c JOIN products p ON c.product_id=p.id LEFT JOIN product_variants v ON c.variant_id=v.id WHERE c.user_id=?")
    : $pdo->prepare("SELECT c.*,p.name,p.image,p.tea_type,COALESCE(v.price,p.sale_price,p.price) AS base_price,c.options_json FROM cart c JOIN products p ON c.product_id=p.id LEFT JOIN product_variants v ON c.variant_id=v.id WHERE c.session_id=?");
$stmt->execute([$uid ?? $sid]);
$items = $stmt->fetchAll();
if (empty($items)) { redirect(pretty_url('pages/cart.php')); }

foreach ($items as &$item) {
    $optData = json_decode($item['options_json'] ?? '{}', true) ?: [];
    $extra   = 0;
    if (isset($optData['extra']))          $extra = (float)$optData['extra'];
    elseif (isset($optData['selections'])) foreach ($optData['selections'] as $sel) $extra += (float)($sel['price_add'] ?? 0);
    $item['options_extra'] = $extra;
    $item['options_label'] = $optData['text'] ?? ($optData['label'] ?? '');
    $item['unit_price']    = (float)$item['base_price'] + $extra;
}
unset($item);

$subtotal      = array_sum(array_map(fn($i) => $i['unit_price'] * $i['qty'], $items));
$freeThreshold = (float)getSetting('free_delivery_threshold', '49');
$deliveryFee   = (float)getSetting('delivery_fee', '3.50');
$shipping      = $subtotal >= $freeThreshold ? 0 : $deliveryFee;
$total         = $subtotal + $shipping;
$stripeEnabled = getSetting('stripe_enabled', '1') === '1';
$paypalEnabled = getSetting('paypal_enabled', '1') === '1';
$stripeKey     = trim((string)getSetting('stripe_publishable_key', ''));
$currency      = getSetting('currency_code', 'USD');
$orderRef      = 'TS-' . strtoupper(substr(uniqid(), -8));

// ─── POST: Place Order ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = sanitize($_POST['name']    ?? '');
    $email         = sanitize($_POST['email']   ?? '');
    $phone         = sanitize($_POST['phone']   ?? '');
    $address       = sanitize($_POST['address'] ?? '');
    $notes         = sanitize($_POST['notes']   ?? '');
    $payment       = in_array($_POST['payment'] ?? '', ['stripe','paypal']) ? $_POST['payment'] : 'stripe';
    $paymentIntentId = sanitize($_POST['payment_intent_id'] ?? '');
    $paypalOrderId   = sanitize($_POST['paypal_order_id']   ?? '');
    $paymentStatus   = sanitize($_POST['payment_status_field'] ?? 'pending');
    $orderNum        = sanitize($_POST['order_ref'] ?? $orderRef);

    $errors = [];
    if (!$name)    $errors[] = 'Full name is required';
    if (!$phone)   $errors[] = 'Phone number is required';
    if (!$email)   $errors[] = 'Email is required';
    if (!$address) $errors[] = 'Delivery address is required';

    // Verify Stripe payment
    if (!$errors && $payment === 'stripe') {
        if (!$paymentIntentId) {
            $errors[] = 'Please complete Stripe card payment before placing order.';
        } else {
            $pi = stripeGetPaymentIntent($paymentIntentId);
            if (!$pi['ok'] || !in_array($pi['status'] ?? '', ['succeeded','requires_capture'])) {
                $errors[] = 'Stripe payment not confirmed. Please try again.';
            } else {
                $paymentStatus = 'paid';
            }
        }
    }

    // Verify PayPal payment
    if (!$errors && $payment === 'paypal') {
        if (!$paypalOrderId) {
            $errors[] = 'Please complete PayPal payment before placing order.';
        } else {
            if ($paymentStatus !== 'paid') {
                $capture = paypalCaptureOrder($paypalOrderId);
                $paymentStatus = $capture['ok'] ? 'paid' : 'pending';
            }
            $paymentIntentId = $paypalOrderId;
        }
    }

    if ($errors) {
        flash('error', implode(' · ', $errors));
    } else {
        try {
            $pdo->prepare("INSERT INTO orders (user_id,order_number,subtotal,shipping,total,name,email,phone,address,payment_method,payment_status,payment_intent_id,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$uid, $orderNum, $subtotal, $shipping, $total, $name, $email, $phone, $address, $payment, $paymentStatus, $paymentIntentId, $notes]);
        } catch (\Exception $e) {
            $pdo->prepare("INSERT INTO orders (user_id,order_number,subtotal,shipping,total,name,email,phone,address,payment_method,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$uid, $orderNum, $subtotal, $shipping, $total, $name, $email, $phone, $address, $payment, $notes]);
        }
        $orderId    = $pdo->lastInsertId();
        $orderItems = [];
        foreach ($items as $item) {
            $pdo->prepare("INSERT INTO order_items (order_id,product_id,product_name,variant_info,qty,price) VALUES (?,?,?,?,?,?)")
                ->execute([$orderId, $item['product_id'], $item['name'], $item['options_json'] ?? '', $item['qty'], $item['unit_price']]);
            $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id=?")->execute([$item['qty'], $item['product_id']]);
            $orderItems[] = ['product_name' => $item['name'], 'qty' => $item['qty'], 'price' => $item['unit_price']];
        }
        if ($uid) $pdo->prepare("DELETE FROM cart WHERE user_id=?")->execute([$uid]);
        else      $pdo->prepare("DELETE FROM cart WHERE session_id=?")->execute([$sid]);

        notifyNewOrder(['order_number' => $orderNum, 'name' => $name, 'phone' => $phone,
            'address' => $address, 'payment_method' => $payment, 'total' => $total], $orderItems);

        redirect(pretty_url('pages/order-success.php') . '?order=' . rawurlencode($orderNum) . '&payment=' . rawurlencode($payment) . '&total=' . rawurlencode(number_format($total, 2, '.', '')));
    }
}

$user = null;
if ($uid) { $s = $pdo->prepare("SELECT * FROM users WHERE id=?"); $s->execute([$uid]); $user = $s->fetch(); }
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.checkout-section{background:#fff;border:1.5px solid #e5e7eb;border-radius:20px;padding:24px;margin-bottom:16px;}
.payment-method{display:flex;align-items:center;padding:16px 18px;border:2px solid #e5e7eb;border-radius:16px;transition:all .2s;cursor:pointer;background:#fff;gap:14px;margin-bottom:10px;}
.payment-method.active{border-color:var(--primary);background:var(--primary-alpha,#f0fdf4);}
.pm-dot{width:20px;height:20px;border-radius:50%;border:2px solid #d1d5db;flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.pm-dot.active{border-color:var(--primary);}
.form-group{margin-bottom:14px;}
.form-group label{display:block;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;}
.form-group input,.form-group textarea{width:100%;border:1.5px solid #e5e7eb;border-radius:12px;padding:12px 14px;font-size:14px;outline:none;transition:border-color .2s;font-family:inherit;color:#111827;box-sizing:border-box;}
.form-group input:focus,.form-group textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(45,106,79,0.08);}
.summary-line{display:flex;justify-content:space-between;font-size:13px;padding:5px 0;}
.checkout-grid{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:24px;align-items:start;}
.checkout-order-item{display:flex;gap:12px;align-items:flex-start;padding:12px;border:1px solid #eef2f7;border-radius:18px;background:#fcfcfd;margin-bottom:8px;}
.checkout-order-thumb{width:52px;height:52px;border-radius:14px;background:#f8fafc;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0}
.checkout-order-thumb img{width:100%;height:100%;object-fit:cover}
#stripe-card-element{border:1.5px solid #e5e7eb;border-radius:12px;padding:14px;background:#fff;transition:border-color .2s;}
#stripe-card-element.StripeElement--focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(45,106,79,0.08);}
#stripe-card-errors{color:#dc2626;font-size:13px;margin-top:8px;min-height:20px;}
#paypal-button-container{min-height:50px;}
.place-order-btn{width:100%;padding:16px;background:var(--primary);color:#fff;border:none;border-radius:16px;font-size:16px;font-weight:700;cursor:pointer;transition:background .2s;margin-top:12px;}
.place-order-btn:hover{background:var(--primary-dark,#1e4d38);}
.place-order-btn:disabled{background:#9ca3af;cursor:not-allowed;}
@media(max-width:900px){.checkout-grid{grid-template-columns:1fr!important;}}
</style>

<div class="container" style="max-width:1200px;padding:24px 16px;">
    <div style="margin-bottom:20px;">
        <h1 style="font-size:22px;font-weight:800;color:var(--text)">Checkout</h1>
        <div style="font-size:13px;color:var(--text-light);margin-top:4px;">
            <a href="<?= pretty_url('index.php') ?>" style="color:var(--primary)">Home</a> › 
            <a href="<?= pretty_url('pages/cart.php') ?>" style="color:var(--primary)">Cart</a> › Checkout
        </div>
    </div>

    <?php if ($err = flash('error')): ?>
    <div style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:14px 18px;border-radius:14px;margin-bottom:16px;font-size:14px;">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?>
    </div>
    <?php endif; ?>

    <form id="checkoutForm" method="POST">
        <input type="hidden" name="order_ref" id="orderRefInput" value="<?= htmlspecialchars($orderRef) ?>">
        <input type="hidden" name="payment_intent_id" id="paymentIntentInput" value="">
        <input type="hidden" name="paypal_order_id" id="paypalOrderInput" value="">
        <input type="hidden" name="payment_status_field" id="paymentStatusField" value="pending">
        <input type="hidden" name="payment" id="paymentMethodInput" value="<?= $stripeEnabled ? 'stripe' : 'paypal' ?>">

        <div class="checkout-grid">
            <!-- LEFT: Form -->
            <div>
                <!-- Contact Info -->
                <div class="checkout-section">
                    <h3 style="font-size:16px;font-weight:800;margin-bottom:16px;">📋 Contact Information</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Full Name *</label>
                            <input type="text" name="name" required value="<?= htmlspecialchars($user['name'] ?? '') ?>" placeholder="Your full name">
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" required value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="email@example.com">
                        </div>
                        <div class="form-group">
                            <label>Phone *</label>
                            <input type="tel" name="phone" required value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+1 234 567 8900">
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Delivery Address *</label>
                            <input type="text" name="address" required value="<?= htmlspecialchars($user['address'] ?? '') ?>" placeholder="Street, City, Country">
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Order Notes (optional)</label>
                            <textarea name="notes" rows="2" placeholder="Special instructions, preferred delivery time..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Payment -->
                <div class="checkout-section">
                    <h3 style="font-size:16px;font-weight:800;margin-bottom:16px;">💳 Payment Method</h3>

                    <?php if ($stripeEnabled): ?>
                    <div class="payment-method active" id="pm-stripe" onclick="selectPayment('stripe')" aria-checked="true">
                        <input type="radio" name="_pm_radio" value="stripe" checked style="display:none">
                        <div style="width:42px;height:28px;background:#635bff;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <span style="color:#fff;font-weight:900;font-size:11px;">STRIPE</span>
                        </div>
                        <div style="flex:1;">
                            <div style="font-weight:700;font-size:13px;">💳 Credit / Debit Card</div>
                            <div style="font-size:11px;color:#6b7280;">Visa, Mastercard, Amex & more · Secured by Stripe</div>
                        </div>
                        <div class="pm-dot active" id="dot-stripe"><div style="width:10px;height:10px;border-radius:50%;background:var(--primary);" id="dot-stripe-inner"></div></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($paypalEnabled): ?>
                    <div class="payment-method <?= !$stripeEnabled ? 'active' : '' ?>" id="pm-paypal" onclick="selectPayment('paypal')" aria-checked="<?= !$stripeEnabled ? 'true' : 'false' ?>">
                        <input type="radio" name="_pm_radio" value="paypal" style="display:none" <?= !$stripeEnabled ? 'checked' : '' ?>>
                        <div style="width:42px;height:28px;background:#003087;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <span style="color:#fff;font-weight:900;font-size:10px;">PayPal</span>
                        </div>
                        <div style="flex:1;">
                            <div style="font-weight:700;font-size:13px;">🅿️ PayPal</div>
                            <div style="font-size:11px;color:#6b7280;">Pay with your PayPal account · Fast & secure</div>
                        </div>
                        <div class="pm-dot <?= !$stripeEnabled ? 'active' : '' ?>" id="dot-paypal"><div style="width:10px;height:10px;border-radius:50%;background:var(--primary);display:<?= !$stripeEnabled ? 'block' : 'none' ?>;" id="dot-paypal-inner"></div></div>
                    </div>
                    <?php endif; ?>

                    <!-- Stripe Card Form -->
                    <?php if ($stripeEnabled && $stripeKey): ?>
                    <div id="stripe-panel" style="margin-top:16px;">
                        <div class="form-group">
                            <label>Card Details</label>
                            <div id="stripe-card-element"></div>
                            <div id="stripe-card-errors"></div>
                        </div>
                        <div style="font-size:11px;color:#9ca3af;text-align:center;margin-top:8px;">
                            🔒 Your card info is encrypted and never stored on our servers
                        </div>
                    </div>
                    <?php elseif ($stripeEnabled && !$stripeKey): ?>
                    <div id="stripe-panel" style="margin-top:16px;background:#fefce8;border:1px solid #fde047;border-radius:12px;padding:12px 14px;font-size:13px;color:#854d0e;">
                        ⚙️ Stripe is enabled but not yet configured. Add your publishable & secret keys in Admin → Settings → Payment.
                    </div>
                    <?php endif; ?>

                    <!-- PayPal Button Container -->
                    <div id="paypal-panel" style="margin-top:16px;display:none;">
                        <div id="paypal-button-container"></div>
                        <div style="font-size:11px;color:#9ca3af;text-align:center;margin-top:8px;">
                            🔒 You'll be redirected to PayPal to approve payment, then returned here.
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Order Summary -->
            <div class="checkout-summary-card" style="position:sticky;top:80px;">
                <div class="checkout-section">
                    <h3 style="font-size:16px;font-weight:800;margin-bottom:16px;">🛒 Order Summary</h3>
                    <?php foreach ($items as $item): ?>
                    <div class="checkout-order-item">
                        <div class="checkout-order-thumb">
                            <?php if ($item['image']): ?>
                            <img src="<?= SITE_URL ?>/assets/img/products/<?= htmlspecialchars($item['image']) ?>" alt="">
                            <?php else: ?>
                            <span style="font-size:22px;">🍵</span>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:700;color:#172033;line-height:1.4"><?= htmlspecialchars($item['name']) ?></div>
                            <?php if ($item['options_label']): ?>
                            <div style="font-size:11px;color:#6b7280;"><?= htmlspecialchars($item['options_label']) ?></div>
                            <?php endif; ?>
                            <div style="font-size:11px;color:#6b7280;">Qty: <?= (int)$item['qty'] ?></div>
                        </div>
                        <div style="font-size:13px;font-weight:800;color:#0f172a;white-space:nowrap"><?= formatPrice($item['unit_price'] * $item['qty']) ?></div>
                    </div>
                    <?php endforeach; ?>

                    <div style="border-top:1px solid #f1f5f9;margin:12px 0;padding-top:12px;">
                        <div class="summary-line"><span style="color:#6b7280;">Subtotal</span><span><?= formatPrice($subtotal) ?></span></div>
                        <div class="summary-line">
                            <span style="color:#6b7280;">Shipping</span>
                            <span><?= $shipping == 0 ? '<span style="color:#16a34a;font-weight:700;">FREE</span>' : formatPrice($shipping) ?></span>
                        </div>
                        <?php if ($shipping > 0): ?>
                        <div style="font-size:11px;color:#9ca3af;text-align:right;margin-top:2px;">
                            Add <?= formatPrice($freeThreshold - $subtotal) ?> more for free shipping
                        </div>
                        <?php endif; ?>
                        <div class="summary-line" style="font-size:16px;font-weight:800;border-top:1px solid #e5e7eb;margin-top:8px;padding-top:8px;">
                            <span>Total</span><span><?= formatPrice($total) ?></span>
                        </div>
                    </div>

                    <button type="button" class="place-order-btn" id="placeOrderBtn" onclick="handlePlaceOrder()">
                        <i class="fas fa-lock" style="margin-right:8px;"></i>Place Order · <?= formatPrice($total) ?>
                    </button>
                    <p style="text-align:center;font-size:11px;color:#9ca3af;margin-top:10px;">🔒 SSL encrypted · Secure checkout</p>
                </div>
            </div>
        </div><!-- .checkout-grid -->
    </form>
</div>

<?php if ($stripeEnabled && $stripeKey): ?>
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>
<?php
$paypalClientId = getSetting('paypal_client_id', '');
$paypalMode     = getSetting('paypal_mode', 'sandbox');
if ($paypalEnabled && $paypalClientId):
?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars($paypalClientId) ?>&currency=<?= htmlspecialchars($currency) ?>"></script>
<?php endif; ?>

<script>
const SITE_URL_JS    = <?= json_encode(SITE_URL) ?>;
const ORDER_TOTAL    = <?= json_encode(number_format($total, 2, '.', '')) ?>;
const ORDER_REF      = <?= json_encode($orderRef) ?>;
const STRIPE_PUB_KEY = <?= json_encode($stripeKey) ?>;
const STRIPE_ENABLED = <?= json_encode($stripeEnabled && $stripeKey) ?>;
const PAYPAL_ENABLED = <?= json_encode($paypalEnabled && $paypalClientId) ?>;

let _currentPayment = STRIPE_ENABLED ? 'stripe' : 'paypal';
let _stripeConfirmed = false;
let _paypalConfirmed = false;
let _stripe = null, _cardElement = null, _stripeClientSecret = null;

// ── Payment method selector ──────────────────────────────────────────────────
function selectPayment(val) {
    _currentPayment = val;
    document.getElementById('paymentMethodInput').value = val;
    ['stripe', 'paypal'].forEach(id => {
        const pm  = document.getElementById('pm-' + id);
        const dot = document.getElementById('dot-' + id);
        const inn = document.getElementById('dot-' + id + '-inner');
        if (!pm) return;
        if (id === val) {
            pm.classList.add('active');
            pm.setAttribute('aria-checked', 'true');
            if (dot) dot.classList.add('active');
            if (inn) inn.style.display = 'block';
        } else {
            pm.classList.remove('active');
            pm.setAttribute('aria-checked', 'false');
            if (dot) dot.classList.remove('active');
            if (inn) inn.style.display = 'none';
        }
    });
    const stripePanel = document.getElementById('stripe-panel');
    const paypalPanel = document.getElementById('paypal-panel');
    if (stripePanel) stripePanel.style.display = val === 'stripe' ? '' : 'none';
    if (paypalPanel) paypalPanel.style.display = val === 'paypal' ? '' : 'none';
}

// ── Form validation ──────────────────────────────────────────────────────────
function validateForm() {
    const required = document.querySelectorAll('#checkoutForm input[required], #checkoutForm textarea[required]');
    for (const el of required) {
        if (!String(el.value || '').trim()) {
            el.focus();
            el.style.borderColor = 'var(--primary)';
            setTimeout(() => el.style.borderColor = '', 2000);
            return false;
        }
    }
    return true;
}

// ── Main place-order handler ─────────────────────────────────────────────────
async function handlePlaceOrder() {
    if (!validateForm()) return;
    const btn = document.getElementById('placeOrderBtn');

    if (_currentPayment === 'stripe' && STRIPE_ENABLED) {
        if (_stripeConfirmed) { submitForm(); return; }
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing card...';
        await processStripePayment(btn);
    } else if (_currentPayment === 'paypal' && PAYPAL_ENABLED) {
        if (_paypalConfirmed) { submitForm(); return; }
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Opening PayPal...';
        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock" style="margin-right:8px;"></i>Place Order · <?= formatPrice($total) ?>';
        }, 2000);
        // PayPal handled by SDK buttons
    } else {
        submitForm();
    }
}

// ── Stripe ───────────────────────────────────────────────────────────────────
async function processStripePayment(btn) {
    if (!_stripe || !_cardElement) {
        setStripeError('Stripe not loaded. Please refresh.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock" style="margin-right:8px;"></i>Place Order · <?= formatPrice($total) ?>';
        return;
    }
    // Create PaymentIntent
    const fd = new FormData();
    fd.append('amount', ORDER_TOTAL);
    fd.append('ref', document.getElementById('orderRefInput').value);
    let clientSecret = _stripeClientSecret;
    if (!clientSecret) {
        try {
            const res  = await fetch(SITE_URL_JS + '/pages/stripe-create-intent.php', {method:'POST',body:fd});
            const data = await res.json();
            if (!data.ok) { setStripeError(data.error || 'Failed to create payment.'); resetBtn(btn); return; }
            clientSecret = data.client_secret;
            _stripeClientSecret = clientSecret;
        } catch(e) { setStripeError('Network error. Try again.'); resetBtn(btn); return; }
    }

    // Confirm card payment
    const result = await _stripe.confirmCardPayment(clientSecret, {
        payment_method: { card: _cardElement }
    });
    if (result.error) {
        setStripeError(result.error.message);
        _stripeClientSecret = null;
        resetBtn(btn);
    } else if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
        _stripeConfirmed = true;
        document.getElementById('paymentIntentInput').value = result.paymentIntent.id;
        document.getElementById('paymentStatusField').value = 'paid';
        submitForm();
    }
}

function setStripeError(msg) {
    const el = document.getElementById('stripe-card-errors');
    if (el) el.textContent = msg;
}

function resetBtn(btn) {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-lock" style="margin-right:8px;"></i>Place Order · <?= formatPrice($total) ?>';
}

function submitForm() {
    const form = document.getElementById('checkoutForm');
    const btn  = document.getElementById('placeOrderBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Completing order...';
    if (form.requestSubmit) form.requestSubmit(); else form.submit();
}

// ── Init Stripe ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    selectPayment(_currentPayment);

    if (STRIPE_ENABLED && typeof Stripe !== 'undefined') {
        _stripe = Stripe(STRIPE_PUB_KEY);
        const elements = _stripe.elements();
        _cardElement = elements.create('card', {
            style: {
                base: { fontSize: '15px', color: '#1a1a1a', fontFamily: 'DM Sans, sans-serif', '::placeholder': {color:'#9ca3af'} },
                invalid: { color: '#dc2626' }
            }
        });
        _cardElement.mount('#stripe-card-element');
        _cardElement.addEventListener('change', e => {
            setStripeError(e.error ? e.error.message : '');
        });
    }

    // ── Init PayPal ──────────────────────────────────────────────────────────
    if (PAYPAL_ENABLED && typeof paypal !== 'undefined') {
        paypal.Buttons({
            createOrder: async () => {
                const fd = new FormData();
                fd.append('amount', ORDER_TOTAL);
                fd.append('ref', document.getElementById('orderRefInput').value);
                const res  = await fetch(SITE_URL_JS + '/pages/paypal-create-order.php', {method:'POST',body:fd});
                const data = await res.json();
                if (!data.ok) { alert(data.error || 'PayPal error'); throw new Error(data.error); }
                return data.order_id;
            },
            onApprove: async (data) => {
                const fd = new FormData();
                fd.append('paypal_order_id', data.orderID);
                fd.append('order_ref', document.getElementById('orderRefInput').value);
                const res    = await fetch(SITE_URL_JS + '/pages/paypal-capture.php', {method:'POST',body:fd});
                const result = await res.json();
                if (result.ok) {
                    _paypalConfirmed = true;
                    document.getElementById('paypalOrderInput').value = data.orderID;
                    document.getElementById('paymentStatusField').value = 'paid';
                    submitForm();
                } else {
                    alert('PayPal capture failed. Please try again.');
                }
            },
            onError: (err) => { console.error(err); alert('PayPal error. Please try again.'); }
        }).render('#paypal-button-container');
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
