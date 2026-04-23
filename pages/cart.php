<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = "Cart – TeaStore";

$sid = session_id();
$uid = $_SESSION['user_id'] ?? null;

function teastoreCartItemUnitPrice(array $item): float {
    $optData = json_decode($item['options_json'] ?? '{}', true) ?: [];
    
    // If unit_price is already stored in options_json (from quantity discount calculation), use it
    if (isset($optData['unit_price'])) {
        return (float)$optData['unit_price'];
    }
    
    $base = (float)($item['base_price'] ?? $item['unit_price'] ?? 0);
    $extra = 0.0;
    if (isset($optData['extra'])) {
        $extra = (float)$optData['extra'];
    } elseif (!empty($optData['selections']) && is_array($optData['selections'])) {
        foreach ($optData['selections'] as $sel) {
            $extra += (float)($sel['price_add'] ?? 0);
        }
    }
    return $base + $extra;
}

function teastoreCartOptionsLabel(array $item): string {
    $optData = json_decode($item['options_json'] ?? '{}', true) ?: [];
    return (string)($optData['text'] ?? $optData['label'] ?? $item['variant_info'] ?? '');
}

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_id'])) {
        $stmt = $uid
            ? $pdo->prepare("DELETE FROM cart WHERE id=? AND user_id=?")
            : $pdo->prepare("DELETE FROM cart WHERE id=? AND session_id=?");
        $stmt->execute([(int)$_POST['remove_id'], $uid ?? $sid]);
        flash('success', 'Item removed from cart');
        redirect(pretty_url('pages/cart.php'));
    }
    if (isset($_POST['update_cart']) && !empty($_POST['qty']) && is_array($_POST['qty'])) {
        foreach ($_POST['qty'] as $cartId => $qty) {
            $qty = max(1, (int)$qty);
            $stmt = $uid
                ? $pdo->prepare("UPDATE cart SET qty=? WHERE id=? AND user_id=?")
                : $pdo->prepare("UPDATE cart SET qty=? WHERE id=? AND session_id=?");
            $stmt->execute([$qty, (int)$cartId, $uid ?? $sid]);
        }
        flash('success', 'Cart updated');
        redirect(pretty_url('pages/cart.php'));
    }
}

$sql = "SELECT c.*, p.name, p.slug, p.image, p.tea_type, COALESCE(v.price, p.sale_price, p.price) AS base_price
        FROM cart c
        JOIN products p ON c.product_id = p.id
        LEFT JOIN product_variants v ON c.variant_id = v.id
        WHERE ";
$stmt = $uid
    ? $pdo->prepare($sql . "c.user_id = ? ORDER BY c.id DESC")
    : $pdo->prepare($sql . "c.session_id = ? ORDER BY c.id DESC");
$stmt->execute([$uid ?? $sid]);
$items = $stmt->fetchAll();

foreach ($items as &$item) {
    $item['unit_price'] = teastoreCartItemUnitPrice($item);
    $item['options_label'] = teastoreCartOptionsLabel($item);
    $item['line_total'] = $item['unit_price'] * (int)$item['qty'];
}
unset($item);

$subtotal = array_sum(array_map(fn($i) => (float)$i['line_total'], $items));
$freeThreshold = (float)getSetting('free_delivery_threshold', '49');
$deliveryFee = (float)getSetting('delivery_fee', '3.50');
$shipping = $subtotal >= $freeThreshold ? 0 : $deliveryFee;
$total = $subtotal + $shipping;

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.cart-shell{padding:34px 0 64px}
.cart-page-title{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:26px;flex-wrap:wrap}
.cart-page-title h1{font-size:44px;line-height:1.02;letter-spacing:-1.6px;font-weight:900}
.cart-page-title p{color:#6b7280;font-size:14px;margin-top:8px}
.cart-pill{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;background:#fff5f4;border:1px solid #ffd7d2;color:var(--primary);font-size:12px;font-weight:800}
.cart-layout-modern{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:24px;align-items:start}
.cart-card-modern,.summary-card-modern{background:#fff;border:1px solid #eaecef;border-radius:24px;box-shadow:0 18px 40px rgba(15,23,42,.05)}
.cart-card-modern{padding:14px}
.cart-item-row{display:grid;grid-template-columns:92px minmax(0,1fr) 120px 116px 124px 56px;gap:14px;align-items:center;padding:18px 12px;border-bottom:1px solid #f1f5f9}
.cart-item-row:last-child{border-bottom:none}
.cart-col-labels{display:grid;grid-template-columns:92px minmax(0,1fr) 120px 116px 124px 56px;gap:14px;padding:8px 12px 14px;color:#94a3b8;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.12em}
.cart-thumb{width:92px;height:92px;border-radius:20px;background:#f8fafc;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;overflow:hidden;font-size:36px}
.cart-thumb img{width:100%;height:100%;object-fit:cover}
.cart-item-name{font-size:18px;font-weight:800;line-height:1.32;color:#111827}
.cart-item-name a:hover{color:var(--primary)}
.cart-item-meta{font-size:13px;color:#64748b;margin-top:6px;display:flex;flex-direction:column;gap:4px}
.cart-addon{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#f8fafc;border:1px solid #e5e7eb;color:#475569;font-size:12px;font-weight:700;width:max-content;max-width:100%}
.cart-price{font-size:16px;font-weight:800;color:#111827}
.cart-muted{font-size:12px;color:#94a3b8;margin-top:4px}
.cart-qty-box{display:flex;align-items:center;justify-content:center;gap:8px}
.qty-stepper{width:34px;height:34px;border:none;border-radius:12px;background:#f8fafc;border:1px solid #e5e7eb;font-size:18px;font-weight:800;cursor:pointer;color:#0f172a}
.qty-stepper:hover{background:#fff3f1;border-color:#ffd3cc;color:var(--primary)}
.cart-qty-input{width:66px;border:1.5px solid #e5e7eb;border-radius:14px;padding:11px 8px;font-size:15px;font-weight:700;text-align:center;outline:none;background:#fff}
.cart-qty-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(235,23,0,.08)}
.cart-remove-modern{width:42px;height:42px;border-radius:14px;border:1px solid #f1f5f9;background:#fff;display:flex;align-items:center;justify-content:center;color:#94a3b8;cursor:pointer;transition:.2s}
.cart-remove-modern:hover{background:#fff1f2;color:#dc2626;border-color:#fecdd3}
.cart-actions-modern{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:18px 10px 6px;flex-wrap:wrap}
.summary-card-modern{padding:24px;position:sticky;top:calc(var(--header-h) + 18px)}
.summary-card-modern h3{font-size:22px;font-weight:900;letter-spacing:-.6px;margin-bottom:18px}
.summary-line-modern{display:flex;justify-content:space-between;gap:12px;padding:8px 0;font-size:14px}
.summary-total-modern{display:flex;justify-content:space-between;align-items:center;padding-top:18px;margin-top:18px;border-top:1px solid #e5e7eb;font-size:24px;font-weight:900;letter-spacing:-.8px}
.summary-note{margin:16px 0 0;padding:14px 16px;border-radius:16px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:13px;font-weight:700}
.summary-cta{display:flex;flex-direction:column;gap:12px;margin-top:20px}
.summary-accept{margin-top:16px;padding:16px;border-radius:18px;background:#f8fafc;border:1px solid #e5e7eb;text-align:center;color:#64748b;font-size:13px}
.summary-accept strong{display:block;color:#0f172a;font-size:14px;margin-bottom:8px}
.mobile-cart-list{display:none}
@media(max-width:1024px){.cart-layout-modern{grid-template-columns:1fr}}
@media(max-width:760px){
  .cart-page-title h1{font-size:34px}
  .cart-col-labels,.cart-item-row{display:none}
  .mobile-cart-list{display:flex;flex-direction:column;gap:14px}
  .mobile-cart-item{display:grid;grid-template-columns:84px minmax(0,1fr);gap:14px;padding:14px;border:1px solid #f1f5f9;border-radius:20px;background:#fff}
  .mobile-cart-meta{display:flex;flex-direction:column;gap:10px}
  .mobile-cart-prices{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
  .mobile-cart-prices .cart-qty-box{justify-content:flex-start}
  .cart-actions-modern{padding:16px 4px 4px;flex-direction:column;align-items:stretch}
  .cart-actions-modern .btn{width:100%;justify-content:center}
  .summary-card-modern{position:static}
}
</style>

<div class="container cart-shell">
    <div class="breadcrumb">
        <a href="<?= pretty_url('index.php') ?>">Home</a><span>›</span><span>Cart</span>
    </div>

    <div class="cart-page-title">
        <div>
            <h1>Checkout Cart</h1>
            <p><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?> with variant and add-on pricing included.</p>
        </div>
        <div class="cart-pill"><i class="fas fa-shield-heart"></i> Add-ons and variant prices are included in the total</div>
    </div>

    <?php if (empty($items)): ?>
        <div class="empty-state cart-card-modern" style="padding:70px 20px;">
            <div class="icon">🛒</div>
            <h3>Your cart is empty</h3>
            <p>Add some products to get started!</p>
            <a href="<?= pretty_url('pages/shop.php') ?>" class="btn btn-primary" style="margin-top:20px;">Continue Shopping</a>
        </div>
    <?php else: ?>
        <div class="cart-layout-modern">
            <div class="cart-card-modern">
                <form method="POST" action="<?= pretty_url('pages/cart.php') ?>" id="cartUpdateForm">
                    <div class="cart-col-labels">
                        <div>Item</div>
                        <div>Product</div>
                        <div>Unit price</div>
                        <div>Qty</div>
                        <div>Total</div>
                        <div></div>
                    </div>

                    <?php foreach ($items as $item):
                        $teaEmoji = $item['tea_type'] === 'green' ? '🍃' : ($item['tea_type'] === 'black' ? '🫖' : '🍵');
                    ?>
                    <div class="cart-item-row">
                        <a class="cart-thumb" href="<?= pretty_url('pages/product.php') ?>?slug=<?= urlencode($item['slug']) ?>">
                            <?php if ($item['image']): ?>
                                <img src="<?= SITE_URL ?>/assets/img/products/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                            <?php else: ?>
                                <?= $teaEmoji ?>
                            <?php endif; ?>
                        </a>
                        <div>
                            <div class="cart-item-name"><a href="<?= pretty_url('pages/product.php') ?>?slug=<?= urlencode($item['slug']) ?>"><?= htmlspecialchars($item['name']) ?></a></div>
                            <div class="cart-item-meta">
                                <?php if ($item['options_label']): ?>
                                    <span class="cart-addon"><i class="fas fa-sliders"></i> <?= htmlspecialchars($item['options_label']) ?></span>
                                <?php endif; ?>
                                <?php 
                                $optData = json_decode($item['options_json'] ?? '{}', true) ?: [];
                                $discountPct = (float)($optData['discount_pct'] ?? 0);
                                ?>
                                <?php if ($discountPct > 0): ?>
                                    <span class="cart-discount-badge" style="background:#dcfce7;color:#166534;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;display:inline-flex;align-items:center;gap:4px;margin-right:6px;"><i class="fas fa-tag"></i> <?= number_format($discountPct, 0) ?>% OFF</span>
                                <?php endif; ?>
                                <?php if (($item['unit_price'] - (float)$item['base_price']) > 0 && $discountPct <= 0): ?>
                                    <span>Includes add-ons: <strong><?= formatPrice($item['unit_price'] - (float)$item['base_price']) ?></strong></span>
                                <?php elseif (($item['unit_price'] - (float)$item['base_price']) > 0): ?>
                                    <span>Includes add-ons: <strong><?= formatPrice($item['unit_price'] - (float)$item['base_price']) ?></strong></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <div class="cart-price"><?= formatPrice($item['unit_price']) ?></div>
                            <?php if ($discountPct > 0): ?>
                                <div class="cart-muted" style="text-decoration:line-through;color:#94a3b8;"><?= formatPrice((float)$item['base_price'] + (float)$optData['extra']) ?> <span style="font-size:10px;">(before discount)</span></div>
                            <?php elseif (($item['unit_price'] - (float)$item['base_price']) > 0): ?>
                                <div class="cart-muted">Base <?= formatPrice((float)$item['base_price']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="cart-qty-box">
                            <button type="button" class="qty-stepper" onclick="teastoreQtyStep(this,-1)">−</button>
                            <input class="cart-qty-input" type="number" name="qty[<?= $item['id'] ?>]" value="<?= (int)$item['qty'] ?>" min="1" max="99" data-cart-id="<?= (int)$item['id'] ?>" data-unit-price="<?= htmlspecialchars(number_format((float)$item['unit_price'],2,'.','')) ?>">
                            <button type="button" class="qty-stepper" onclick="teastoreQtyStep(this,1)">+</button>
                        </div>
                        <div class="cart-price cart-line-total" data-line-total data-line-total-for="<?= (int)$item['id'] ?>"><?= formatPrice($item['line_total']) ?></div>
                        <div>
                            <button type="submit" name="remove_id" value="<?= $item['id'] ?>" class="cart-remove-modern" title="Remove item">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="mobile-cart-list">
                        <?php foreach ($items as $item):
                            $teaEmoji = $item['tea_type'] === 'green' ? '🍃' : ($item['tea_type'] === 'black' ? '🫖' : '🍵');
                        ?>
                        <div class="mobile-cart-item">
                            <a class="cart-thumb" href="<?= pretty_url('pages/product.php') ?>?slug=<?= urlencode($item['slug']) ?>">
                                <?php if ($item['image']): ?>
                                    <img src="<?= SITE_URL ?>/assets/img/products/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                <?php else: ?>
                                    <?= $teaEmoji ?>
                                <?php endif; ?>
                            </a>
                            <div class="mobile-cart-meta">
                                <div class="cart-item-name" style="font-size:17px"><a href="<?= pretty_url('pages/product.php') ?>?slug=<?= urlencode($item['slug']) ?>"><?= htmlspecialchars($item['name']) ?></a></div>
                                <?php if ($item['options_label']): ?>
                                    <span class="cart-addon"><i class="fas fa-sliders"></i> <?= htmlspecialchars($item['options_label']) ?></span>
                                <?php endif; ?>
                                <div class="mobile-cart-prices">
                                    <div>
                                        <div class="cart-price cart-line-total" data-line-total data-line-total-for="<?= (int)$item['id'] ?>"><?= formatPrice($item['line_total']) ?></div>
                                        <div class="cart-muted">Unit <?= formatPrice($item['unit_price']) ?></div>
                                    </div>
                                    <div class="cart-qty-box">
                                        <button type="button" class="qty-stepper" onclick="teastoreQtyStep(this,-1)">−</button>
                                        <input class="cart-qty-input" type="number" name="qty[<?= $item['id'] ?>]" value="<?= (int)$item['qty'] ?>" min="1" max="99" data-cart-id="<?= (int)$item['id'] ?>" data-unit-price="<?= htmlspecialchars(number_format((float)$item['unit_price'],2,'.','')) ?>">
                                        <button type="button" class="qty-stepper" onclick="teastoreQtyStep(this,1)">+</button>
                                    </div>
                                </div>
                                <button type="submit" name="remove_id" value="<?= $item['id'] ?>" class="cart-remove-modern" title="Remove item">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="cart-actions-modern">
                        <a href="<?= pretty_url('pages/shop.php') ?>" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Continue Shopping</a>
                        <button type="submit" name="update_cart" value="1" class="btn btn-dark"><i class="fas fa-rotate"></i> Update Cart</button>
                    </div>
                </form>
            </div>

            <div class="summary-card-modern">
                <h3>Checkout Summary</h3>
                <div class="summary-line-modern"><span>Subtotal</span><strong id="cartSubtotalText"><?= formatPrice($subtotal) ?></strong></div>
                <div class="summary-line-modern"><span>Shipping</span><strong><?= $shipping === 0 ? 'Free' : formatPrice($shipping) ?></strong></div>
                <div class="summary-total-modern"><span>Total</span><span style="color:var(--primary)" id="cartTotalText"><?= formatPrice($total) ?></span></div>
                <?php if ($shipping > 0): ?>
                    <div class="summary-note">Add <?= formatPrice(max(0, $freeThreshold - $subtotal)) ?> more to unlock free delivery.</div>
                <?php else: ?>
                    <div class="summary-note" style="background:#f0fdf4;border-color:#bbf7d0;color:#15803d">Free delivery unlocked for this order.</div>
                <?php endif; ?>
                <div class="summary-cta">
                    <a href="<?= pretty_url('pages/checkout.php') ?>" class="btn btn-primary btn-full btn-lg"><i class="fas fa-lock"></i> Proceed to Checkout</a>
                    <div style="text-align:center;font-size:12px;color:#64748b">Secure checkout · add-ons included in final total</div>
                </div>
                <div class="summary-accept">
                    <strong>We Accept</strong>
                    💳 🏦 💵<br>
                    💳 Stripe • 🅿️ PayPal • SSL Secured
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>


<script>
function teastoreSyncQtyInputs(changedInput){
  if(!changedInput) return;
  const cartId = changedInput.dataset.cartId || '';
  if(!cartId) return;
  document.querySelectorAll('.cart-qty-input[data-cart-id="' + cartId + '"]').forEach((input) => {
    if (input !== changedInput) input.value = changedInput.value;
  });
}

function teastoreQtyStep(btn, delta){
  const wrap = btn.closest('.cart-qty-box');
  const input = wrap ? wrap.querySelector('.cart-qty-input') : null;
  if(!input) return;
  const current = parseInt(input.value || '1', 10);
  input.value = Math.max(1, Math.min(99, current + delta));
  teastoreSyncQtyInputs(input);
  teastoreRefreshCartTotals();
}

function teastoreRefreshCartTotals(){
  const shipping = <?= json_encode((float)$shipping) ?>;
  let subtotal = 0;
  const seen = new Set();
  document.querySelectorAll('.cart-qty-input').forEach((input) => {
    const cartId = input.dataset.cartId || '';
    if (cartId && seen.has(cartId)) return;
    if (cartId) seen.add(cartId);
    const unit = parseFloat(input.dataset.unitPrice || '0');
    const qty = Math.max(1, parseInt(input.value || '1', 10));
    input.value = qty;
    teastoreSyncQtyInputs(input);
    subtotal += unit * qty;
    document.querySelectorAll('[data-line-total-for="' + cartId + '"]').forEach((target) => {
      target.textContent = <?= json_encode(getSetting('currency_symbol', '$')) ?> + (unit * qty).toFixed(2);
    });
  });
  const total = subtotal + shipping;
  const subtotalText = document.getElementById('cartSubtotalText');
  const totalText = document.getElementById('cartTotalText');
  if (subtotalText) subtotalText.textContent = <?= json_encode(getSetting('currency_symbol', '$')) ?> + subtotal.toFixed(2);
  if (totalText) totalText.textContent = <?= json_encode(getSetting('currency_symbol', '$')) ?> + total.toFixed(2);
}

document.querySelectorAll('.cart-qty-input').forEach((input)=>{
  input.addEventListener('change', () => { teastoreSyncQtyInputs(input); teastoreRefreshCartTotals(); });
  input.addEventListener('input', () => { teastoreSyncQtyInputs(input); teastoreRefreshCartTotals(); });
});
teastoreRefreshCartTotals();
function teastorePrepareCartSubmit(){
  const form = document.getElementById('cartUpdateForm');
  if(!form) return;
  form.querySelectorAll('.cart-qty-shadow').forEach(el => el.remove());
  const seen = new Set();
  form.querySelectorAll('.cart-qty-input').forEach((input)=>{
    const cartId = input.dataset.cartId || '';
    if (!cartId) return;
    if (seen.has(cartId)) {
      input.disabled = true;
      return;
    }
    seen.add(cartId);
    input.disabled = true;
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = `qty[${cartId}]`;
    hidden.value = Math.max(1, parseInt(input.value || '1', 10));
    hidden.className = 'cart-qty-shadow';
    form.appendChild(hidden);
  });
}
const _cartForm = document.getElementById('cartUpdateForm');
if(_cartForm){ _cartForm.addEventListener('submit', teastorePrepareCartSubmit); }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
