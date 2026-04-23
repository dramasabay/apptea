<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = "TeaStore – Cambodia's first online tea & accessories";

// ─── Ensure tables exist ─────────────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS hero_banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255), subtitle VARCHAR(500),
        button_text VARCHAR(100), button_url VARCHAR(500),
        image VARCHAR(255), bg_color VARCHAR(20) DEFAULT '#1a1a1a',
        text_color VARCHAR(20) DEFAULT '#ffffff',
        sort_order INT DEFAULT 0, is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS home_sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_key VARCHAR(80) UNIQUE NOT NULL,
        section_label VARCHAR(100),
        sort_order INT DEFAULT 0, is_visible TINYINT(1) DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("INSERT IGNORE INTO home_sections (section_key,section_label,sort_order,is_visible) VALUES
        ('categories','Browse by Type',1,1),('new_arrivals','✨ New Arrivals',2,1),
        ('featured','⭐ Customer Favorites',3,1),('green_tea','🍃 Green Teas',4,1),
        ('black_tea','🫖 Black Teas',5,1),('sale','🏷️ On Sale',6,1),('brands','Top Tea Brands',7,1)");
    // Banner settings defaults
    foreach(['banner_autoplay'=>'1','banner_autoplay_speed'=>'4000','banner_height'=>'250','banner_width'=>'1260'] as $k=>$v)
        $pdo->prepare("INSERT IGNORE INTO site_settings (setting_key,setting_value,setting_group) VALUES (?,?,'homepage')")->execute([$k,$v]);
} catch (Throwable $e) {}

// ─── Load hero banners ───────────────────────────────────────────────────────
$heroBanners = teastoreSafeQueryAll("SELECT * FROM hero_banners WHERE is_active=1 ORDER BY sort_order,id");
$bannerH     = (int)(getSetting('banner_height','250') ?: 250);
$bannerHM    = (int)(getSetting('banner_height_mobile','200') ?: 200);
$bannerW     = (int)(getSetting('banner_width','1260') ?: 1260);
$bannerBR    = (int)(getSetting('banner_border_radius','0') ?: 0);
$bannerOvOp  = (int)(getSetting('banner_overlay_opacity','18') ?: 18);
$autoplay    = getSetting('banner_autoplay','1') === '1';
$autoplaySpd = (int)(getSetting('banner_autoplay_speed','4000') ?: 4000);

// ─── Legacy single hero fallback (used if no banners) ───────────────────────
$heroTitle   = getSetting('hero_title','Premium Teas<br><em>Delivered Fast</em>');
$heroSub     = getSetting('hero_subtitle',"TeaStore – Your trusted online tea store.");
$heroBadge   = getSetting('hero_badge','🍵 #1 Online Tea Store');
$heroCta1    = getSetting('hero_cta_text','Shop Now');
$heroBgColor = getSetting('hero_bg_color','#1a1a1a');
$heroBgType  = getSetting('hero_bg_type','color');
$heroBgImage = getSetting('hero_bg_image','');

// ─── Load home sections ──────────────────────────────────────────────────────
$rawSections = teastoreSafeQueryAll("SELECT * FROM home_sections WHERE is_visible=1 ORDER BY sort_order,id");
$visibleSections = array_column($rawSections, 'section_key');

// ─── Load product data only for visible sections ─────────────────────────────
$_homeLimit = max(1, min(20, (int)(getSetting('home_products_per_section', '8'))));
$newProducts      = in_array('new_arrivals',$visibleSections) ? teastoreSafeQueryAll("SELECT p.*,b.name AS brand_name FROM products p LEFT JOIN brands b ON p.brand_id=b.id WHERE p.is_new=1 ORDER BY p.created_at DESC LIMIT {$_homeLimit}") : [];
$greenTeaProducts      = in_array('green_tea',$visibleSections)         ? teastoreSafeQueryAll("SELECT p.*,b.name AS brand_name FROM products p LEFT JOIN brands b ON p.brand_id=b.id WHERE p.tea_type IN ('green','both') ORDER BY RAND() LIMIT {$_homeLimit}") : [];
$blackTeaProducts      = in_array('black_tea',$visibleSections)         ? teastoreSafeQueryAll("SELECT p.*,b.name AS brand_name FROM products p LEFT JOIN brands b ON p.brand_id=b.id WHERE p.tea_type IN ('black','both') ORDER BY RAND() LIMIT {$_homeLimit}") : [];
$featuredProducts = in_array('featured',$visibleSections)     ? teastoreSafeQueryAll("SELECT p.*,b.name AS brand_name FROM products p LEFT JOIN brands b ON p.brand_id=b.id WHERE p.featured=1 ORDER BY RAND() LIMIT {$_homeLimit}") : [];
$saleProducts     = in_array('sale',$visibleSections)         ? teastoreSafeQueryAll("SELECT p.*,b.name AS brand_name FROM products p LEFT JOIN brands b ON p.brand_id=b.id WHERE p.sale_price IS NOT NULL ORDER BY RAND() LIMIT {$_homeLimit}") : [];
$brands           = in_array('brands',$visibleSections)       ? teastoreSafeQueryAll("SELECT * FROM brands ORDER BY name") : [];
$allCategories    = in_array('categories',$visibleSections)   ? teastoreSafeQueryAll("SELECT c.*,COUNT(p.id) AS pcount FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id HAVING pcount>0 ORDER BY name LIMIT 16") : [];

require_once __DIR__ . '/includes/header.php';

function renderProductCard($p) {
    $siteUrl = SITE_URL;
    $price = $p['sale_price'] ?? $p['price'];
    $hasDiscount = $p['sale_price'] !== null;
    $discount = $hasDiscount ? round((1 - $p['sale_price']/$p['price'])*100) : 0;
    $teaEmoji = $p['tea_type'] === 'green' ? '🍃' : ($p['tea_type'] === 'black' ? '🫖' : ($p['tea_type'] === 'herbal' ? '🌿' : '🍵'));
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

<!-- ═══ HERO BANNER SLIDER ═══════════════════════════════════════════════════ -->
<?php if (!empty($heroBanners)): ?>
<div class="hero-slider-wrap" style="--banner-h:<?= $bannerH ?>px;--banner-h-mobile:<?= $bannerHM ?>px;--banner-maxw:<?= $bannerW ?>px;--banner-radius:<?= $bannerBR ?>px;--banner-overlay:rgba(0,0,0,<?= round($bannerOvOp/100,2) ?>)"><?php if($bannerBR > 0) echo '<style>.hero-slider-wrap{border-radius:'.$bannerBR.'px;overflow:hidden}</style>'; ?>
  <div class="hero-slider" id="heroSlider">
    <?php foreach ($heroBanners as $i => $b):
      $hasBgImg = !empty($b['image']);
      $bgStyle = $hasBgImg
          ? "background-image:url('".SITE_URL."/assets/img/banners/".htmlspecialchars($b['image'])."');background-size:cover;background-position:center;"
          : "background:".htmlspecialchars($b['bg_color'] ?: '#1a1a1a').";";
      $txtColor = htmlspecialchars($b['text_color'] ?: '#ffffff');
    ?>
    <div class="hero-slide<?= $i===0?' active':'' ?>" style="<?= $bgStyle ?>">
      <?php if ($hasBgImg): ?><div class="hero-slide-overlay"></div><?php endif; ?>
      <div class="hero-slide-inner">
        <?php if ($b['title']): ?><h2 class="hero-slide-title" style="color:<?= $txtColor ?>"><?= htmlspecialchars($b['title']) ?></h2><?php endif; ?>
        <?php if ($b['subtitle']): ?><p class="hero-slide-sub" style="color:<?= $txtColor ?>"><?= htmlspecialchars($b['subtitle']) ?></p><?php endif; ?>
        <?php if ($b['button_text'] && $b['button_url']): ?>
        <a href="<?= htmlspecialchars($b['button_url']) ?>" class="hero-slide-btn"><?= htmlspecialchars($b['button_text']) ?></a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if (count($heroBanners) > 1): ?>
  <!-- Arrows -->
  <button class="hs-arrow hs-prev" onclick="sliderNav(-1)" aria-label="Previous">&#8249;</button>
  <button class="hs-arrow hs-next" onclick="sliderNav(1)" aria-label="Next">&#8250;</button>
  <!-- Dots -->
  <div class="hs-dots">
    <?php foreach ($heroBanners as $i => $b): ?>
    <button class="hs-dot<?= $i===0?' active':'' ?>" onclick="sliderGoTo(<?= $i ?>)" aria-label="Slide <?= $i+1 ?>"></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php else:
// ─── LEGACY HERO (no banners configured) ────────────────────────────────────
function adjustColor(string $hex, int $amt): string {
    $hex = ltrim($hex,'#'); if(strlen($hex)!==6) return '#'.$hex;
    $r=max(0,min(255,hexdec(substr($hex,0,2))+$amt)); $g=max(0,min(255,hexdec(substr($hex,2,2))+$amt)); $b=max(0,min(255,hexdec(substr($hex,4,2))+$amt));
    return sprintf('#%02x%02x%02x',$r,$g,$b);
}
if ($heroBgType==='image'&&$heroBgImage) $heroBgStyle="background-image:url('".SITE_URL."/assets/img/".htmlspecialchars($heroBgImage)."');background-size:cover;background-position:center;background-color:".$heroBgColor.";";
else { $dark=adjustColor($heroBgColor,-35); $heroBgStyle="background:linear-gradient(135deg,{$heroBgColor} 0%,{$dark} 100%);"; }
?>
<section class="hero" style="<?= $heroBgStyle ?>">
    <?php if ($heroBgType==='image'&&$heroBgImage): ?><div style="position:absolute;inset:0;background:rgba(0,0,0,.42);z-index:1"></div><?php endif; ?>
    <div class="hero-glow"></div>
    <div class="container hero-shell" style="position:relative;z-index:2">
        <div class="hero-content">
            <?php if ($heroBadge): ?><span class="hero-badge"><?= htmlspecialchars($heroBadge) ?></span><?php endif; ?>
            <h1><?= $heroTitle ?></h1>
            <p><?= htmlspecialchars($heroSub) ?></p>
            <div class="hero-btns">
                <a href="<?= pretty_url('pages/shop.php') ?>" class="btn btn-primary"><i class="fas fa-paw"></i> <?= htmlspecialchars($heroCta1) ?></a>
                <a href="<?= pretty_url('pages/shop.php') ?>?sale=1" class="btn btn-outline"><i class="fas fa-tags"></i> View Deals</a>
            </div>
            <div class="hero-chip-row">
                <span class="hero-chip"><i class="fas fa-bolt"></i> Same-day Phnom Penh prep</span>
                <span class="hero-chip"><i class="fas fa-truck"></i> Fast local delivery</span>
                <span class="hero-chip"><i class="fas fa-lock"></i> Stripe &amp; PayPal</span>
            </div>
            <div class="hero-stats">
                <div class="hero-stat"><div class="hero-stat-num">500+</div><div class="hero-stat-label">Products</div></div>
                <div class="hero-stat"><div class="hero-stat-num">20+</div><div class="hero-stat-label">Brands</div></div>
                <div class="hero-stat"><div class="hero-stat-num">1–3 Days</div><div class="hero-stat-label">Delivery</div></div>
            </div>
        </div>
        <div class="hero-visual">
            <div class="hero-visual-card hero-card-main">
                <div class="hero-card-title">Popular categories</div>
                <div class="hero-mini-grid" style="margin-top:14px">
                    <div class="hero-mini-pill">🍃 Dog Food</div><div class="hero-mini-pill">🫖 Cat Food</div>
                    <div class="hero-mini-pill">🦴 Treats</div><div class="hero-mini-pill">🧸 Supplies</div>
                </div>
                <div class="hero-visual-divider"></div>
                <div class="hero-order-card">
                    <div><div class="hero-card-title">Modern checkout</div><div class="hero-card-sub">Clear delivery summary, product add-ons, Stripe &amp; PayPal checkout.</div></div>
                    <div class="hero-price-badge">Ready</div>
                </div>
            </div>
            <div class="hero-visual-card hero-card-floating">
                <div class="hero-floating-icon">🏦</div>
                <div><div class="hero-card-title">Checkout methods</div><div class="hero-card-sub">Pay securely with Stripe or PayPal.</div></div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section" style="padding:18px 0 12px;background:#fff">
    <div class="container">
        <div class="trust-row">
            <?php
            $trustCardsJson = getSetting('trust_cards', '');
            $trustCards = $trustCardsJson ? json_decode($trustCardsJson, true) : [];
            if (empty($trustCards)) {
                $trustCards = [
                    ['icon'=>'🚚','title'=>'Free Delivery','desc'=>'Free on orders over $49 in Phnom Penh'],
                    ['icon'=>'⭐','title'=>'Premium Quality','desc'=>'Vet-approved and tested products only'],
                    ['icon'=>'🔒','title'=>'Secure Payment','desc'=>'Stripe & PayPal · SSL encrypted'],
                ];
            }
            foreach ($trustCards as $tc): ?>
            <div class="trust-card"><strong><?= htmlspecialchars($tc['icon']) ?> <?= htmlspecialchars($tc['title']) ?></strong><span><?= htmlspecialchars($tc['desc']) ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php
// ─── Dynamic sections rendering ─────────────────────────────────────────────
foreach ($rawSections as $sec):
  $key = $sec['section_key'];
?>

<?php if ($key === 'categories' && !empty($allCategories)): ?>
<section class="section">
    <div class="container">
        <div class="section-top">
            <h2>Browse by Category</h2>
            <a href="<?= SITE_URL ?>/shop" class="view-all">See all <i class="fas fa-arrow-right" style="font-size:11px"></i></a>
        </div>
        <div class="category-grid-home">
            <?php foreach ($allCategories as $cat): ?>
            <a href="<?= pretty_url('pages/shop.php') ?>?cat=<?= urlencode($cat['slug'] ?? '') ?>" class="cat-card-home" onclick="event.stopPropagation()">
                <div class="cat-home-icon">
                    <?php
                    $slug = strtolower($cat['slug'] ?? $cat['name']);
                    $icons = ['dog-food'=>'🥩','cat-food'=>'🐟','treats'=>'🦴','litter'=>'🪣','healthcare'=>'💊','supplies'=>'🧸','accessories'=>'🎀','toy'=>'🎾'];
                    $icon = '🍵';
                    foreach ($icons as $k=>$v) { if (strpos($slug,$k)!==false) { $icon=$v; break; } }
                    echo $icon;
                    ?>
                </div>
                <div class="cat-home-name"><?= htmlspecialchars($cat['name']) ?></div>
                <div class="cat-home-count"><?= (int)$cat['pcount'] ?> items</div>
                <button class="cat-home-view">Shop →</button>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php elseif ($key === 'new_arrivals' && !empty($newProducts)): ?>
<section class="section section-alt">
    <div class="container">
        <div class="section-top"><h2>✨ New Arrivals</h2><a href="<?= SITE_URL ?>/shop?new=1" class="view-all">View all</a></div>
        <div class="product-grid home-grid"><?php foreach ($newProducts as $p) echo renderProductCard($p); ?></div>
    </div>
</section>

<?php elseif ($key === 'featured' && !empty($featuredProducts)): ?>
<section class="section">
    <div class="container">
        <div class="section-top"><h2>⭐ Customer Favorites</h2><a href="<?= SITE_URL ?>/shop?featured=1" class="view-all">View all</a></div>
        <div class="product-grid home-grid"><?php foreach ($featuredProducts as $p) echo renderProductCard($p); ?></div>
    </div>
</section>

<?php elseif (($key === 'dogs' || $key === 'green_tea') && !empty($greenTeaProducts)): ?>
<section class="section section-alt">
    <div class="container">
        <div class="section-top"><h2>🍃 Green Teas</h2><a href="<?= SITE_URL ?>/shop?tea=green" class="view-all">View all</a></div>
        <div class="product-grid home-grid"><?php foreach ($greenTeaProducts as $p) echo renderProductCard($p); ?></div>
    </div>
</section>

<?php elseif (($key === 'cats' || $key === 'black_tea') && !empty($blackTeaProducts)): ?>
<section class="section">
    <div class="container">
        <div class="section-top"><h2>🫖 Black Teas</h2><a href="<?= SITE_URL ?>/shop?tea=black" class="view-all">View all</a></div>
        <div class="product-grid home-grid"><?php foreach ($blackTeaProducts as $p) echo renderProductCard($p); ?></div>
    </div>
</section>

<?php elseif ($key === 'sale' && !empty($saleProducts)): ?>
<section class="section section-alt">
    <div class="container">
        <div class="section-top"><h2>🏷️ On Sale</h2><a href="<?= SITE_URL ?>/shop?sale=1" class="view-all">View all</a></div>
        <div class="product-grid home-grid"><?php foreach ($saleProducts as $p) echo renderProductCard($p); ?></div>
    </div>
</section>

<?php elseif ($key === 'brands' && !empty($brands)): ?>
<section class="section">
    <div class="container">
        <div class="section-top"><h2>Top Brands We Carry</h2><a href="<?= SITE_URL ?>/brands" class="view-all">All brands</a></div>
        <div class="brands-grid-home">
            <?php foreach ($brands as $b): ?>
            <a href="<?= pretty_url('pages/shop.php') ?>?brand=<?= $b['id'] ?>" class="brand-card-home">
                <?= htmlspecialchars($b['name']) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php endif; endforeach; ?>

<section class="section section-alt">
    <div class="container">
        <div class="section-top"><h2>Why Shop at TeaStore?</h2></div>
        <div class="why-grid">
            <?php foreach([['🚚','Fast Delivery','1-3 day delivery across Cambodia'],['✅','100% Authentic','Direct from authorized distributors'],['💰','Best Prices','Competitive pricing + regular deals'],['📞','Expert Support','Pet care advice from our team']] as [$i,$t,$d]): ?>
            <div class="why-card"><div class="why-icon"><?= $i ?></div><h3><?= $t ?></h3><p><?= $d ?></p></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Slider JS -->
<?php if (!empty($heroBanners) && count($heroBanners) > 1): ?>
<script>
(function() {
    let current = 0;
    const slides = document.querySelectorAll('.hero-slide');
    const dots   = document.querySelectorAll('.hs-dot');
    const total  = slides.length;
    let timer;

    function goTo(n) {
        slides[current].classList.remove('active');
        dots[current]?.classList.remove('active');
        current = ((n % total) + total) % total;
        slides[current].classList.add('active');
        dots[current]?.classList.add('active');
    }

    window.sliderGoTo = goTo;
    window.sliderNav  = dir => { clearInterval(timer); goTo(current + dir); startAuto(); };

    function startAuto() {
        <?php if ($autoplay): ?>
        clearInterval(timer);
        timer = setInterval(() => goTo(current + 1), <?= $autoplaySpd ?>);
        <?php endif; ?>
    }

    // Swipe support
    const slider = document.getElementById('heroSlider');
    let sx = 0;
    slider?.addEventListener('touchstart', e => { sx = e.touches[0].clientX; }, {passive:true});
    slider?.addEventListener('touchend',   e => {
        const dx = e.changedTouches[0].clientX - sx;
        if (Math.abs(dx) > 40) { clearInterval(timer); goTo(current + (dx < 0 ? 1 : -1)); startAuto(); }
    });

    startAuto();
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
