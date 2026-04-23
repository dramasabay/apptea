<?php
require_once __DIR__ . '/../includes/config.php';
if (!canAccessAdmin()) { flash('error','Admin access required'); redirect(pretty_url('pages/login.php')); }
// Settings and Users pages are admin-only
$adminOnlyPages = ['settings.php'];
if (in_array(basename($_SERVER['PHP_SELF']), $adminOnlyPages) && !isAdmin()) {
    flash('error','Admin access required for this page');
    redirect(pretty_url('admin/index.php'));
}
$currentPage = basename($_SERVER['PHP_SELF']);
$siteName    = getSetting('site_name','TeaStore');
$primaryColor = getSetting('theme_primary_color','#eb1700');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Admin – '.$siteName) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
    --p:<?= $primaryColor ?>;
    --p-light:<?= $primaryColor ?>22;
    --sidebar:#0f1117;
    --sidebar-hover:#1a1d27;
    --sidebar-active:#1f2333;
    --bg:#f0f2f7;
    --card:#ffffff;
    --border:#e8eaf0;
    --text:#1a1d2e;
    --text-2:#6b7280;
    --text-3:#9ca3af;
    --radius:14px;
    --radius-sm:10px;
    --shadow:0 2px 8px rgba(0,0,0,.06);
    --shadow-md:0 4px 20px rgba(0,0,0,.09);
    --shadow-lg:0 8px 32px rgba(0,0,0,.12);
}
body{font-family:'Inter',-apple-system,sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;font-size:14px;-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}

/* ---- SIDEBAR ---- */
.sidebar{width:240px;background:var(--sidebar);min-height:100vh;display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:200;transition:transform .3s}
.sidebar-brand{padding:22px 20px 18px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.06)}
.sidebar-brand-icon{width:34px;height:34px;background:var(--p);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.sidebar-brand-text{font-size:15px;font-weight:700;color:#fff;letter-spacing:-.3px}
.sidebar-brand-sub{font-size:10px;color:rgba(255,255,255,.35);font-weight:400;letter-spacing:.5px;text-transform:uppercase}
.sidebar-nav{padding:14px 10px;flex:1;overflow-y:auto}
.nav-section{font-size:9px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,.25);padding:14px 10px 6px}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius-sm);color:rgba(255,255,255,.55);font-size:13px;font-weight:500;transition:all .15s;margin-bottom:2px;cursor:pointer}
.nav-item:hover{background:var(--sidebar-hover);color:rgba(255,255,255,.85)}
.nav-item.active{background:var(--sidebar-active);color:#fff;font-weight:600}
.nav-item.active .nav-icon{color:var(--p)}
.nav-icon{width:18px;text-align:center;font-size:14px;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--p);color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:10px}
.sidebar-footer{padding:14px 10px;border-top:1px solid rgba(255,255,255,.06)}

/* ---- MAIN CONTENT ---- */
.main{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--card);border-bottom:1px solid var(--border);padding:0 28px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:var(--shadow)}
.topbar-left{display:flex;align-items:center;gap:12px}
.topbar-title{font-size:16px;font-weight:700;color:var(--text)}
.topbar-breadcrumb{font-size:12px;color:var(--text-3);display:flex;align-items:center;gap:4px}
.topbar-right{display:flex;align-items:center;gap:12px}
.topbar-btn{width:36px;height:36px;border-radius:var(--radius-sm);background:var(--bg);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text-2);transition:all .15s;text-decoration:none}
.topbar-btn:hover{background:var(--p-light);border-color:var(--p);color:var(--p)}
.admin-avatar{width:34px;height:34px;border-radius:50%;background:var(--p);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;cursor:pointer}
.content{padding:24px 28px;flex:1}

/* ---- PAGE HEADER ---- */
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;gap:16px}
.page-header h1{font-size:20px;font-weight:800;letter-spacing:-.3px}
.page-header p{font-size:12px;color:var(--text-3);margin-top:3px}

/* ---- CARDS ---- */
.card{background:var(--card);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow)}
.card-header{padding:18px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.card-header h3{font-size:14px;font-weight:700}
.card-body{padding:20px}

/* ---- BUTTONS ---- */
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:25px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .15s;font-family:inherit;white-space:nowrap}
.btn-primary{background:var(--p);color:#fff}
.btn-primary:hover{opacity:.9;transform:translateY(-1px)}
.btn-secondary{background:var(--bg);color:var(--text);border:1px solid var(--border)}
.btn-secondary:hover{background:var(--border)}
.btn-sm{padding:6px 12px;font-size:12px;border-radius:20px}
.btn-ghost{background:transparent;color:var(--text-2);border:1px solid var(--border)}
.btn-ghost:hover{background:var(--bg)}
.btn-danger{background:#fee2e2;color:#dc2626;border:1px solid #fecaca}
.btn-danger:hover{background:#fecaca}
.btn-success{background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0}
.btn-success:hover{background:#bbf7d0}
.btn-blue{background:#dbeafe;color:#1d4ed8;border:1px solid #bfdbfe}

/* ---- STAT CARDS ---- */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:var(--card);border-radius:var(--radius);padding:20px;border:1px solid var(--border);box-shadow:var(--shadow);position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--p)}
.stat-card-2::before{background:#3b82f6}
.stat-card-3::before{background:#8b5cf6}
.stat-card-4::before{background:#f59e0b}
.stat-num{font-size:26px;font-weight:800;letter-spacing:-.5px;margin-bottom:4px}
.stat-label{font-size:11px;color:var(--text-3);font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.stat-icon-bg{position:absolute;right:16px;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;opacity:.15}
.stat-trend{font-size:11px;color:#16a34a;font-weight:600;margin-top:6px;display:flex;align-items:center;gap:3px}
.stat-trend.down{color:#dc2626}

/* ---- TABLE ---- */
.table-wrap{overflow-x:auto;border-radius:var(--radius);border:1px solid var(--border)}
.admin-table{width:100%;border-collapse:collapse;background:var(--card)}
.admin-table th{background:#f8f9fc;padding:11px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-3);border-bottom:1px solid var(--border);white-space:nowrap}
.admin-table td{padding:13px 16px;border-bottom:1px solid #f3f4f6;font-size:13px;vertical-align:middle}
.admin-table tr:last-child td{border-bottom:none}
.admin-table tr:hover td{background:#fafbfc}
.admin-table a{color:var(--p);font-weight:500}

/* ---- STATUS BADGES ---- */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.2px}
.badge-pending{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa}
.badge-processing{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
.badge-shipped{background:#f5f3ff;color:#6d28d9;border:1px solid #ddd6fe}
.badge-delivered{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.badge-cancelled{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}
.badge-paid{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.badge-unpaid{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa}

/* ---- FORM ELEMENTS ---- */
.form-input{width:100%;border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:10px 14px;font-size:13px;outline:none;font-family:inherit;background:#fff;color:var(--text);transition:border-color .15s}
.form-input:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-light)}
.form-label{font-size:12px;font-weight:600;color:var(--text-2);margin-bottom:5px;display:block}
.form-group{margin-bottom:16px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}

/* ---- FLASH ---- */
.flash{padding:12px 20px;border-radius:var(--radius-sm);font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;margin-bottom:16px}
.flash.success{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
.flash.error{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}

/* ---- MISC ---- */
.empty-state{text-align:center;padding:60px 20px;color:var(--text-3)}
.empty-state .icon{font-size:48px;margin-bottom:14px;opacity:.4}
.empty-state h3{font-size:16px;font-weight:600;color:var(--text-2);margin-bottom:6px}
.product-thumb{width:40px;height:40px;border-radius:8px;object-fit:cover;background:var(--bg);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.action-btns{display:flex;gap:5px;align-items:center;flex-wrap:wrap}

/* ---- DROPDOWN QUICK ACTIONS ---- */
.status-select{border:1.5px solid var(--border);border-radius:8px;padding:5px 10px;font-size:12px;font-family:inherit;outline:none;background:#fff;cursor:pointer;color:var(--text)}

/* ---- RESPONSIVE ---- */
@media(max-width:1024px){
    .stat-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:768px){
    .sidebar{transform:translateX(-100%);width:min(82vw,300px);box-shadow:var(--shadow-lg)}
    .sidebar.open{transform:translateX(0)}
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:150}
    .sidebar-overlay.show{display:block}
    .main{margin-left:0}
    .topbar{padding:0 14px;height:58px}
    .topbar-title{font-size:14px}
    .topbar-breadcrumb{display:none}
    .topbar-right{gap:8px}
    .content{padding:16px}
    .page-header{flex-direction:column;align-items:flex-start}
    .form-row{grid-template-columns:1fr}
    .settings-row{flex-direction:column;align-items:flex-start!important;gap:10px}
    .settings-row>div:first-child{min-width:0;width:100%}
    .settings-input, textarea.settings-input{width:100%!important;min-width:0!important;max-width:none!important}
    .stat-grid{grid-template-columns:repeat(2,1fr);gap:10px}
    .admin-table th,.admin-table td{padding:10px 12px}
}
@media(max-width:520px){
    .stat-grid{grid-template-columns:1fr}
    .topbar-right .topbar-btn:nth-child(1){display:none}
    .topbar-right .admin-avatar{display:none}
}

/* ---- ADMIN PREMIUM OVERRIDES ---- */
body{background:linear-gradient(180deg,#f6f8fc 0,#eef2f7 100%)}
.sidebar{background:linear-gradient(180deg,#0f172a 0,#111827 100%);border-right:1px solid rgba(255,255,255,.05)}
.sidebar-brand{padding:24px 20px 20px}.sidebar-brand-icon{box-shadow:0 10px 20px rgba(235,23,0,.25)}
.nav-item{border:1px solid transparent}.nav-item:hover{transform:translateX(2px);border-color:rgba(255,255,255,.05)}.nav-item.active{box-shadow:inset 0 0 0 1px rgba(255,255,255,.06)}
.topbar{height:70px;background:rgba(255,255,255,.82);backdrop-filter:blur(16px);border-bottom:1px solid rgba(15,23,42,.06)}
.topbar-title{font-size:18px;letter-spacing:-.3px}.content{padding:28px}
.card,.stat-card,.table-wrap{box-shadow:0 18px 40px rgba(15,23,42,.06);border-color:#edf0f3}.card{border-radius:20px}.card-header{padding:20px 22px}.card-body{padding:22px}.stat-card{border-radius:22px;padding:22px}.stat-num{font-size:30px}.btn{border-radius:14px;padding:10px 16px}.btn-primary{box-shadow:0 12px 26px rgba(235,23,0,.18)}
.form-input{border-radius:14px;padding:11px 14px}.flash{border-radius:16px;box-shadow:0 14px 28px rgba(15,23,42,.06)}.admin-table th{background:#f8fafc}.admin-table td{font-size:13px}
@media(max-width:768px){.content{padding:16px}.topbar{height:62px}.card,.stat-card{border-radius:18px}}

.form-group input:not([type=checkbox]):not([type=radio]),
.form-group select,
.form-group textarea,
input.settings-input,
textarea.settings-input,
select.settings-input,
input[type=file]{width:100%;border:1.5px solid var(--border);border-radius:14px;padding:12px 14px;font-size:14px;font-family:inherit;background:#fff;color:var(--text);outline:none;transition:all .15s;box-shadow:0 4px 12px rgba(15,23,42,.03)}
.form-group input:not([type=checkbox]):not([type=radio]):focus,
.form-group select:focus,
.form-group textarea:focus,
input.settings-input:focus,
textarea.settings-input:focus,
select.settings-input:focus{border-color:var(--p);box-shadow:0 0 0 4px var(--p-light)}
.form-group textarea{min-height:120px;resize:vertical}
.form-checkline{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:18px}
</style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeAdminSidebar()"></div>

<!-- SIDEBAR -->
<nav class="sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">🍵</div>
        <div>
            <div class="sidebar-brand-text"><?= htmlspecialchars($siteName) ?></div>
            <div class="sidebar-brand-sub">Admin Panel</div>
        </div>
    </div>
    <div class="sidebar-nav">
        <div class="nav-section">Overview</div>
        <a href="<?= pretty_url('admin/index.php') ?>" class="nav-item <?= $currentPage==='index.php'?'active':'' ?>">
            <span class="nav-icon"><i class="fas fa-chart-line"></i></span> Dashboard
        </a>

        <div class="nav-section">Content</div>
        <a href="<?= pretty_url('admin/homepage.php') ?>" class="nav-item <?= $currentPage==='homepage.php'?'active':'' ?>">
            <span class="nav-icon"><i class="fas fa-home"></i></span> Homepage
        </a>
        <a href="<?= pretty_url('admin/nav-menu.php') ?>" class="nav-item <?= $currentPage==='nav-menu.php'?'active':'' ?>">
            <span class="nav-icon"><i class="fas fa-bars"></i></span> Nav Menu
        </a>

        <div class="nav-section">Catalog</div>
        <a href="<?= pretty_url('admin/products.php') ?>" class="nav-item <?= strpos($currentPage,'product')!==false?'active':'' ?>">
            <span class="nav-icon"><i class="fas fa-box"></i></span> Products
        </a>
        <a href="<?= pretty_url('admin/categories.php') ?>" class="nav-item <?= $currentPage==='categories.php'?'active':'' ?>">
            <span class="nav-icon"><i class="fas fa-tags"></i></span> Categories
        </a>
        <a href="<?= pretty_url('admin/brands.php') ?>" class="nav-item <?= $currentPage==='brands.php'?'active':'' ?>">
            <span class="nav-icon"><i class="fas fa-award"></i></span> Brands
        </a>

        <div class="nav-section">Sales</div>
        <a href="<?= pretty_url('admin/orders.php') ?>" class="nav-item <?= $currentPage==='orders.php'?'active':'' ?>">
            <span class="nav-icon"><i class="fas fa-shopping-bag"></i></span> Orders
            <?php
            try { $pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
            if ($pending > 0): ?><span class="nav-badge"><?= $pending ?></span><?php endif; } catch(\Exception $e) {} ?>
        </a>
        <a href="<?= pretty_url('admin/reports.php') ?>" class="nav-item <?= $currentPage==='reports.php'?'active':'' ?>">
            <span class="nav-icon"><i class="fas fa-chart-bar"></i></span> Reports
        </a>
        <a href="<?= pretty_url('admin/users.php') ?>" class="nav-item <?= $currentPage==='users.php'?'active':'' ?>">
            <span class="nav-icon"><i class="fas fa-users"></i></span> Users & Perms
        </a>

        <div class="nav-section">System</div>
        <a href="<?= pretty_url('admin/media.php') ?>" class="nav-item <?= $currentPage==='media.php'?'active':'' ?>">
            <span class="nav-icon"><i class="fas fa-images"></i></span> Media Library
        </a>
        <a href="<?= pretty_url('admin/settings.php') ?>" class="nav-item <?= $currentPage==='settings.php'?'active':'' ?>">
            <span class="nav-icon"><i class="fas fa-sliders-h"></i></span> Settings
        </a>
        <?php if (!getSetting('telegram_bot_token')): ?>
        <a href="<?= pretty_url('admin/settings.php') ?>?tab=notifications" class="nav-item" style="color:#f59e0b">
            <span class="nav-icon"><i class="fab fa-telegram"></i></span> Setup Telegram
            <span class="nav-badge" style="background:#f59e0b">!</span>
        </a>
        <?php endif; ?>
    </div>
    <div class="sidebar-footer">
        <a href="<?= pretty_url('index.php') ?>" target="_blank" class="nav-item">
            <span class="nav-icon"><i class="fas fa-external-link-alt"></i></span> View Store
        </a>
        <a href="<?= pretty_url('pages/logout.php') ?>" class="nav-item" style="color:#f87171">
            <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span> Logout
        </a>
    </div>
</nav>

<!-- MAIN -->
<div class="main">
<!-- TOPBAR -->
<div class="topbar">
    <div class="topbar-left">
        <button class="topbar-btn" onclick="document.getElementById('adminSidebar').classList.toggle('open')" style="display:none" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div>
            <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
            <div class="topbar-breadcrumb"><span><?= htmlspecialchars($siteName) ?></span><span>›</span><span><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></span></div>
        </div>
    </div>
    <div class="topbar-right">
        <a href="<?= pretty_url('admin/settings.php') ?>?tab=tools&action=backup" class="topbar-btn" title="Backup DB"><i class="fas fa-database"></i></a>
        <a href="<?= pretty_url('index.php') ?>" target="_blank" class="topbar-btn" title="View Store"><i class="fas fa-store"></i></a>
        <a href="<?= pretty_url('pages/logout.php') ?>" class="topbar-btn" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
        <div class="admin-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?></div>
    </div>
</div>

<!-- Content area -->
<div class="content">
<?php if ($msg = flash('success')): ?><div class="flash success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="flash error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<script>
const menuToggleBtn = document.getElementById('menuToggle');
const adminSidebar = document.getElementById('adminSidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
function syncAdminMenuUi(){
    menuToggleBtn.style.display = window.innerWidth <= 768 ? 'flex' : 'none';
    if (window.innerWidth > 768) {
        adminSidebar.classList.remove('open');
        sidebarOverlay.classList.remove('show');
    }
}
function toggleAdminSidebar(){
    adminSidebar.classList.toggle('open');
    sidebarOverlay.classList.toggle('show');
}
function closeAdminSidebar(){
    adminSidebar.classList.remove('open');
    sidebarOverlay.classList.remove('show');
}
menuToggleBtn.onclick = toggleAdminSidebar;
window.addEventListener('resize', syncAdminMenuUi);
syncAdminMenuUi();
document.querySelectorAll('#adminSidebar a').forEach(a => a.addEventListener('click', closeAdminSidebar));
</script>
