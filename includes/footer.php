<?php
$fbUrl  = getSetting('facebook_url', '#');
$igUrl  = getSetting('instagram_url', '#');
$tgUrl  = getSetting('telegram_url', '#');
$ttUrl  = getSetting('tiktok_url', '');
$ytUrl  = getSetting('youtube_url', '');
$siteName = getSetting('site_name', 'TeaStore');
$phone  = getSetting('site_phone', '+1 800 TEA SHOP');
$email  = getSetting('site_email', 'support@teastore.com');
$addr   = getSetting('site_address', 'Phnom Penh, Cambodia');
$footerDesc = getSetting('footer_description', 'TeaStore – Premium teas & accessories delivered to your door.');
$footerCopyright = getSetting('footer_copyright', '');
$footerBg = getSetting('footer_bg_color', '#111');
$footerTxt = getSetting('footer_text_color', '#ffffff');
$footerShowSocial = getSetting('footer_show_social', '1') === '1';
if (!$footerCopyright) $footerCopyright = '© '.date('Y').' '.htmlspecialchars($siteName).'. All rights reserved.';
?>
<?php $assetJsVer = @filemtime(__DIR__ . '/../assets/js/main.js') ?: time(); ?>
<footer class="site-footer" style="background:<?= htmlspecialchars($footerBg) ?>;color:<?= htmlspecialchars($footerTxt) ?>">
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="footer-logo">🍵 <?= htmlspecialchars($siteName) ?></div>
                <p style="color:<?= htmlspecialchars($footerTxt) ?>;opacity:.7"><?= htmlspecialchars($footerDesc) ?></p>
                <?php if ($footerShowSocial): ?>
                <div class="footer-socials">
                    <a href="<?= htmlspecialchars($fbUrl) ?>" target="_blank"><i class="fab fa-facebook-f"></i></a>
                    <a href="<?= htmlspecialchars($igUrl) ?>" target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="<?= htmlspecialchars($tgUrl) ?>" target="_blank"><i class="fab fa-telegram-plane"></i></a>
                    <?php if ($ttUrl): ?><a href="<?= htmlspecialchars($ttUrl) ?>" target="_blank"><i class="fab fa-tiktok"></i></a><?php endif; ?>
                    <?php if ($ytUrl): ?><a href="<?= htmlspecialchars($ytUrl) ?>" target="_blank"><i class="fab fa-youtube"></i></a><?php endif; ?>
                </div>
                <?php endif; ?>
                <div style="margin-top:16px;font-size:12px;opacity:.45;line-height:1.8;">
                    <?= htmlspecialchars($addr) ?><br>
                    📞 <?= htmlspecialchars($phone) ?><br>
                    ✉️ <?= htmlspecialchars($email) ?>
                </div>
            </div>
            <div class="footer-col">
                <h4>Shop Tea</h4>
                <ul>
                    <li><a href="<?= pretty_url('pages/shop.php') ?>?tea=green">🍃 Green Tea</a></li>
                    <li><a href="<?= pretty_url('pages/shop.php') ?>?tea=black">🫖 Black Tea</a></li>
                    <li><a href="<?= pretty_url('pages/shop.php') ?>?tea=herbal">🌿 Herbal Tea</a></li>
                    <li><a href="<?= pretty_url('pages/shop.php') ?>?new=1">✨ New Arrivals</a></li>
                    <li><a href="<?= pretty_url('pages/shop.php') ?>?sale=1">🔥 On Sale</a></li>
                    <li><a href="<?= pretty_url('pages/brands.php') ?>">All Brands</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Help</h4>
                <ul>
                    <li><a href="<?= pretty_url('pages/how-to-buy.php') ?>">How to Buy</a></li>
                    <li><a href="<?= pretty_url('pages/payment-delivery.php') ?>">Payment & Delivery</a></li>
                    <li><a href="<?= pretty_url('pages/faq.php') ?>">FAQ</a></li>
                    <li><a href="<?= pretty_url('pages/contact.php') ?>">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Account</h4>
                <ul>
                    <li><a href="<?= pretty_url('pages/account.php') ?>">My Account</a></li>
                    <li><a href="<?= pretty_url('pages/orders.php') ?>">My Orders</a></li>
                    <li><a href="<?= pretty_url('pages/wishlist.php') ?>">Wishlist</a></li>
                    <li><a href="<?= pretty_url('pages/terms.php') ?>">Terms & Conditions</a></li>
                    <li><a href="<?= pretty_url('pages/privacy.php') ?>">Privacy Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p><?= htmlspecialchars($footerCopyright) ?></p>
            <div style="display:flex;align-items:center;gap:12px;font-size:12px;color:rgba(255,255,255,0.35);">
                <span>💳 Stripe</span>
                <span>🅿️ PayPal</span>
                <span>🔒 SSL Secured</span>
            </div>
        </div>
    </div>
</footer>
<!-- ═══ PRODUCT QUICK-ADD MODAL ═══════════════════════════════════════════ -->
<div class="modal-backdrop" id="productModal" onclick="closeModalOutside(event)">
    <div class="product-modal" id="modalContent">

        <!-- Close button -->
        <button class="modal-close" onclick="closeModal()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>

        <!-- Hero image -->
        <div class="modal-hero-img" id="modalHeroImg"></div>

        <!-- Scrollable body -->
        <div class="modal-body">

            <!-- Product info -->
            <div class="modal-info">
                <div id="modalBadges"></div>
                <div class="modal-title" id="modalTitle"></div>
                <div id="modalMeta" style="margin-top:4px;margin-bottom:6px;"></div>
                <div style="display:flex;align-items:center;gap:10px;margin:10px 0 4px;">
                    <span class="modal-price-tag" id="modalPrice"></span>
                    <span class="modal-price-old" id="modalOldPrice"></span>
                </div>
                <div class="modal-desc" id="modalDesc"></div>

                <!-- Quantity discount tiers (populated by JS from API) -->
                <div id="modalDiscountTiers" style="display:none;margin-top:10px;"></div>
            </div>

            <!-- Option groups (populated by JS) -->
            <div id="modalOptions"></div>

        </div><!-- /.modal-body -->

        <!-- Footer: qty + add to cart -->
        <div class="modal-footer">
            <div class="qty-control">
                <button class="qty-btn" id="modalQtyMinus" onclick="modalQtyChange(-1)" disabled aria-label="Decrease quantity">−</button>
                <span class="qty-num" id="modalQty">1</span>
                <button class="qty-btn" onclick="modalQtyChange(1)" aria-label="Increase quantity">+</button>
            </div>
            <button class="add-to-cart-modal-btn" id="modalAddBtn" onclick="modalAddToCart()">
                <span id="modalBtnLabel">Add to cart</span>
                <span id="modalBtnTotal"></span>
            </button>
        </div>

    </div>
</div>
<!-- ═════════════════════════════════════════════════════════════════════════ -->

<script src="<?= SITE_URL ?>/assets/js/main.js?v=<?= $assetJsVer ?>"></script>
</div><!-- .page-wrapper -->
</body>
</html>
