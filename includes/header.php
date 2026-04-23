<?php
$themeMode    = getSetting('theme_mode', 'light');
$siteName     = getSetting('site_name', 'TeaStore');
$siteTagline  = getSetting('site_tagline', 'TeaStore – Premium Tea & Accessories');
$siteLogo     = getSetting('site_logo', '');
$annoBar      = getSetting('announcement_bar', '🚚 Free delivery on orders over $49 | Premium Tea Selection');
$annoEnabled  = getSetting('announcement_bar_enabled', '1');
$showFloatContact = getSetting('show_telegram_btn', getSetting('show_whatsapp_btn', '1'));
$floatContactUrl  = trim((string)getSetting('telegram_float_url', getSetting('telegram_url', '#')));
$fontName     = getSetting('theme_font', 'DM Sans');
$primaryColor = getSetting('theme_primary_color', '#2d6a4f');
$fontSizeBase = (int)(getSetting('font_size_base', '15') ?: 15);
$fontSizeH1   = (int)(getSetting('font_size_h1', '28') ?: 28);
$fontSizeH2   = (int)(getSetting('font_size_h2', '22') ?: 22);
$fontSizeA    = (int)(getSetting('font_size_a', '14') ?: 14);
$fontSizeNav  = (int)(getSetting('font_size_nav', '14') ?: 14);
$wishlistCount = getWishlistCount();

$fontMap = [
    'DM Sans'      => 'DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,700',
    'Inter'        => 'Inter:wght@300;400;500;600;700',
    'Poppins'      => 'Poppins:wght@300;400;500;600;700',
    'Nunito'       => 'Nunito:wght@300;400;500;600;700',
    'Outfit'       => 'Outfit:wght@300;400;500;600;700',
    'Plus Jakarta' => 'Plus+Jakarta+Sans:wght@300;400;500;600;700',
];
$googleFontStr = $fontMap[$fontName] ?? $fontMap['DM Sans'];

// ─── Load nav menu from DB ─────────────────────────────────────────────────
$navMenuItems = [];
try {
    // Ensure nav_menu_items table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS nav_menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        label VARCHAR(200) NOT NULL,
        url VARCHAR(500) DEFAULT '#',
        icon VARCHAR(50) DEFAULT '',
        parent_id INT DEFAULT 0,
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        open_new_tab TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $navStmt = $pdo->query("SELECT * FROM nav_menu_items WHERE is_active=1 ORDER BY sort_order, id");
    $allNavItems = $navStmt->fetchAll();
    // Build tree: parent_id=0 are top-level
    foreach ($allNavItems as $item) {
        if ((int)$item['parent_id'] === 0) {
            $item['children'] = [];
            foreach ($allNavItems as $child) {
                if ((int)$child['parent_id'] === (int)$item['id']) {
                    $item['children'][] = $child;
                }
            }
            $navMenuItems[] = $item;
        }
    }
} catch (Throwable $e) {}

// If no menu items configured yet, use smart defaults
$useDefaultNav = empty($navMenuItems);
?>
<?php
$assetCssVer = @filemtime(__DIR__ . '/../assets/css/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $themeMode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="site-url" content="<?= SITE_URL ?>">
    <meta name="cart-action-url" content="<?= SITE_URL ?>/pages/cart-action.php">
    <meta name="wishlist-action-url" content="<?= SITE_URL ?>/pages/wishlist-action.php">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : htmlspecialchars($siteName) . ' – ' . htmlspecialchars($siteTagline) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?= urlencode($googleFontStr) ?>&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=<?= $assetCssVer ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style><?= getThemeCssVars() ?>
    body { font-size: <?= $fontSizeBase ?>px; }
    h1 { font-size: <?= $fontSizeH1 ?>px; }
    h2 { font-size: <?= $fontSizeH2 ?>px; }
    a { font-size: <?= $fontSizeA ?>px; }
    .main-nav>ul>li>a { font-size: <?= $fontSizeNav ?>px; }
    p { font-size: <?= $fontSizeBase ?>px; }
    /* ── Fixed search bar ── */
    .search-wrap{display:flex;background:var(--bg,#f5f5f5);border:1.5px solid var(--border,#e2e8f0);border-radius:25px;overflow:hidden;transition:.2s;}
    .search-wrap:focus-within{border-color:var(--primary);background:#fff;box-shadow:0 0 0 3px rgba(45,106,79,.12);}
    .search-wrap .search-input{border:none;background:none;padding:8px 14px;font-size:13px;outline:none;width:190px;color:var(--text,#111);font-family:inherit;}
    .search-wrap .search-btn{background:none;border:none;color:var(--text-muted,#888);padding:8px 13px;cursor:pointer;transition:.2s;display:flex;align-items:center;justify-content:center;}
    .search-wrap .search-btn:hover{color:var(--primary);}
    @media(max-width:1100px){.search-wrap .search-input{width:140px;}}
    @media(max-width:768px){.search-wrap{display:none;}}
    /* ── Mobile search icon ── */
    .mobile-search-icon{display:none;width:40px;height:40px;border:1.5px solid var(--border,#e2e8f0);border-radius:12px;background:transparent;color:var(--text,#111);cursor:pointer;align-items:center;justify-content:center;font-size:15px;}
    @media(max-width:768px){.mobile-search-icon{display:flex;}}
    /* ── Cart count badge realtime ── */
    .badge-count{position:absolute;top:-6px;right:-6px;background:var(--primary);color:#fff;font-size:10px;font-weight:800;min-width:18px;height:18px;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px;line-height:1;pointer-events:none;}
    .cart-icon-btn{position:relative;}
    .icon-btn{position:relative;}
    /* ── nav menu from DB ── */
    .nav-custom-dropdown{position:absolute;top:calc(100% + 8px);left:0;background:#fff;border:1px solid #edf0f3;border-radius:14px;padding:6px;min-width:180px;box-shadow:0 8px 30px rgba(0,0,0,.12);z-index:400;opacity:0;visibility:hidden;transform:translateY(6px);transition:.2s;}
    .has-dropdown:hover .nav-custom-dropdown{opacity:1;visibility:visible;transform:translateY(0);}
    .nav-custom-dropdown a{display:block;padding:8px 13px;border-radius:9px;color:var(--text,#111);font-size:13px;transition:.15s;}
    .nav-custom-dropdown a:hover{background:var(--bg,#f5f5f5);color:var(--primary);}
    </style>
</head>
<body>

<div class="overlay-backdrop" id="backdrop" onclick="closeMobileMenu()"></div>

<?php if ($annoEnabled === '1' && $annoBar): ?>
<div class="announcement-bar">
    <div class="container" style="text-align:center;font-size:13px;">
        <?= htmlspecialchars($annoBar) ?>
    </div>
</div>
<?php endif; ?>

<header class="site-header" id="siteHeader">
    <div class="container header-inner">
        <!-- Logo -->
        <a href="<?= pretty_url('index.php') ?>" class="logo">
            <?php if ($siteLogo): ?>
            <img src="<?= SITE_URL ?>/assets/img/<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteName) ?>" style="height:38px;max-width:160px;object-fit:contain;">
            <?php else: ?>
            <span class="logo-icon">🍵</span>
            <span class="logo-text"><?= htmlspecialchars($siteName) ?></span>
            <?php endif; ?>
        </a>

        <!-- Desktop Nav -->
        <nav class="main-nav">
            <ul>
                <?php if ($useDefaultNav): ?>
                <!-- Default menu (shown when no custom menu items exist) -->
                <li><a href="<?= pretty_url('index.php') ?>"><i class="fas fa-home" style="margin-right:6px;"></i>Home</a></li>
                <li><a href="<?= pretty_url('pages/shop.php') ?>"><i class="fas fa-store" style="margin-right:6px;"></i>Shop</a></li>
                <li class="has-dropdown" style="position:relative;">
                    <a href="<?= pretty_url('pages/shop.php') ?>"><i class="fas fa-th-large" style="margin-right:6px;"></i>Categories <i class="fas fa-chevron-down" style="font-size:9px;margin-left:4px;"></i></a>
                    <ul class="nav-custom-dropdown">
                        <?php
                        try {
                            $cats = teastoreSafeQueryAll("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order, name LIMIT 12");
                            if (!empty($cats)) {
                                foreach ($cats as $cat): ?>
                                <li><a href="<?= pretty_url('pages/shop.php') ?>?category=<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
                                <?php endforeach;
                            } else { ?>
                                <li><a href="<?= pretty_url('pages/shop.php') ?>?tea=green">🍃 Green Tea</a></li>
                                <li><a href="<?= pretty_url('pages/shop.php') ?>?tea=black">🫖 Black Tea</a></li>
                                <li><a href="<?= pretty_url('pages/shop.php') ?>?tea=herbal">🌿 Herbal Tea</a></li>
                                <li><a href="<?= pretty_url('pages/shop.php') ?>?tea=oolong">🍂 Oolong Tea</a></li>
                                <li><a href="<?= pretty_url('pages/shop.php') ?>?cat=tea-accessories">🫙 Accessories</a></li>
                            <?php } } catch (\Exception $e) { ?>
                                <li><a href="<?= pretty_url('pages/shop.php') ?>">All Products</a></li>
                        <?php } ?>
                    </ul>
                </li>
                <li><a href="<?= pretty_url('pages/contact.php') ?>"><i class="fas fa-envelope" style="margin-right:6px;"></i>Contact Us</a></li>
                <?php else: ?>
                <!-- Custom menu from CMS -->
                <?php foreach ($navMenuItems as $item): 
                    $targetAttr = $item['open_new_tab'] ? ' target="_blank" rel="noopener"' : '';
                    $hasChildren = !empty($item['children']);
                ?>
                <li<?= $hasChildren ? ' class="has-dropdown" style="position:relative;"' : '' ?>>
                    <a href="<?= htmlspecialchars($item['url']) ?>"<?= $targetAttr ?>>
                        <?php if ($item['icon']): ?><i class="<?= htmlspecialchars($item['icon']) ?>" style="margin-right:6px;"></i><?php endif; ?>
                        <?= htmlspecialchars($item['label']) ?>
                        <?php if ($hasChildren): ?><i class="fas fa-chevron-down" style="font-size:9px;margin-left:4px;"></i><?php endif; ?>
                    </a>
                    <?php if ($hasChildren): ?>
                    <ul class="nav-custom-dropdown">
                        <?php foreach ($item['children'] as $child): 
                            $childTarget = $child['open_new_tab'] ? ' target="_blank" rel="noopener"' : '';
                        ?>
                        <li><a href="<?= htmlspecialchars($child['url']) ?>"<?= $childTarget ?>>
                            <?php if ($child['icon']): ?><i class="<?= htmlspecialchars($child['icon']) ?>" style="margin-right:6px;"></i><?php endif; ?>
                            <?= htmlspecialchars($child['label']) ?>
                        </a></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Header Actions -->
        <div class="header-actions">
            <!-- Desktop search -->
            <form action="<?= pretty_url('pages/shop.php') ?>" method="GET" class="search-wrap">
                <input type="text" name="q" placeholder="Search teas, accessories..." class="search-input" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                <button type="submit" class="search-btn" aria-label="Search"><i class="fas fa-search"></i></button>
            </form>
            <!-- Mobile search icon -->
            <button class="mobile-search-icon" onclick="toggleSearch()" aria-label="Search">
                <i class="fas fa-search"></i>
            </button>
            <?php if (isLoggedIn()): ?>
            <a href="<?= pretty_url('pages/wishlist.php') ?>" class="icon-btn" title="Wishlist">
                <i class="fas fa-heart"></i>
                <?php if ($wishlistCount > 0): ?><span class="badge-count"><?= $wishlistCount ?></span><?php endif; ?>
            </a>
            <a href="<?= pretty_url('pages/account.php') ?>" class="icon-btn" title="My Account"><i class="fas fa-user"></i></a>
            <?php else: ?>
            <a href="<?= pretty_url('pages/login.php') ?>" class="icon-btn" title="Login"><i class="fas fa-user"></i></a>
            <?php endif; ?>
            <a href="<?= pretty_url('pages/cart.php') ?>" class="icon-btn cart-icon-btn" title="Cart">
                <span class="cart-bag-wrap"><i class="fas fa-shopping-bag"></i></span>
                <?php $cc = getCartCount(); ?>
                <span class="badge-count cart-count" style="<?= $cc > 0 ? '' : 'display:none' ?>"><?= $cc ?></span>
            </a>
            <button class="mobile-menu-btn" onclick="openMobileMenu()" aria-label="Menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>
</header>

<!-- Mobile Nav -->
<nav class="mobile-nav" id="mobileNav">
    <div class="mobile-nav-header">
        <span style="font-size:18px;font-weight:700;">🍵 <?= htmlspecialchars($siteName) ?></span>
        <button class="close-nav" onclick="closeMobileMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div style="padding:12px 16px;">
        <form action="<?= pretty_url('pages/shop.php') ?>" method="GET" style="display:flex;background:var(--bg);border-radius:10px;overflow:hidden;border:1px solid var(--border);">
            <input type="text" name="q" placeholder="Search teas..." style="flex:1;padding:10px 14px;border:none;background:transparent;outline:none;font-size:14px;color:var(--text);">
            <button type="submit" style="padding:10px 14px;background:var(--primary);border:none;color:#fff;cursor:pointer;"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <ul>
        <?php if ($useDefaultNav): ?>
        <!-- Default mobile menu -->
        <li><a href="<?= pretty_url('index.php') ?>"><i class="fas fa-home"></i>Home</a></li>
        <li><a href="<?= pretty_url('pages/shop.php') ?>"><i class="fas fa-store"></i>Shop</a></li>
        <li><a href="<?= pretty_url('pages/contact.php') ?>"><i class="fas fa-envelope"></i>Contact Us</a></li>
        <?php
        try {
            $mobCats = teastoreSafeQueryAll("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order, name LIMIT 12");
            if (!empty($mobCats)) {
                foreach ($mobCats as $mobCat): ?>
                <li><a href="<?= pretty_url('pages/shop.php') ?>?category=<?= (int)$mobCat['id'] ?>"><i class="fas fa-tag"></i><?= htmlspecialchars($mobCat['name']) ?></a></li>
                <?php endforeach;
            } else { ?>
                <li><a href="<?= pretty_url('pages/shop.php') ?>?tea=green"><span style="margin-right:10px;">🍃</span>Green Tea</a></li>
                <li><a href="<?= pretty_url('pages/shop.php') ?>?tea=black"><span style="margin-right:10px;">🫖</span>Black Tea</a></li>
                <li><a href="<?= pretty_url('pages/shop.php') ?>?tea=herbal"><span style="margin-right:10px;">🌿</span>Herbal Tea</a></li>
                <li><a href="<?= pretty_url('pages/shop.php') ?>?tea=oolong"><span style="margin-right:10px;">🍂</span>Oolong Tea</a></li>
                <li><a href="<?= pretty_url('pages/shop.php') ?>?cat=tea-accessories"><span style="margin-right:10px;">🫙</span>Accessories</a></li>
        <?php } } catch (\Exception $e) {} ?>
        <?php else: ?>
        <!-- Custom mobile menu from CMS -->
        <?php foreach ($navMenuItems as $item): 
            $targetAttr = $item['open_new_tab'] ? ' target="_blank" rel="noopener"' : '';
        ?>
        <li>
            <a href="<?= htmlspecialchars($item['url']) ?>"<?= $targetAttr ?>>
                <?php if ($item['icon']): ?><i class="<?= htmlspecialchars($item['icon']) ?>"></i><?php else: ?><i class="fas fa-link"></i><?php endif; ?>
                <?= htmlspecialchars($item['label']) ?>
            </a>
        </li>
        <?php endforeach; ?>
        <?php endif; ?>
        <li><a href="<?= pretty_url('pages/cart.php') ?>"><i class="fas fa-shopping-bag"></i>Cart <span class="cart-count" style="background:var(--primary);color:#fff;padding:2px 7px;border-radius:12px;font-size:11px;margin-left:6px;"><?= getCartCount() ?></span></a></li>
        <?php if (isLoggedIn()): ?>
        <li><a href="<?= pretty_url('pages/account.php') ?>"><i class="fas fa-user"></i>My Account</a></li>
        <li><a href="<?= pretty_url('pages/wishlist.php') ?>"><i class="fas fa-heart"></i>Wishlist</a></li>
        <li><a href="<?= pretty_url('pages/logout.php') ?>"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
        <?php else: ?>
        <li><a href="<?= pretty_url('pages/login.php') ?>"><i class="fas fa-sign-in-alt"></i>Login</a></li>
        <li><a href="<?= pretty_url('pages/register.php') ?>"><i class="fas fa-user-plus"></i>Register</a></li>
        <?php endif; ?>
    </ul>
</nav>

<div class="page-wrapper">
