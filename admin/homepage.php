<?php
$pageTitle = "Homepage Manager – TeaStore Admin";
require_once __DIR__ . '/../includes/config.php';
if (!canAccessAdmin()) { flash('error','Admin access required'); redirect(pretty_url('pages/login.php')); }

// Ensure tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS hero_banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255),
        subtitle VARCHAR(500),
        button_text VARCHAR(100),
        button_url VARCHAR(500),
        image VARCHAR(255),
        bg_color VARCHAR(20) DEFAULT '#1a1a1a',
        text_color VARCHAR(20) DEFAULT '#ffffff',
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS home_sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_key VARCHAR(80) UNIQUE NOT NULL,
        section_label VARCHAR(100),
        sort_order INT DEFAULT 0,
        is_visible TINYINT(1) DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Insert defaults if empty
    $pdo->exec("INSERT IGNORE INTO home_sections (section_key,section_label,sort_order,is_visible) VALUES
        ('categories','Browse by Category',1,1),
        ('new_arrivals','✨ New Arrivals',2,1),
        ('featured','⭐ Customer Favorites',3,1),
        ('dogs','🍃 Green Tea',4,1),
        ('cats','🫖 Black Tea',5,1),
        ('sale','🏷️ On Sale',6,1),
        ('brands','Top Brands',7,1)");
} catch (Throwable $e) {}

$selfUrl = SITE_URL . '/admin/homepage.php';
$uploadDir = __DIR__ . '/../assets/img/banners';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// ─── Handle actions ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Save grid layout settings
    if ($action === 'save_grid_settings') {
        setSetting('home_product_cols', max(2,min(6,(int)($_POST['home_product_cols']??4))), 'theme');
        setSetting('home_products_per_section', max(1,min(20,(int)($_POST['home_products_per_section']??8))), 'theme');
        setSetting('shop_product_cols', max(2,min(6,(int)($_POST['shop_product_cols']??4))), 'theme');
        setSetting('shop_per_page', max(4,min(48,(int)($_POST['shop_per_page']??8))), 'theme');
        clearSettingCache();
        flash('success','Grid layout settings saved!');
        header('Location: '.$selfUrl); exit;
    }

    // Save slider settings
    if ($action === 'save_slider_settings') {
        setSetting('banner_autoplay',   isset($_POST['autoplay']) ? '1' : '0', 'homepage');
        setSetting('banner_autoplay_speed', max(1000,(int)($_POST['speed']??4000)), 'homepage');
        $w = trim($_POST['banner_width']??'1260');  if($w) setSetting('banner_width', $w, 'homepage');
        $h = max(80,(int)($_POST['banner_height']??250)); setSetting('banner_height', $h, 'homepage');
        $hm = max(80,(int)($_POST['banner_height_mobile']??200)); setSetting('banner_height_mobile', $hm, 'homepage');
        $br = max(0,(int)($_POST['banner_border_radius']??0)); setSetting('banner_border_radius', $br, 'homepage');
        $ov = max(0,min(100,(int)($_POST['banner_overlay_opacity']??18))); setSetting('banner_overlay_opacity', $ov, 'homepage');
        clearSettingCache();
        flash('success','Slider settings saved!');
        header('Location: '.$selfUrl); exit;
    }

    // Add/edit banner
    if ($action === 'save_banner') {
        $bid   = (int)($_POST['banner_id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $sub   = sanitize($_POST['subtitle'] ?? '');
        $btnTx = sanitize($_POST['button_text'] ?? '');
        $btnUrl= trim($_POST['button_url'] ?? '');
        $bgCol = preg_replace('/[^#a-fA-F0-9]/','',$_POST['bg_color']??'#1a1a1a');
        $txtCol= preg_replace('/[^#a-fA-F0-9]/','',$_POST['text_color']??'#ffffff');
        $sort  = (int)($_POST['sort_order'] ?? 0);
        $active= isset($_POST['is_active']) ? 1 : 0;

        $image = $bid ? ($pdo->prepare("SELECT image FROM hero_banners WHERE id=?")->execute([$bid]) ? $pdo->prepare("SELECT image FROM hero_banners WHERE id=?")->execute([$bid]) : '') : '';
        // Re-fetch properly
        if ($bid) {
            $imgRow = $pdo->prepare("SELECT image FROM hero_banners WHERE id=?");
            $imgRow->execute([$bid]); $imgRow = $imgRow->fetch();
            $image = $imgRow['image'] ?? '';
        }

        if (!empty($_FILES['banner_image']['name']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                $fn = 'banner_'.uniqid().'.'.$ext;
                if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $uploadDir.'/'.$fn)) {
                    // Delete old
                    if ($image) @unlink($uploadDir.'/'.$image);
                    $image = $fn;
                }
            }
        }

        if ($bid) {
            $pdo->prepare("UPDATE hero_banners SET title=?,subtitle=?,button_text=?,button_url=?,image=?,bg_color=?,text_color=?,sort_order=?,is_active=? WHERE id=?")
                ->execute([$title,$sub,$btnTx,$btnUrl,$image,$bgCol,$txtCol,$sort,$active,$bid]);
            flash('success','Banner updated!');
        } else {
            $pdo->prepare("INSERT INTO hero_banners (title,subtitle,button_text,button_url,image,bg_color,text_color,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$title,$sub,$btnTx,$btnUrl,$image,$bgCol,$txtCol,$sort,$active]);
            flash('success','Banner added!');
        }
        header('Location: '.$selfUrl); exit;
    }

    // Delete banner
    if ($action === 'delete_banner') {
        $bid = (int)($_POST['banner_id'] ?? 0);
        $row = $pdo->prepare("SELECT image FROM hero_banners WHERE id=?");
        $row->execute([$bid]); $row = $row->fetch();
        if ($row && $row['image']) @unlink($uploadDir.'/'.$row['image']);
        $pdo->prepare("DELETE FROM hero_banners WHERE id=?")->execute([$bid]);
        flash('success','Banner deleted.');
        header('Location: '.$selfUrl); exit;
    }

    // Toggle banner active
    if ($action === 'toggle_banner') {
        $bid = (int)($_POST['banner_id'] ?? 0);
        $pdo->prepare("UPDATE hero_banners SET is_active = 1 - is_active WHERE id=?")->execute([$bid]);
        header('Content-Type: application/json');
        echo json_encode(['ok'=>true]); exit;
    }

    // Save sections order/visibility
    if ($action === 'save_sections') {
        $keys  = $_POST['section_keys'] ?? [];
        $vis   = $_POST['section_visible'] ?? [];
        foreach ($keys as $i => $key) {
            $key = sanitize($key);
            $isVis = in_array($key, $vis) ? 1 : 0;
            $pdo->prepare("UPDATE home_sections SET sort_order=?,is_visible=? WHERE section_key=?")
                ->execute([$i+1, $isVis, $key]);
        }
        flash('success','Homepage sections saved!');
        header('Location: '.$selfUrl); exit;
    }

    header('Location: '.$selfUrl); exit;
}

// ─── Load data ───────────────────────────────────────────────────────────────
$banners  = $pdo->query("SELECT * FROM hero_banners ORDER BY sort_order, id")->fetchAll();
$sections = $pdo->query("SELECT * FROM home_sections ORDER BY sort_order, id")->fetchAll();

$bannerW = getSetting('banner_width','1260');
$bannerH = getSetting('banner_height','250');
$bannerHMobile = getSetting('banner_height_mobile','200');
$bannerBorderRadius = getSetting('banner_border_radius','0');
$bannerOverlayOpacity = getSetting('banner_overlay_opacity','18');
$autoplay = getSetting('banner_autoplay','1');
$autoplaySpeed = getSetting('banner_autoplay_speed','4000');

require_once __DIR__ . '/admin-header.php';
?>
<style>
.hm-tabs{display:flex;gap:0;border-bottom:2px solid #edf0f3;margin-bottom:24px}
.hm-tab{padding:11px 20px;font-size:13px;font-weight:700;color:#64748b;border-bottom:3px solid transparent;cursor:pointer;transition:.15s;margin-bottom:-2px;text-decoration:none}
.hm-tab.active{color:var(--primary);border-bottom-color:var(--primary)}
.hm-card{background:#fff;border:1.5px solid #e8edf3;border-radius:18px;padding:22px;box-shadow:0 2px 12px rgba(15,23,42,.05);margin-bottom:20px}
.hm-banner-row{display:flex;align-items:center;gap:14px;padding:14px;border:1.5px solid #e8edf3;border-radius:14px;background:#fafbfc;margin-bottom:10px}
.hm-banner-thumb{width:88px;height:46px;border-radius:8px;object-fit:cover;background:#e2e8f0;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:20px;overflow:hidden}
.hm-banner-thumb img{width:100%;height:100%;object-fit:cover}
.hm-label{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px}
.hm-inp{border:1.5px solid #e2e8f0;border-radius:9px;padding:8px 11px;font-size:13px;outline:none;font-family:inherit;background:#fff;width:100%;box-sizing:border-box;transition:.15s}
.hm-inp:focus{border-color:#1e293b}
.hm-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:9px;border:none;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s}
.hm-btn-primary{background:linear-gradient(135deg,#eb1700,#ff4a26);color:#fff}
.hm-btn-primary:hover{opacity:.9}
.hm-btn-ghost{background:#fff;border:1.5px solid #e2e8f0;color:#374151}
.hm-btn-ghost:hover{background:#f8fafc}
.hm-btn-danger{background:#fff;border:1.5px solid #fecaca;color:#ef4444}
.hm-btn-danger:hover{background:#fef2f2}
.hm-section-row{display:flex;align-items:center;gap:12px;padding:12px 16px;border:1.5px solid #e8edf3;border-radius:12px;background:#fff;margin-bottom:8px;cursor:grab;user-select:none}
.hm-section-row:active{cursor:grabbing;background:#f8fafc}
.hm-drag-handle{color:#94a3b8;font-size:16px;flex-shrink:0}
.hm-toggle{width:40px;height:22px;border-radius:11px;background:#e2e8f0;position:relative;cursor:pointer;flex-shrink:0;transition:.2s;border:none}
.hm-toggle.on{background:var(--primary)}
.hm-toggle::after{content:'';position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;transition:.2s}
.hm-toggle.on::after{left:21px}
.form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
.hm-preview-strip{width:100%;height:180px;border-radius:12px;overflow:hidden;position:relative;display:flex;align-items:center;justify-content:center;margin-top:10px;background:#1a1a1a}
.drag-over{border-color:var(--primary)!important;background:#fff8f8!important}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:22px;font-weight:900;color:#0f172a;margin:0">🏠 Homepage Manager</h1>
    <p style="font-size:13px;color:#64748b;margin-top:3px">Manage banners, sections order &amp; visibility</p>
  </div>
  <a href="<?= SITE_URL ?>/" target="_blank" class="hm-btn hm-btn-ghost"><i class="fas fa-external-link-alt"></i> Preview Homepage</a>
</div>

<div class="hm-tabs">
  <a href="#" class="hm-tab active" onclick="switchTab('banners',this);return false">🖼️ Hero Banners</a>
  <a href="#" class="hm-tab" onclick="switchTab('sections',this);return false">📋 Sections</a>
  <a href="#" class="hm-tab" onclick="switchTab('trust',this);return false">🤝 Trust Cards</a>
  <a href="#" class="hm-tab" onclick="switchTab('settings',this);return false">⚙️ Slider Settings</a>
</div>

<!-- ═══ TAB: BANNERS ══════════════════════════════════════════════════════════ -->
<div id="tab_banners">

  <!-- Existing banners -->
  <div class="hm-card">
    <h3 style="font-size:14px;font-weight:800;color:#0f172a;margin:0 0 16px">Active Banners (<?= count($banners) ?>)</h3>
    <?php if (empty($banners)): ?>
    <div style="text-align:center;padding:32px;color:#94a3b8;font-size:13px">No banners yet — add one below ↓</div>
    <?php endif; ?>
    <?php foreach ($banners as $b): ?>
    <div class="hm-banner-row" id="bannerRow_<?= (int)$b['id'] ?>">
      <div class="hm-banner-thumb" style="background:<?= htmlspecialchars($b['bg_color']) ?>">
        <?php if ($b['image']): ?>
        <img src="<?= SITE_URL ?>/assets/img/banners/<?= htmlspecialchars($b['image']) ?>" alt="">
        <?php else: ?><span>🖼️</span><?php endif; ?>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:13px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($b['title'] ?: '(no title)') ?></div>
        <div style="font-size:11px;color:#94a3b8;margin-top:2px"><?= htmlspecialchars($b['subtitle'] ? substr($b['subtitle'],0,60).'...' : 'No subtitle') ?></div>
      </div>
      <div style="display:flex;align-items:center;gap:6px;flex-shrink:0">
        <button type="button" class="hm-toggle <?= $b['is_active'] ? 'on' : '' ?>"
                onclick="toggleBanner(<?= (int)$b['id'] ?>,this)" title="<?= $b['is_active'] ? 'Active' : 'Inactive' ?>"></button>
        <button class="hm-btn hm-btn-ghost" style="font-size:11px;padding:6px 10px" onclick="editBanner(<?= htmlspecialchars(json_encode($b)) ?>)">✏️ Edit</button>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this banner?')">
          <input type="hidden" name="action" value="delete_banner">
          <input type="hidden" name="banner_id" value="<?= (int)$b['id'] ?>">
          <button type="submit" class="hm-btn hm-btn-danger" style="font-size:11px;padding:6px 10px">🗑</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Add/Edit banner form -->
  <div class="hm-card" id="bannerFormCard">
    <h3 style="font-size:14px;font-weight:800;color:#0f172a;margin:0 0 16px" id="bannerFormTitle">➕ Add New Banner</h3>
    <form method="POST" enctype="multipart/form-data" id="bannerForm">
      <input type="hidden" name="action" value="save_banner">
      <input type="hidden" name="banner_id" id="banner_id_field" value="0">
      <div class="form-grid-2" style="margin-bottom:14px">
        <div>
          <label class="hm-label">Title</label>
          <input type="text" name="title" id="bf_title" class="hm-inp" placeholder="Premium Tea & Accessories">
        </div>
        <div>
          <label class="hm-label">Button Text</label>
          <input type="text" name="button_text" id="bf_btn_text" class="hm-inp" placeholder="Shop Now">
        </div>
      </div>
      <div style="margin-bottom:14px">
        <label class="hm-label">Subtitle</label>
        <input type="text" name="subtitle" id="bf_subtitle" class="hm-inp" placeholder="TeaStore – Your trusted online tea store">
      </div>
      <div class="form-grid-2" style="margin-bottom:14px">
        <div>
          <label class="hm-label">Button URL</label>
          <input type="text" name="button_url" id="bf_btn_url" class="hm-inp" placeholder="/shop">
        </div>
        <div>
          <label class="hm-label">Sort Order</label>
          <input type="number" name="sort_order" id="bf_sort" class="hm-inp" value="0">
        </div>
      </div>
      <div class="form-grid-3" style="margin-bottom:14px">
        <div>
          <label class="hm-label">Background Color</label>
          <div style="display:flex;gap:6px;align-items:center">
            <input type="color" id="bf_bg_color_picker" value="#1a1a1a" oninput="document.getElementById('bf_bg_color').value=this.value;updateBannerPreview()" style="width:36px;height:36px;border:none;cursor:pointer;border-radius:6px">
            <input type="text" name="bg_color" id="bf_bg_color" class="hm-inp" value="#1a1a1a" oninput="document.getElementById('bf_bg_color_picker').value=this.value;updateBannerPreview()" style="flex:1">
          </div>
        </div>
        <div>
          <label class="hm-label">Text Color</label>
          <div style="display:flex;gap:6px;align-items:center">
            <input type="color" id="bf_text_color_picker" value="#ffffff" oninput="document.getElementById('bf_text_color').value=this.value;updateBannerPreview()" style="width:36px;height:36px;border:none;cursor:pointer;border-radius:6px">
            <input type="text" name="text_color" id="bf_text_color" class="hm-inp" value="#ffffff" oninput="document.getElementById('bf_text_color_picker').value=this.value;updateBannerPreview()" style="flex:1">
          </div>
        </div>
        <div>
          <label class="hm-label">Active?</label>
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;cursor:pointer;margin-top:4px">
            <input type="checkbox" name="is_active" id="bf_active" checked> Show on homepage
          </label>
        </div>
      </div>
      <div style="margin-bottom:14px">
        <label class="hm-label">Banner Image <span style="font-weight:400;text-transform:none;letter-spacing:0">(recommended: <?= $bannerW ?>×<?= $bannerH ?>px)</span></label>
        <div style="border:2px dashed #d1d5db;border-radius:12px;padding:16px;text-align:center;cursor:pointer;background:#fafbfc" onclick="document.getElementById('bf_image').click()">
          <input type="file" name="banner_image" id="bf_image" accept="image/*" style="display:none" onchange="previewBannerImage(this)">
          <div id="bf_img_preview" style="font-size:22px;margin-bottom:6px">🖼️</div>
          <div style="font-size:12px;color:#94a3b8">Click to upload · JPG, PNG, WebP</div>
        </div>
      </div>

      <!-- Live preview -->
      <div id="bannerPreview" class="hm-preview-strip" style="background:#1a1a1a">
        <div style="text-align:center;padding:20px;color:#fff" id="bannerPreviewContent">
          <div style="font-size:22px;font-weight:800;margin-bottom:6px" id="bp_title">Preview</div>
          <div style="font-size:13px;opacity:.75;margin-bottom:12px" id="bp_sub">Your subtitle here</div>
          <div style="display:inline-block;background:var(--primary);color:#fff;padding:8px 18px;border-radius:20px;font-size:12px;font-weight:700" id="bp_btn">Shop Now</div>
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:16px">
        <button type="submit" class="hm-btn hm-btn-primary" style="padding:10px 20px">💾 Save Banner</button>
        <button type="button" class="hm-btn hm-btn-ghost" onclick="resetBannerForm()">✕ Clear</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ TAB: TRUST CARDS ════════════════════════════════════════════════════ -->
<div id="tab_trust" style="display:none">
  <div class="hm-card">
    <h3 style="font-size:14px;font-weight:800;margin:0 0 6px">🤝 Trust Cards Editor</h3>
    <p style="font-size:12px;color:#64748b;margin-bottom:16px">Edit the feature highlights shown below the hero banner (e.g. Free Delivery, Secure Payment).</p>
    <?php
    $trustCardsJson = getSetting('trust_cards', '');
    $trustCards = $trustCardsJson ? json_decode($trustCardsJson, true) : [];
    if (empty($trustCards)) {
        $trustCards = [
            ['icon'=>'🚚','title'=>'Free Delivery','desc'=>'Free on orders over $49 in Phnom Penh'],
            ['icon'=>'⭐','title'=>'Premium Quality','desc'=>'Vet-approved and tested products only'],
            ['icon'=>'🔒','title'=>'Secure Payment','desc'=>'Stripe/PayPal, cash on delivery & more'],
        ];
    }
    ?>
    <form method="POST">
      <input type="hidden" name="action" value="save_trust_cards">
      <div id="trustCardsList">
        <?php foreach ($trustCards as $idx => $tc): ?>
        <div class="trust-card-row" style="display:grid;grid-template-columns:60px 1fr 2fr auto;gap:10px;align-items:center;margin-bottom:10px;padding:12px;border:1.5px solid #e8edf3;border-radius:12px;background:#fafbfc">
          <input type="text" name="trust_icon[]" value="<?= htmlspecialchars($tc['icon']) ?>" class="hm-inp" placeholder="🚚" style="text-align:center;font-size:20px">
          <input type="text" name="trust_title[]" value="<?= htmlspecialchars($tc['title']) ?>" class="hm-inp" placeholder="Title">
          <input type="text" name="trust_desc[]" value="<?= htmlspecialchars($tc['desc']) ?>" class="hm-inp" placeholder="Description">
          <button type="button" class="hm-btn hm-btn-danger" onclick="this.closest('.trust-card-row').remove()" style="font-size:11px;padding:6px 10px">🗑</button>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:10px;margin-top:12px">
        <button type="button" class="hm-btn hm-btn-ghost" onclick="addTrustCard()">➕ Add Card</button>
        <button type="submit" class="hm-btn hm-btn-primary" style="padding:10px 20px">💾 Save Trust Cards</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ TAB: SECTIONS ═══════════════════════════════════════════════════════ -->
<div id="tab_sections" style="display:none">
  <div class="hm-card">
    <h3 style="font-size:14px;font-weight:800;margin:0 0 6px">Drag to reorder · Toggle to show/hide sections</h3>
    <p style="font-size:12px;color:#64748b;margin-bottom:16px">Changes take effect on the homepage immediately after saving.</p>
    <form method="POST" id="sectionsForm">
      <input type="hidden" name="action" value="save_sections">
      <div id="sectionsList">
        <?php foreach ($sections as $sec): ?>
        <div class="hm-section-row" draggable="true" data-key="<?= htmlspecialchars($sec['section_key']) ?>">
          <span class="hm-drag-handle">⠿</span>
          <input type="hidden" name="section_keys[]" value="<?= htmlspecialchars($sec['section_key']) ?>">
          <span style="font-size:14px;font-weight:700;color:#0f172a;flex:1"><?= htmlspecialchars($sec['section_label']) ?></span>
          <label style="display:flex;align-items:center;gap:7px;font-size:12px;color:#475569;cursor:pointer">
            <input type="checkbox" name="section_visible[]" value="<?= htmlspecialchars($sec['section_key']) ?>" <?= $sec['is_visible'] ? 'checked' : '' ?>>
            Visible
          </label>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:16px">
        <button type="submit" class="hm-btn hm-btn-primary" style="padding:10px 22px">💾 Save Section Order</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ TAB: SETTINGS ═══════════════════════════════════════════════════════ -->
<div id="tab_settings" style="display:none">
  <div class="hm-card">
    <h3 style="font-size:14px;font-weight:800;margin:0 0 16px">🎛️ Banner Slider Settings</h3>
    <form method="POST">
      <input type="hidden" name="action" value="save_slider_settings">
      <div style="margin-bottom:16px">
        <label class="hm-label">Desktop Banner Size</label>
        <div style="display:flex;align-items:center;gap:10px">
          <input type="number" name="banner_width" value="<?= (int)($bannerW ?: 1260) ?>" class="hm-inp" style="max-width:100px" placeholder="1260"> px wide
          <span style="color:#94a3b8">×</span>
          <input type="number" name="banner_height" value="<?= (int)($bannerH ?: 250) ?>" class="hm-inp" style="max-width:100px" placeholder="250"> px tall
        </div>
        <p style="font-size:11px;color:#94a3b8;margin-top:5px">Width is max-width. Height sets the slide container height in px.</p>
      </div>
      <div style="margin-bottom:16px">
        <label class="hm-label">📱 Mobile Banner Height</label>
        <div style="display:flex;align-items:center;gap:10px">
          <input type="number" name="banner_height_mobile" value="<?= (int)($bannerHMobile ?: 200) ?>" class="hm-inp" style="max-width:100px" placeholder="200"> px tall on mobile
        </div>
        <p style="font-size:11px;color:#94a3b8;margin-top:5px">Height of the banner on screens ≤768px.</p>
      </div>
      <div style="margin-bottom:16px">
        <label class="hm-label">🔲 Slide Border Radius</label>
        <div style="display:flex;align-items:center;gap:10px">
          <input type="number" name="banner_border_radius" value="<?= (int)($bannerBorderRadius ?: 0) ?>" class="hm-inp" style="max-width:100px" placeholder="0"> px
        </div>
        <p style="font-size:11px;color:#94a3b8;margin-top:5px">Round the corners of the banner (e.g. 12 for subtle rounding, 0 for sharp edges).</p>
      </div>
      <div style="margin-bottom:16px">
        <label class="hm-label">🌑 Overlay Darkness (%)</label>
        <div style="display:flex;align-items:center;gap:10px">
          <input type="range" name="banner_overlay_opacity" id="overlayRange" min="0" max="80" step="5" value="<?= (int)($bannerOverlayOpacity ?: 18) ?>" oninput="document.getElementById('overlayVal').textContent=this.value+'%'" style="width:200px">
          <span id="overlayVal" style="font-size:13px;font-weight:700;color:#475569"><?= (int)($bannerOverlayOpacity ?: 18) ?>%</span>
        </div>
        <p style="font-size:11px;color:#94a3b8;margin-top:5px">0% = no dark overlay (show image normally). Higher = darker overlay so text is more readable.</p>
      </div>
      <div style="margin-bottom:16px">
        <label class="hm-label">Autoplay</label>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;cursor:pointer">
          <input type="checkbox" name="autoplay" <?= $autoplay === '1' ? 'checked' : '' ?>> Auto-advance slides
        </label>
      </div>
      <div style="margin-bottom:16px">
        <label class="hm-label">Autoplay Speed (ms)</label>
        <div style="display:flex;align-items:center;gap:8px">
          <input type="range" name="speed" id="speedRange" min="1000" max="10000" step="500" value="<?= (int)($autoplaySpeed ?: 4000) ?>" oninput="document.getElementById('speedVal').textContent=this.value+'ms'" style="width:220px">
          <span id="speedVal" style="font-size:13px;font-weight:700;color:#475569"><?= (int)($autoplaySpeed ?: 4000) ?>ms</span>
        </div>
      </div>
      <button type="submit" class="hm-btn hm-btn-primary" style="padding:10px 22px">💾 Save Settings</button>
    </form>
  </div>

  <div class="hm-card" style="margin-top:20px">
    <h3 style="font-size:14px;font-weight:800;margin:0 0 16px">📐 Product Grid Layout</h3>
    <form method="POST">
      <input type="hidden" name="action" value="save_grid_settings">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div>
          <label class="hm-label">🏠 Homepage Columns</label>
          <input type="number" name="home_product_cols" min="2" max="6" value="<?= (int)(getSetting('home_product_cols','4')) ?>" class="hm-inp" style="max-width:80px"> per row
          <p style="font-size:11px;color:#94a3b8;margin-top:4px">Products per row on homepage (2–6)</p>
        </div>
        <div>
          <label class="hm-label">🔢 Products Per Section</label>
          <input type="number" name="home_products_per_section" min="1" max="20" value="<?= (int)(getSetting('home_products_per_section','8')) ?>" class="hm-inp" style="max-width:80px"> items
          <p style="font-size:11px;color:#94a3b8;margin-top:4px">Max shown per section (1–20)</p>
        </div>
        <div>
          <label class="hm-label">🛒 Shop Columns</label>
          <input type="number" name="shop_product_cols" min="2" max="6" value="<?= (int)(getSetting('shop_product_cols','4')) ?>" class="hm-inp" style="max-width:80px"> per row
          <p style="font-size:11px;color:#94a3b8;margin-top:4px">Products per row on shop page (2–6)</p>
        </div>
        <div>
          <label class="hm-label">📄 Shop Items Per Page</label>
          <input type="number" name="shop_per_page" min="4" max="48" step="4" value="<?= (int)(getSetting('shop_per_page','8')) ?>" class="hm-inp" style="max-width:80px"> items
          <p style="font-size:11px;color:#94a3b8;margin-top:4px">Products per page on shop (4–48)</p>
        </div>
      </div>
      <button type="submit" class="hm-btn hm-btn-primary" style="padding:10px 22px">💾 Save Grid Settings</button>
    </form>
  </div>
</div>

<script>
// Tab switching
function switchTab(name, el) {
    ['banners','sections','trust','settings'].forEach(t => {
        document.getElementById('tab_'+t).style.display = t===name ? 'block' : 'none';
    });
    document.querySelectorAll('.hm-tab').forEach(a => a.classList.remove('active'));
    el.classList.add('active');
}

function addTrustCard() {
    const list = document.getElementById('trustCardsList');
    const div = document.createElement('div');
    div.className = 'trust-card-row';
    div.style.cssText = 'display:grid;grid-template-columns:60px 1fr 2fr auto;gap:10px;align-items:center;margin-bottom:10px;padding:12px;border:1.5px solid #e8edf3;border-radius:12px;background:#fafbfc';
    div.innerHTML = `<input type="text" name="trust_icon[]" value="" class="hm-inp" placeholder="🎁" style="text-align:center;font-size:20px"><input type="text" name="trust_title[]" value="" class="hm-inp" placeholder="Title"><input type="text" name="trust_desc[]" value="" class="hm-inp" placeholder="Description"><button type="button" class="hm-btn hm-btn-danger" onclick="this.closest('.trust-card-row').remove()" style="font-size:11px;padding:6px 10px">🗑</button>`;
    list.appendChild(div);
}

// Edit banner — populate form
function editBanner(b) {
    document.getElementById('banner_id_field').value = b.id;
    document.getElementById('bannerFormTitle').textContent = '✏️ Edit Banner: ' + (b.title || '(no title)');
    document.getElementById('bf_title').value   = b.title || '';
    document.getElementById('bf_subtitle').value = b.subtitle || '';
    document.getElementById('bf_btn_text').value = b.button_text || '';
    document.getElementById('bf_btn_url').value  = b.button_url || '';
    document.getElementById('bf_bg_color').value = b.bg_color || '#1a1a1a';
    document.getElementById('bf_bg_color_picker').value = b.bg_color || '#1a1a1a';
    document.getElementById('bf_text_color').value = b.text_color || '#ffffff';
    document.getElementById('bf_text_color_picker').value = b.text_color || '#ffffff';
    document.getElementById('bf_sort').value = b.sort_order || 0;
    document.getElementById('bf_active').checked = parseInt(b.is_active) === 1;
    if (b.image) {
        document.getElementById('bf_img_preview').innerHTML = `<img src="<?= SITE_URL ?>/assets/img/banners/${b.image}" style="max-height:80px;border-radius:8px">`;
    }
    updateBannerPreview();
    document.getElementById('bannerFormCard').scrollIntoView({behavior:'smooth', block:'start'});
}
function resetBannerForm() {
    document.getElementById('banner_id_field').value = '0';
    document.getElementById('bannerFormTitle').textContent = '➕ Add New Banner';
    document.getElementById('bannerForm').reset();
    document.getElementById('bf_img_preview').innerHTML = '🖼️';
    updateBannerPreview();
}
function previewBannerImage(input) {
    if (input.files && input.files[0]) {
        const r = new FileReader();
        r.onload = e => {
            document.getElementById('bf_img_preview').innerHTML = `<img src="${e.target.result}" style="max-height:80px;border-radius:8px">`;
            document.getElementById('bannerPreview').style.backgroundImage = `url(${e.target.result})`;
            document.getElementById('bannerPreview').style.backgroundSize = 'cover';
            document.getElementById('bannerPreview').style.backgroundPosition = 'center';
        };
        r.readAsDataURL(input.files[0]);
    }
}
function updateBannerPreview() {
    const bg  = document.getElementById('bf_bg_color').value || '#1a1a1a';
    const txt = document.getElementById('bf_text_color').value || '#ffffff';
    const ttl = document.getElementById('bf_title').value || 'Preview Title';
    const sub = document.getElementById('bf_subtitle').value || 'Your subtitle here';
    const btn = document.getElementById('bf_btn_text').value || 'Shop Now';
    const prev = document.getElementById('bannerPreview');
    if (!prev.style.backgroundImage) prev.style.background = bg;
    document.getElementById('bp_title').textContent = ttl;
    document.getElementById('bp_title').style.color = txt;
    document.getElementById('bp_sub').textContent = sub;
    document.getElementById('bp_sub').style.color = txt;
    document.getElementById('bp_btn').textContent = btn;
}
// Live preview on input
['bf_title','bf_subtitle','bf_btn_text','bf_bg_color','bf_text_color'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', updateBannerPreview);
});

// Toggle banner active
async function toggleBanner(id, btn) {
    const fd = new FormData();
    fd.append('action','toggle_banner'); fd.append('banner_id',id);
    await fetch(window.location.href, {method:'POST',body:fd});
    btn.classList.toggle('on');
}

// Drag-to-reorder sections
const list = document.getElementById('sectionsList');
let draggingEl = null;
if (list) {
    list.addEventListener('dragstart', e => {
        draggingEl = e.target.closest('.hm-section-row');
        if (draggingEl) { draggingEl.style.opacity = '.4'; e.dataTransfer.effectAllowed = 'move'; }
    });
    list.addEventListener('dragend', () => {
        if (draggingEl) { draggingEl.style.opacity = ''; draggingEl = null; }
    });
    list.addEventListener('dragover', e => {
        e.preventDefault();
        const target = e.target.closest('.hm-section-row');
        if (target && target !== draggingEl) {
            const rect = target.getBoundingClientRect();
            const mid  = rect.top + rect.height / 2;
            if (e.clientY < mid) list.insertBefore(draggingEl, target);
            else list.insertBefore(draggingEl, target.nextSibling);
        }
    });
    // Sync hidden inputs order on form submit
    document.getElementById('sectionsForm')?.addEventListener('submit', () => {
        list.querySelectorAll('.hm-section-row').forEach(row => {
            const input = row.querySelector('input[name="section_keys[]"]');
            if (input) input.value = row.dataset.key;
        });
    });
}
</script>

<?php require_once __DIR__ . '/admin-footer.php'; ?>
