<?php
$pageTitle = "Site Settings – TeaStore Admin";
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) { flash('error','Admin access required'); redirect(pretty_url('pages/login.php')); }
require_once __DIR__ . '/../includes/telegram.php';

// Handle telegram test
if (isset($_GET['action']) && $_GET['action'] === 'test_telegram') {
    $result = testTelegramConnection();
    if ($result['ok']) flash('success', '✅ Telegram connected! Bot: @'.$result['bot']);
    else flash('error', '❌ Telegram error: '.($result['error']??'unknown'));
    redirect(pretty_url('admin/settings.php').'?tab=notifications');
}

// Handle backup
if (isset($_GET['action']) && $_GET['action'] === 'backup') {
    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $sql = "-- TeaStore Backup ".date('Y-m-d H:i:s')."\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        foreach ($tables as $t) {
            $c = $pdo->query("SHOW CREATE TABLE `$t`")->fetch();
            $sql .= "DROP TABLE IF EXISTS `$t`;\n".$c['Create Table'].";\n\n";
            $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_NUM);
            if ($rows) {
                $sql .= "INSERT INTO `$t` VALUES\n";
                $vals = [];
                foreach ($rows as $r) {
                    $e = array_map(fn($v) => $v===null?'NULL':"'".addslashes($v)."'", $r);
                    $vals[] = '('.implode(',',$e).')';
                }
                $sql .= implode(",\n",$vals).";\n\n";
            }
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="teastore-backup-'.date('Y-m-d-His').'.sql"');
        echo $sql; exit;
    } catch (\Exception $e) { flash('error','Backup failed: '.$e->getMessage()); redirect(pretty_url('admin/settings.php').'?tab=tools'); }
}

// Handle CSV exports
if (isset($_GET['action']) && in_array($_GET['action'],['export_orders','export_users','export_products'])) {
    $type = str_replace('export_','',$_GET['action']);
    $qs = [
        'orders'   => "SELECT order_number,name,phone,email,address,payment_method,payment_status,status,total,created_at FROM orders ORDER BY created_at DESC",
        'users'    => "SELECT name,email,phone,address,created_at FROM users WHERE role='customer' ORDER BY created_at DESC",
        'products' => "SELECT p.name,p.price,p.sale_price,p.stock,b.name as brand,c.name as category,p.tea_type FROM products p LEFT JOIN brands b ON p.brand_id=b.id LEFT JOIN categories c ON p.category_id=c.id",
    ];
    $rows = $pdo->query($qs[$type])->fetchAll();
    if ($rows) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="teastore-'.$type.'-'.date('Y-m-d').'.csv"');
        $f = fopen('php://output','w');
        fputcsv($f, array_keys($rows[0]));
        foreach ($rows as $r) fputcsv($f, $r);
        fclose($f); exit;
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $group = sanitize($_POST['group'] ?? 'general');
    try {
    // Hero bg image upload
    if (!empty($_FILES['hero_bg_image']['name'])) {
        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        if (in_array($_FILES['hero_bg_image']['type'],$allowed)) {
            $ext = pathinfo($_FILES['hero_bg_image']['name'],PATHINFO_EXTENSION);
            $fn  = 'hero-bg-'.time().'.'.$ext;
            $uploadDir = __DIR__ . '/../assets/img'; if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); } if (move_uploaded_file($_FILES['hero_bg_image']['tmp_name'], $uploadDir . '/' . $fn)) setSetting('hero_bg_image',$fn,'homepage');
        }
    }
    // Logo upload
    if (!empty($_FILES['site_logo']['name'])) {
        $allowed = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
        if (in_array($_FILES['site_logo']['type'],$allowed)) {
            $ext = pathinfo($_FILES['site_logo']['name'],PATHINFO_EXTENSION);
            $fn  = 'logo-'.time().'.'.$ext;
            $uploadDir = __DIR__ . '/../assets/img'; if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); } if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $uploadDir . '/' . $fn)) setSetting('site_logo',$fn,'general');
        }
    }
    if (isset($_POST['remove_logo'])) setSetting('site_logo','','general');
    // All val_ fields
    foreach ($_POST as $k => $v) {
        if (substr($k, 0, 4) === 'val_') setSetting(substr($k,4), is_array($v)?implode(',',$v):$v, $group);
    }
    // Only process checkboxes belonging to the submitted group
    $groupCheckboxes = [
        'general'       => ['announcement_bar_enabled','show_telegram_btn','maintenance_mode','show_out_of_stock','reviews_enabled','guest_checkout'],
        'homepage'      => ['show_sale_badge','show_new_badge'],
        'payment'       => ['stripe_enabled','paypal_enabled','cod_enabled'],
        'notifications' => ['telegram_notify_orders','telegram_notify_lowstock'],
        'theme'         => [],
        'shipping'      => [],
        'social'        => [],
        'tools'         => [],
    ];
    foreach ($groupCheckboxes[$group] ?? [] as $ck) {
        setSetting($ck, isset($_POST[$ck]) ? '1' : '0', $group);
    }
    // Process text/select fields for payment group
    if ($group === 'payment') {
        foreach (['stripe_publishable_key','stripe_secret_key','paypal_client_id','paypal_secret','paypal_mode','currency_code'] as $field) {
            if (isset($_POST['val_'.$field])) {
                setSetting($field, $_POST['val_'.$field], 'payment');
            }
        }
    }
    clearSettingCache();
    flash('success','Settings saved!');
    redirect(pretty_url('admin/settings.php').'?tab='.rawurlencode($group));
    } catch (\Throwable $e) {
        clearSettingCache();
        flash('error', 'Settings save failed: ' . $e->getMessage());
        redirect(pretty_url('admin/settings.php').'?tab='.rawurlencode($group));
    }
}

$activeTab = sanitize($_GET['tab'] ?? 'general');
$settings = [];
foreach (teastoreSafeQueryAll("SELECT setting_key, setting_value FROM site_settings") as $r) { $settings[$r['setting_key']] = $r['setting_value']; }
function s($k,$d='') { global $settings; return htmlspecialchars($settings[$k]??$d); }
function isOn($k,$d='0') { global $settings; return ($settings[$k]??$d)==='1'; }

$tabs=[
    'general'       =>['icon'=>'fa-cog',         'label'=>'General'],
    'homepage'      =>['icon'=>'fa-home',         'label'=>'Homepage'],
    'theme'         =>['icon'=>'fa-palette',      'label'=>'Theme & Branding'],
    'shipping'      =>['icon'=>'fa-truck',        'label'=>'Shipping'],
    'payment'       =>['icon'=>'fa-credit-card',  'label'=>'Payment (Stripe & PayPal)'],
    'notifications' =>['icon'=>'fa-bell',         'label'=>'Telegram Alerts'],
    'social'        =>['icon'=>'fa-share-alt',    'label'=>'Social Media'],
    'tools'         =>['icon'=>'fa-tools',        'label'=>'Tools & Backup'],
];
require_once __DIR__ . '/admin-header.php';
?>
<div class="admin-header">
    <div>
        <h1>⚙️ Site Settings</h1>
        <p style="font-size:12px;color:rgba(255,255,255,.5);margin-top:2px">Control every aspect of your TeaStore store</p>
    </div>
    <a href="<?= pretty_url('index.php') ?>" target="_blank" class="btn btn-ghost btn-sm">
        <i class="fas fa-external-link-alt"></i> View Store
    </a>
</div>

<div style="display:grid;grid-template-columns:220px 1fr;gap:20px;align-items:start">

<!-- Sidebar -->
<div style="background:var(--white);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow)">
    <?php foreach($tabs as $key=>$tab): ?>
    <a href="?tab=<?=$key?>" style="display:flex;align-items:center;gap:10px;padding:13px 18px;font-size:13px;font-weight:500;
        color:<?=$activeTab===$key?'var(--primary)':'var(--text-light)'?>;
        background:<?=$activeTab===$key?'var(--bg)':'transparent'?>;
        border-left:3px solid <?=$activeTab===$key?'var(--primary)':'transparent'?>;
        transition:all .15s;text-decoration:none;">
        <i class="fas <?=$tab['icon']?>" style="width:16px;text-align:center;opacity:.7"></i>
        <?=$tab['label']?>
        <?php if($key==='notifications' && !getSetting('telegram_bot_token')): ?><span style="background:#ef4444;color:#fff;font-size:9px;font-weight:700;padding:2px 5px;border-radius:10px;margin-left:auto">Setup</span><?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Content -->
<div style="background:var(--white);border-radius:var(--radius);padding:28px;box-shadow:var(--shadow)">

<?php if($activeTab==='general'): ?>
<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="group" value="general">
<div class="settings-section">
    <h3><i class="fas fa-store" style="color:var(--primary)"></i> Store Identity</h3>
    <?php foreach([
        ['site_name','Store Name','TeaStore','text'],['site_tagline','Tagline',"Cambodia's #1 TeaStore",'text'],
        ['site_phone','Phone','+855 xx xxx xxxx','text'],['site_email','Support Email','hime@teastore.com','email'],
        ['site_address','Address','Phnom Penh, Cambodia','text'],['currency_symbol','Currency Symbol','$','text'],
    ] as [$k,$l,$ph,$t]): ?>
    <div class="settings-row">
        <div><label><?=$l?></label></div>
        <input type="<?=$t?>" name="val_<?=$k?>" value="<?=s($k,$ph)?>" class="settings-input" placeholder="<?=$ph?>" autocomplete="off">
    </div>
    <?php endforeach; ?>
    <div class="settings-row">
        <div><label>Store Logo</label><p>PNG/JPG/SVG shown in header</p></div>
        <div>
            <?php $logo=s('site_logo'); if($logo): ?>
            <div style="margin-bottom:8px;display:flex;align-items:center;gap:10px">
                <img src="<?=SITE_URL?>/assets/img/<?=$logo?>" style="height:44px;border:1px solid var(--border);border-radius:8px;padding:4px;background:#f9f9f9">
                <button type="submit" name="remove_logo" value="1" class="btn btn-sm" style="background:#ffebee;color:var(--primary);border:none;cursor:pointer"><i class="fas fa-trash"></i> Remove</button>
            </div>
            <?php endif; ?>
            <input type="file" name="site_logo" accept="image/*" style="font-size:13px">
        </div>
    </div>
</div>
<div class="settings-section">
    <h3><i class="fas fa-bullhorn" style="color:var(--primary)"></i> Announcement Bar</h3>
    <div class="settings-row">
        <div><label>Enable</label></div>
        <label class="toggle-switch"><input type="checkbox" name="announcement_bar_enabled" <?=isOn('announcement_bar_enabled','1')?'checked':''?>><span class="toggle-slider"></span></label>
    </div>
    <div class="settings-row">
        <div><label>Message</label></div>
        <input type="text" name="val_announcement_bar" value="<?=s('announcement_bar')?>" class="settings-input">
    </div>
</div>
<div class="settings-section">
    <h3><i class="fab fa-telegram-plane" style="color:#0088cc"></i> Telegram Float Button</h3>
    <div class="settings-row"><div><label>Show Button</label></div><label class="toggle-switch"><input type="checkbox" name="show_telegram_btn" <?=isOn('show_telegram_btn', isOn('show_whatsapp_btn','1') ? '1' : '0')?'checked':''?>><span class="toggle-slider"></span></label></div>
    <div class="settings-row"><div><label>Telegram URL</label><p>Example: https://t.me/yourusername or https://t.me/+invitecode</p></div><input type="url" name="val_telegram_float_url" value="<?=s('telegram_float_url', $settings['telegram_url'] ?? '')?>" class="settings-input" placeholder="https://t.me/yourusername"></div>
</div>
<div class="settings-section">
    <h3><i class="fas fa-shield-alt" style="color:var(--primary)"></i> Store Controls</h3>
    <div class="settings-row"><div><label>Maintenance Mode</label><p>Show maintenance page</p></div><label class="toggle-switch"><input type="checkbox" name="maintenance_mode" <?=isOn('maintenance_mode')?'checked':''?>><span class="toggle-slider"></span></label></div>
    <div class="settings-row"><div><label>Guest Checkout</label></div><label class="toggle-switch"><input type="checkbox" name="guest_checkout" <?=isOn('guest_checkout','1')?'checked':''?>><span class="toggle-slider"></span></label></div>
    <div class="settings-row"><div><label>Show Out of Stock Products</label></div><label class="toggle-switch"><input type="checkbox" name="show_out_of_stock" <?=isOn('show_out_of_stock','1')?'checked':''?>><span class="toggle-slider"></span></label></div>
    <div class="settings-row"><div><label>Product Reviews</label></div><label class="toggle-switch"><input type="checkbox" name="reviews_enabled" <?=isOn('reviews_enabled','1')?'checked':''?>><span class="toggle-slider"></span></label></div>
</div>
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save General Settings</button>
</form>

<?php elseif($activeTab==='homepage'): ?>
<form method="POST" enctype="multipart/form-data"><input type="hidden" name="group" value="homepage">
<div class="settings-section">
    <h3><i class="fas fa-image" style="color:var(--primary)"></i> Hero Banner</h3>
    <div class="settings-row"><div><label>Hero Title</label><p>HTML allowed. Use &lt;em&gt; for accent color</p></div><textarea name="val_hero_title" class="settings-input" rows="2"><?=htmlspecialchars($settings['hero_title']??'Premium Tea & Accessories<br><em>Delivered Fast</em>')?></textarea></div>
    <div class="settings-row"><div><label>Hero Subtitle</label></div><textarea name="val_hero_subtitle" class="settings-input" rows="2"><?=htmlspecialchars($settings['hero_subtitle']??'')?></textarea></div>
    <div class="settings-row"><div><label>Hero Badge Text</label></div><input type="text" name="val_hero_badge" value="<?=s('hero_badge')?>" class="settings-input" placeholder="🇰🇭 #1 TeaStore in Cambodia"></div>
    <div class="settings-row"><div><label>CTA Button 1</label></div><input type="text" name="val_hero_cta_text" value="<?=s('hero_cta_text','Shop for Dogs')?>" class="settings-input" placeholder="Shop for Dogs"></div>
    <div class="settings-row"><div><label>CTA Button 2</label></div><input type="text" name="val_hero_cta2_text" value="<?=s('hero_cta2_text','Shop for Cats')?>" class="settings-input" placeholder="Shop for Cats"></div>
    <div class="settings-row">
        <div><label>Background Type</label></div>
        <div style="display:flex;gap:14px;align-items:center">
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="radio" name="val_hero_bg_type" value="color" <?=s('hero_bg_type','color')==='color'?'checked':''?> onchange="toggleHeroBg(this.value)"> Solid Color</label>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="radio" name="val_hero_bg_type" value="image" <?=s('hero_bg_type','color')==='image'?'checked':''?> onchange="toggleHeroBg(this.value)"> Background Image</label>
        </div>
    </div>
    <div class="settings-row" id="heroBgColorRow" style="display:<?= s('hero_bg_type','color')==='color' ? 'flex' : 'none' ?>">
        <div><label>Background Color</label></div>
        <div class="color-picker-row">
            <input type="color" id="heroBgPicker" value="<?=$settings['hero_bg_color']??'#1a1a1a'?>" oninput="document.getElementById('heroBgInput').value=this.value">
            <input type="text" id="heroBgInput" name="val_hero_bg_color" value="<?=s('hero_bg_color','#1a1a1a')?>" class="settings-input" style="max-width:120px" oninput="document.getElementById('heroBgPicker').value=this.value">
        </div>
    </div>
    <div class="settings-row" id="heroBgImageRow" style="display:<?= s('hero_bg_type','color')==='image' ? 'flex' : 'none' ?>">
        <div><label>Background Image</label><p>JPG/PNG, min 1200×500px recommended</p></div>
        <div>
            <?php $hImg=s('hero_bg_image'); if($hImg): ?>
            <img src="<?=SITE_URL?>/assets/img/<?=$hImg?>" style="height:60px;border-radius:8px;object-fit:cover;width:120px;margin-bottom:8px;display:block">
            <?php endif; ?>
            <input type="file" name="hero_bg_image" accept="image/*" style="font-size:13px">
        </div>
    </div>
</div>
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Homepage Settings</button>
</form>
<script>
function toggleHeroBg(v){
    document.getElementById('heroBgColorRow').style.display=v==='color'?'flex':'none';
    document.getElementById('heroBgImageRow').style.display=v==='image'?'flex':'none';
}
</script>

<!-- Utility Strip Editor (separate form) -->
<div style="margin-top:20px">
<form method="POST" id="utilityStripForm" onsubmit="saveUtilityStrip(event)">
<input type="hidden" name="group" value="general">
<input type="hidden" name="action" value="save_utility_strip">
<div class="settings-section">
    <h3><i class="fas fa-grip-horizontal" style="color:var(--primary)"></i> Header Utility Strip Pills</h3>
    <p style="font-size:12px;color:#64748b;margin-bottom:14px">Edit the small pill labels shown below the main header on desktop.</p>
    <div id="utilityPillsList">
    <?php
    $utilityStrip = getSetting('utility_strip_items', '');
    $utilityItems = $utilityStrip ? json_decode($utilityStrip, true) : [];
    if (empty($utilityItems)) $utilityItems = [
        ['icon'=>'fa-bolt','text'=>'Same-day Phnom Penh prep'],
        ['icon'=>'fa-shield-halved','text'=>'Trusted pet brands'],
        ['icon'=>'fa-lock','text'=>'Stripe & PayPal Payments'],
    ];
    foreach ($utilityItems as $ui): ?>
    <div class="utility-pill-row" style="display:flex;gap:10px;align-items:center;margin-bottom:8px">
        <input type="text" class="upill-icon settings-input" value="<?= htmlspecialchars($ui['icon'] ?? 'fa-star') ?>" placeholder="fa-star" style="max-width:130px">
        <input type="text" class="upill-text settings-input" value="<?= htmlspecialchars($ui['text'] ?? '') ?>" placeholder="Pill label text" style="flex:1">
        <button type="button" class="btn btn-sm" style="background:#fff;border:1px solid #fecaca;color:#ef4444" onclick="this.closest('.utility-pill-row').remove()">🗑</button>
    </div>
    <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:10px;margin-top:10px">
        <button type="button" class="btn btn-ghost btn-sm" onclick="addUtilityPill()">➕ Add Pill</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="saveUtilityPills()">💾 Save Pills</button>
    </div>
    <p style="font-size:11px;color:#94a3b8;margin-top:8px">Icon: use FontAwesome class names like fa-truck, fa-star, fa-shield-halved</p>
</div>
</form>
</div>
<script>
function addUtilityPill(){
    const list=document.getElementById('utilityPillsList');
    const div=document.createElement('div');
    div.className='utility-pill-row';
    div.style.cssText='display:flex;gap:10px;align-items:center;margin-bottom:8px';
    div.innerHTML='<input type="text" class="upill-icon settings-input" value="fa-star" placeholder="fa-star" style="max-width:130px"><input type="text" class="upill-text settings-input" value="" placeholder="Pill label text" style="flex:1"><button type="button" class="btn btn-sm" style="background:#fff;border:1px solid #fecaca;color:#ef4444" onclick="this.closest(\'.utility-pill-row\').remove()">🗑</button>';
    list.appendChild(div);
}
async function saveUtilityPills(){
    const rows=document.querySelectorAll('.utility-pill-row');
    const items=[];
    rows.forEach(r=>{
        const icon=r.querySelector('.upill-icon')?.value||'fa-star';
        const text=r.querySelector('.upill-text')?.value||'';
        if(text) items.push({icon,text});
    });
    const fd=new FormData();
    fd.append('action','save_setting_direct');
    fd.append('key','utility_strip_items');
    fd.append('value',JSON.stringify(items));
    fd.append('group','general');
    // Fall back to a standard form submit instead
    const hidden=document.createElement('input'); hidden.type='hidden'; hidden.name='val_utility_strip_items'; hidden.value=JSON.stringify(items);
    const form=document.createElement('form'); form.method='POST'; form.action='<?= pretty_url('admin/settings.php') ?>?tab=homepage';
    const gi=document.createElement('input'); gi.type='hidden'; gi.name='group'; gi.value='general';
    form.appendChild(gi); form.appendChild(hidden); document.body.appendChild(form); form.submit();
}
</script>

<?php elseif($activeTab==='theme'): ?>
<form method="POST"><input type="hidden" name="group" value="theme">
<div class="settings-section">
    <h3><i class="fas fa-palette" style="color:var(--primary)"></i> Color Theme</h3>
    <div class="settings-row">
        <div><label>Primary Color</label><p>Buttons, links, accents</p></div>
        <div class="color-picker-row">
            <input type="color" id="primaryPicker" value="<?=$settings['theme_primary_color']??'#eb1700'?>" oninput="document.getElementById('primaryInput').value=this.value;previewColor(this.value)">
            <input type="text" id="primaryInput" name="val_theme_primary_color" value="<?=s('theme_primary_color','#eb1700')?>" class="settings-input" style="max-width:120px" oninput="document.getElementById('primaryPicker').value=this.value;previewColor(this.value)">
            <div id="colorPreviewBtn" style="width:90px;height:38px;border-radius:20px;background:<?=s('theme_primary_color','#eb1700')?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:700;transition:background .2s">Preview</div>
        </div>
    </div>
    <div class="settings-row">
        <div><label>Product Card Hover</label><p>Border &amp; shadow color on hover</p></div>
        <select name="val_card_hover_style" class="settings-input" style="max-width:200px">
            <?php foreach([
                ['primary','🔴 Primary Color (default)'],
                ['subtle','⬜ Subtle Gray'],
                ['shadow','🌑 Shadow Only'],
                ['none','✖️ No hover effect'],
            ] as [$v,$l]): ?>
            <option value="<?=$v?>" <?=s('card_hover_style','primary')===$v?'selected':''?>><?=$l?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="settings-row">
        <div><label>Product Hover Action</label><p>What shoppers see on product hover</p></div>
        <select name="val_product_hover_action" class="settings-input" style="max-width:220px">
            <?php foreach([
                ['both','Quick add + details'],
                ['quick_add','Quick add only'],
                ['view_details','View details only'],
                ['none','No hover buttons'],
            ] as [$v,$l]): ?>
            <option value="<?=$v?>" <?=s('product_hover_action','both')===$v?'selected':''?>><?=$l?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="settings-section">
    <h3><i class="fas fa-th" style="color:var(--primary)"></i> Product Grid Layout</h3>
    <div class="settings-row">
        <div><label>Homepage Columns</label><small>Products per row on homepage (2–6)</small></div>
        <input type="number" name="val_home_product_cols" class="settings-input" min="2" max="6" value="<?= (int)(s('home_product_cols','4')) ?>" style="max-width:100px">
    </div>
    <div class="settings-row">
        <div><label>Homepage Products per Section</label><small>Max products shown per section (1–20)</small></div>
        <input type="number" name="val_home_products_per_section" class="settings-input" min="1" max="20" value="<?= (int)(s('home_products_per_section','8')) ?>" style="max-width:100px">
    </div>
    <div class="settings-row">
        <div><label>Shop Page Columns</label><small>Products per row in shop (2–6)</small></div>
        <input type="number" name="val_shop_product_cols" class="settings-input" min="2" max="6" value="<?= (int)(s('shop_product_cols','4')) ?>" style="max-width:100px">
    </div>
    <div class="settings-row">
        <div><label>Shop Items Per Page</label><small>Products per page in shop (4–48)</small></div>
        <input type="number" name="val_shop_per_page" class="settings-input" min="4" max="48" step="4" value="<?= (int)(s('shop_per_page','8')) ?>" style="max-width:100px">
    </div>
</div>
<div class="settings-section">
    <h3><i class="fas fa-font" style="color:var(--primary)"></i> Typography</h3>
    <div class="settings-row">
        <div><label>Font Family</label></div>
        <select name="val_theme_font" class="settings-input" onchange="previewFont(this.value)">
            <?php foreach(['DM Sans','Inter','Poppins','Nunito','Outfit','Plus Jakarta'] as $f): ?>
            <option value="<?=$f?>" <?=($settings['theme_font']??'DM Sans')===$f?'selected':''?>><?=$f?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="settings-row">
        <div><label>Preview</label></div>
        <div id="fontPreview" style="padding:12px 16px;background:var(--bg);border-radius:8px;font-size:15px;border:1px solid var(--border);font-family:'<?=s('theme_font','DM Sans')?>', sans-serif">
            The quick brown fox — AaBb 0123
        </div>
    </div>
    <div class="settings-row">
        <div><label>Base Font Size</label><p>Body / paragraph text size (px)</p></div>
        <div style="display:flex;align-items:center;gap:8px">
            <input type="range" id="fsBase" name="val_font_size_base" min="12" max="20" step="1" value="<?=s('font_size_base','15')?>" oninput="document.getElementById('fsBaseVal').textContent=this.value+'px'" style="width:140px">
            <span id="fsBaseVal" style="font-size:13px;font-weight:700;color:#475569"><?=s('font_size_base','15')?>px</span>
        </div>
    </div>
    <div class="settings-row">
        <div><label>Heading H1 Size</label><p>Page main headings</p></div>
        <div style="display:flex;align-items:center;gap:8px">
            <input type="range" id="fsH1" name="val_font_size_h1" min="18" max="56" step="2" value="<?=s('font_size_h1','28')?>" oninput="document.getElementById('fsH1Val').textContent=this.value+'px'" style="width:140px">
            <span id="fsH1Val" style="font-size:13px;font-weight:700;color:#475569"><?=s('font_size_h1','28')?>px</span>
        </div>
    </div>
    <div class="settings-row">
        <div><label>Heading H2 Size</label><p>Section headings</p></div>
        <div style="display:flex;align-items:center;gap:8px">
            <input type="range" id="fsH2" name="val_font_size_h2" min="16" max="40" step="2" value="<?=s('font_size_h2','22')?>" oninput="document.getElementById('fsH2Val').textContent=this.value+'px'" style="width:140px">
            <span id="fsH2Val" style="font-size:13px;font-weight:700;color:#475569"><?=s('font_size_h2','22')?>px</span>
        </div>
    </div>
    <div class="settings-row">
        <div><label>Anchor / Link Size</label><p>Default link font size</p></div>
        <div style="display:flex;align-items:center;gap:8px">
            <input type="range" id="fsA" name="val_font_size_a" min="12" max="18" step="1" value="<?=s('font_size_a','14')?>" oninput="document.getElementById('fsAVal').textContent=this.value+'px'" style="width:140px">
            <span id="fsAVal" style="font-size:13px;font-weight:700;color:#475569"><?=s('font_size_a','14')?>px</span>
        </div>
    </div>
    <div class="settings-row">
        <div><label>Nav / Menu Size</label><p>Header navigation links</p></div>
        <div style="display:flex;align-items:center;gap:8px">
            <input type="range" id="fsNav" name="val_font_size_nav" min="11" max="18" step="1" value="<?=s('font_size_nav','14')?>" oninput="document.getElementById('fsNavVal').textContent=this.value+'px'" style="width:140px">
            <span id="fsNavVal" style="font-size:13px;font-weight:700;color:#475569"><?=s('font_size_nav','14')?>px</span>
        </div>
    </div>
</div>
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Theme Settings</button>
</form>

<?php elseif($activeTab==='shipping'): ?>
<form method="POST"><input type="hidden" name="group" value="shipping">
<div class="settings-section">
    <h3><i class="fas fa-truck" style="color:var(--primary)"></i> Delivery Fees</h3>
    <?php foreach([
        ['free_delivery_threshold','Free Delivery (Phnom Penh)','Minimum order for free delivery','49'],
        ['free_delivery_threshold_province','Free Delivery (Provinces)','Province minimum order','89'],
        ['delivery_fee','Flat Delivery Fee','Charged when below threshold','3.50'],
    ] as [$k,$l,$p,$d]): ?>
    <div class="settings-row">
        <div><label><?=$l?></label><p><?=$p?></p></div>
        <div style="display:flex;align-items:center;gap:8px"><span style="font-size:16px;font-weight:700">$</span><input type="number" name="val_<?=$k?>" value="<?=s($k,$d)?>" class="settings-input" style="max-width:100px" step="0.01" min="0"></div>
    </div>
    <?php endforeach; ?>
</div>
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Shipping Settings</button>
</form>

<?php elseif($activeTab==='payment'): ?>
<form method="POST"><input type="hidden" name="group" value="payment">
<div class="settings-section">
    <h3><i class="fas fa-toggle-on" style="color:var(--primary)"></i> Payment Methods</h3>
    <div class="settings-row"><div><label>💳 Stripe Payments</label></div><label class="toggle-switch"><input type="checkbox" name="stripe_enabled" <?=isOn('stripe_enabled','1')?'checked':''?>><span class="toggle-slider"></span></label></div>
    <div class="settings-row"><div><label>🅿️ PayPal Payments</label></div><label class="toggle-switch"><input type="checkbox" name="paypal_enabled" <?=isOn('paypal_enabled','1')?'checked':''?>><span class="toggle-slider"></span></label></div>
    <div class="settings-row"><div><label>💵 Cash on Delivery (COD)</label><p>Allow customers to pay cash when receiving their order</p></div><label class="toggle-switch"><input type="checkbox" name="cod_enabled" <?=isOn('cod_enabled', '0') ? 'checked' : '' ?>><span class="toggle-slider"></span></label></div>
    <h4 style="font-weight:700;margin:20px 0 10px;font-size:14px;">Stripe Configuration</h4>
    <div class="settings-row"><div><label>Stripe Publishable Key</label><p>pk_test_... or pk_live_...</p></div><input type="text" name="val_stripe_publishable_key" value="<?=s('stripe_publishable_key','')?>" class="settings-input" placeholder="pk_test_..."></div>
    <div class="settings-row"><div><label>Stripe Secret Key</label><p>sk_test_... or sk_live_... (keep secret!)</p></div><input type="password" name="val_stripe_secret_key" value="<?=s('stripe_secret_key','')?>" class="settings-input" placeholder="sk_test_..."></div>
    <h4 style="font-weight:700;margin:20px 0 10px;font-size:14px;">PayPal Configuration</h4>
    <div class="settings-row"><div><label>PayPal Mode</label></div><select name="val_paypal_mode" class="settings-input"><option value="sandbox" <?=s('paypal_mode','sandbox')==='sandbox'?'selected':''?>>Sandbox (Test)</option><option value="live" <?=s('paypal_mode','sandbox')==='live'?'selected':''?>>Live</option></select></div>
    <div class="settings-row"><div><label>PayPal Client ID</label><p>From PayPal Developer Dashboard</p></div><input type="text" name="val_paypal_client_id" value="<?=s('paypal_client_id','')?>" class="settings-input" placeholder="AaBbCc..."></div>
    <div class="settings-row"><div><label>PayPal Secret</label><p>Keep this secret!</p></div><input type="password" name="val_paypal_secret" value="<?=s('paypal_secret','')?>" class="settings-input" placeholder="Secret..."></div>
    <div class="settings-row"><div><label>Currency Code</label><p>ISO code e.g. USD, EUR, GBP</p></div><input type="text" name="val_currency_code" value="<?=s('currency_code','USD')?>" class="settings-input" style="max-width:100px" maxlength="3"></div>
</div>
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Payment Settings</button>
</form>

<?php elseif($activeTab==='notifications'): ?>
<div style="background:linear-gradient(135deg,#0088cc,#006faa);border-radius:var(--radius);padding:20px;margin-bottom:24px;color:#fff">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
        <i class="fab fa-telegram" style="font-size:28px"></i>
        <div><div style="font-size:16px;font-weight:700">Telegram Bot Notifications</div><div style="font-size:12px;opacity:.8">Get instant order alerts in your Telegram group</div></div>
    </div>
</div>

<!-- Setup Guide -->
<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:var(--radius);padding:20px;margin-bottom:24px">
    <h4 style="font-weight:700;color:#15803d;margin-bottom:12px;font-size:14px"><i class="fas fa-list-ol"></i> How to set up your Telegram Bot</h4>
    <ol style="font-size:13px;color:#166534;line-height:2;padding-left:20px">
        <li>Open Telegram → search for <strong>@BotFather</strong></li>
        <li>Send <code style="background:#dcfce7;padding:2px 6px;border-radius:4px">/newbot</code> → follow prompts</li>
        <li>BotFather gives you a <strong>Bot Token</strong> like <code style="background:#dcfce7;padding:2px 6px;border-radius:4px">7890123456:AAHv-...</code></li>
        <li>Create or open your Telegram group/channel</li>
        <li>Add your bot as a member to the group</li>
        <li>Get Chat ID: send any message in the group, then visit<br>
            <code style="background:#dcfce7;padding:2px 6px;border-radius:4px">https://api.telegram.org/bot{YOUR_TOKEN}/getUpdates</code><br>
            Look for <code>"chat":{"id":</code> — that's your Chat ID (may be negative like -123456789)
        </li>
        <li>Paste Bot Token and Chat ID below → click Save → click Test</li>
    </ol>
</div>

<form method="POST"><input type="hidden" name="group" value="notifications">
<div class="settings-section">
    <h3><i class="fas fa-robot" style="color:#0088cc"></i> Bot Configuration</h3>
    <div class="settings-row">
        <div><label>Bot Token</label><p>From @BotFather</p></div>
        <input type="text" name="val_telegram_bot_token" value="<?=s('telegram_bot_token')?>" class="settings-input" placeholder="7890123456:AAHv_abc123...">
    </div>
    <div class="settings-row">
        <div><label>Chat ID</label><p>Group or channel ID</p></div>
        <input type="text" name="val_telegram_chat_id" value="<?=s('telegram_chat_id')?>" class="settings-input" placeholder="-1001234567890">
    </div>
</div>
<div class="settings-section">
    <h3><i class="fas fa-bell" style="color:#0088cc"></i> Notification Triggers</h3>
    <div class="settings-row"><div><label>New Order Placed</label></div><label class="toggle-switch"><input type="checkbox" name="telegram_notify_orders" <?=isOn('telegram_notify_orders','1')?'checked':''?>><span class="toggle-slider"></span></label></div>
    <div class="settings-row"><div><label>Low Stock Alert</label></div><label class="toggle-switch"><input type="checkbox" name="telegram_notify_lowstock" <?=isOn('telegram_notify_lowstock','1')?'checked':''?>><span class="toggle-slider"></span></label></div>
</div>
<div style="display:flex;gap:12px;flex-wrap:wrap">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Notification Settings</button>
    <a href="?tab=notifications&action=test_telegram" class="btn" style="background:#0088cc;color:#fff"><i class="fab fa-telegram"></i> Test Connection</a>
</div>
</form>

<?php elseif($activeTab==='social'): ?>
<form method="POST"><input type="hidden" name="group" value="social">
<div class="settings-section">
    <h3><i class="fas fa-share-alt" style="color:var(--primary)"></i> Social Media Links</h3>
    <?php foreach([
        ['facebook_url','<i class="fab fa-facebook" style="color:#1877f2"></i> Facebook','https://facebook.com/yourpage'],
        ['instagram_url','<i class="fab fa-instagram" style="color:#e1306c"></i> Instagram','https://instagram.com/yourpage'],
        ['telegram_url','<i class="fab fa-telegram" style="color:#0088cc"></i> Telegram Channel','https://t.me/yourchannel'],
        ['tiktok_url','<i class="fab fa-tiktok"></i> TikTok','https://tiktok.com/@yourpage'],
        ['youtube_url','<i class="fab fa-youtube" style="color:#ff0000"></i> YouTube','https://youtube.com/@yourchannel'],
    ] as [$k,$l,$ph]): ?>
    <div class="settings-row"><div><label><?=$l?></label></div><input type="url" name="val_<?=$k?>" value="<?=s($k)?>" class="settings-input" placeholder="<?=$ph?>" autocomplete="off"></div>
    <?php endforeach; ?>
</div>
<div class="settings-section">
    <h3><i class="fas fa-shoe-prints" style="color:var(--primary)"></i> Footer Customization</h3>
    <div class="settings-row">
        <div><label>Footer Description</label><p>Short text under logo in footer</p></div>
        <textarea name="val_footer_description" class="settings-input" rows="2" placeholder="TeaStore – Your trusted online tea supplies store..."><?=s('footer_description',"TeaStore – Your trusted online tea supplies store. Premium products delivered fast to your door.")?></textarea>
    </div>
    <div class="settings-row">
        <div><label>Footer Copyright Text</label></div>
        <input type="text" name="val_footer_copyright" value="<?=s('footer_copyright','')?>" class="settings-input" placeholder="© 2025 TeaStore. All rights reserved.">
    </div>
    <div class="settings-row">
        <div><label>Footer Background Color</label></div>
        <div style="display:flex;gap:8px;align-items:center">
            <input type="color" id="footerBgPicker" value="<?=s('footer_bg_color','#111')?>" oninput="document.getElementById('footerBgInput').value=this.value" style="width:36px;height:36px;border:none;cursor:pointer;border-radius:6px">
            <input type="text" id="footerBgInput" name="val_footer_bg_color" value="<?=s('footer_bg_color','#111')?>" class="settings-input" style="max-width:130px" oninput="document.getElementById('footerBgPicker').value=this.value">
        </div>
    </div>
    <div class="settings-row">
        <div><label>Footer Text Color</label></div>
        <div style="display:flex;gap:8px;align-items:center">
            <input type="color" id="footerTxtPicker" value="<?=s('footer_text_color','#ffffff')?>" oninput="document.getElementById('footerTxtInput').value=this.value" style="width:36px;height:36px;border:none;cursor:pointer;border-radius:6px">
            <input type="text" id="footerTxtInput" name="val_footer_text_color" value="<?=s('footer_text_color','#ffffff')?>" class="settings-input" style="max-width:130px" oninput="document.getElementById('footerTxtPicker').value=this.value">
        </div>
    </div>
    <div class="settings-row">
        <div><label>Show Footer Social Icons</label></div>
        <label class="toggle-switch"><input type="checkbox" name="val_footer_show_social" value="1" <?=s('footer_show_social','1')==='1'?'checked':''?>><span class="toggle-slider"></span></label>
    </div>
</div>
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Social & Footer Settings</button>
</form>

<?php elseif($activeTab==='tools'): ?>
<?php
$stats=['products'=>0,'orders'=>0,'users'=>0,'revenue'=>0];
try {
    $stats['products']=$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $stats['orders']=$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $stats['users']=$pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
    $stats['revenue']=$pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='cancelled'")->fetchColumn();
} catch(\Exception $e){}
?>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:28px">
    <?php foreach([['📦','Products',$stats['products']],['🛒','Orders',$stats['orders']],['👥','Customers',$stats['users']],['💰','Revenue','$'.number_format($stats['revenue'],2)]] as [$e,$l,$v]): ?>
    <div style="background:var(--bg);border-radius:var(--radius);padding:16px;text-align:center;border:1px solid var(--border)"><div style="font-size:24px;margin-bottom:4px"><?=$e?></div><div style="font-size:20px;font-weight:800"><?=$v?></div><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px"><?=$l?></div></div>
    <?php endforeach; ?>
</div>
<div class="settings-section">
    <h3><i class="fas fa-database" style="color:var(--primary)"></i> Database Backup</h3>
    <a href="?tab=tools&action=backup" class="btn btn-dark"><i class="fas fa-download"></i> Download SQL Backup</a>
</div>
<div class="settings-section">
    <h3><i class="fas fa-file-csv" style="color:var(--primary)"></i> Export Data</h3>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php foreach(['orders','users','products'] as $t): ?>
        <a href="?tab=tools&action=export_<?=$t?>" class="btn btn-ghost btn-sm"><i class="fas fa-table"></i> <?=ucfirst($t)?> CSV</a>
        <?php endforeach; ?>
    </div>
</div>
<div class="settings-section">
    <h3><i class="fas fa-info-circle" style="color:var(--primary)"></i> System Info</h3>
    <table style="width:100%;font-size:13px;border-collapse:collapse">
        <?php foreach([['PHP',PHP_VERSION],['Server',$_SERVER['SERVER_SOFTWARE']??'Unknown'],['Max Upload',ini_get('upload_max_filesize')],['Time',date('Y-m-d H:i:s')]] as [$k,$v]): ?>
        <tr style="border-bottom:1px solid var(--border)"><td style="padding:9px 0;color:var(--text-muted);width:140px"><?=$k?></td><td style="padding:9px 0;font-weight:500;font-family:monospace"><?=htmlspecialchars($v)?></td></tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

</div><!-- content -->
</div><!-- grid -->

<style>
.settings-section{margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid var(--border);}
.settings-section:last-child{border:none;margin-bottom:0;}
.settings-section h3{font-size:14px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px;}
.settings-row{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:11px 0;border-bottom:1px solid var(--bg);}
.settings-row:last-child{border-bottom:none;}
.settings-row>div:first-child{min-width:180px;}
.settings-row label{font-size:13px;font-weight:600;display:block;margin-bottom:2px;}
.settings-row p{font-size:12px;color:var(--text-muted);margin:0;}
.settings-input{border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;font-family:inherit;outline:none;background:var(--white);color:var(--text);min-width:220px;width:auto;transition:border-color .15s;}
.settings-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-alpha);}
textarea.settings-input{resize:vertical;min-width:280px;}
.color-picker-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.color-picker-row input[type=color]{width:44px;height:44px;border:none;border-radius:8px;cursor:pointer;padding:2px;}
.toggle-switch{position:relative;display:inline-block;width:46px;height:26px;flex-shrink:0;}
.toggle-switch input{opacity:0;width:0;height:0;position:absolute;}
.toggle-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;border-radius:26px;transition:.2s;}
.toggle-slider:before{position:absolute;content:"";height:20px;width:20px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s;}
.toggle-switch input:checked+.toggle-slider{background:var(--primary);}
.toggle-switch input:checked+.toggle-slider:before{transform:translateX(20px);}
</style>
<script>
function previewColor(c){document.getElementById('colorPreviewBtn').style.background=c;}
function updateDarkLabel(){document.getElementById('darkModeLabel').textContent=document.getElementById('darkModeToggle').checked?'🌙 Dark Mode':'☀️ Light Mode';}
function previewFont(name){
    const link=document.createElement('link');link.rel='stylesheet';
    link.href='https://fonts.googleapis.com/css2?family='+encodeURIComponent(name+':wght@400;700')+'&display=swap';
    document.head.appendChild(link);
    const p=document.getElementById('fontPreview');
    if(p){p.style.fontFamily="'"+name+"',sans-serif";p.textContent=name+': The quick brown fox — AaBb 0123';}
}
</script>
<?php require_once __DIR__ . '/admin-footer.php'; ?>
