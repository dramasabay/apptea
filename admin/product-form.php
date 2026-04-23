<?php
$pageTitle = "Product Form – TeaStore Admin";
require_once __DIR__ . '/../includes/config.php';
if (!canAccessAdmin()) { flash('error','Admin access required'); redirect(pretty_url('pages/login.php')); }

$id = (int)($_GET['id'] ?? 0);
$product = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
}

// Ensure product_images table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// Ensure product_quantity_discounts table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_quantity_discounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        min_qty INT NOT NULL DEFAULT 5,
        discount_pct DECIMAL(5,2) NOT NULL DEFAULT 5.00,
        sort_order INT DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// Handle discount tier save (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_discount_tiers') {
    $pid  = (int)($_POST['product_id'] ?? 0);
    $tiers = $_POST['tiers'] ?? [];
    if ($pid) {
        $pdo->prepare("DELETE FROM product_quantity_discounts WHERE product_id=?")->execute([$pid]);
        $sort = 0;
        foreach ($tiers as $tier) {
            $minQty  = (int)($tier['min_qty'] ?? 0);
            $pct     = (float)($tier['discount_pct'] ?? 0);
            if ($minQty > 0 && $pct > 0 && $pct <= 100) {
                $pdo->prepare("INSERT INTO product_quantity_discounts (product_id,min_qty,discount_pct,sort_order) VALUES (?,?,?,?)")
                    ->execute([$pid, $minQty, $pct, $sort++]);
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true]); exit;
}

// Handle gallery image delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_gallery_image') {
    $imgId = (int)($_POST['img_id'] ?? 0);
    $pid   = (int)($_POST['product_id'] ?? 0);
    if ($imgId && $pid) {
        $row = $pdo->prepare("SELECT filename FROM product_images WHERE id=? AND product_id=?");
        $row->execute([$imgId, $pid]);
        $row = $row->fetch();
        if ($row) {
            @unlink(__DIR__ . '/../assets/img/products/' . $row['filename']);
            $pdo->prepare("DELETE FROM product_images WHERE id=?")->execute([$imgId]);
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]); exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$brands     = $pdo->query("SELECT * FROM brands ORDER BY name")->fetchAll();

// Load gallery images for edit
$galleryImages = [];
if ($id) {
    $gStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id=? ORDER BY sort_order,id");
    $gStmt->execute([$id]);
    $galleryImages = $gStmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'delete_gallery_image') {
    $name      = sanitize($_POST['name'] ?? '');
    $desc      = $_POST['description'] ?? '';
    $shortDesc = $_POST['short_desc'] ?? '';
    $price     = (float)($_POST['price'] ?? 0);
    $salePrice = ($_POST['sale_price'] !== '' && $_POST['sale_price'] !== null) ? (float)$_POST['sale_price'] : null;
    $stock     = (int)($_POST['stock'] ?? 0);
    $catId     = (int)($_POST['category_id'] ?? 0) ?: null;
    $brandId   = (int)($_POST['brand_id'] ?? 0) ?: null;
    $petType   = sanitize($_POST['tea_type'] ?? 'both');
    $featured  = isset($_POST['featured']) ? 1 : 0;
    $isNew     = isset($_POST['is_new']) ? 1 : 0;
    $image     = $product['image'] ?? '';
    $slug      = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));

    $uploadDir = __DIR__ . '/../assets/img/products';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

    // Main image upload
    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $filename = 'p_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . '/' . $filename)) {
                $image = $filename;
            }
        }
    }

    if (!$name || !$price) {
        flash('error', 'Name and price are required');
    } else {
        if ($id) {
            $pdo->prepare("UPDATE products SET name=?,slug=?,description=?,short_desc=?,price=?,sale_price=?,stock=?,category_id=?,brand_id=?,tea_type=?,featured=?,is_new=?,image=? WHERE id=?")
               ->execute([$name, $slug, $desc, $shortDesc, $price, $salePrice, $stock, $catId, $brandId, $petType, $featured, $isNew, $image, $id]);
        } else {
            $baseSlug = $slug; $cnt = 0;
            while ($pdo->prepare("SELECT id FROM products WHERE slug=?")->execute([$slug]) && $pdo->query("SELECT id FROM products WHERE slug='$slug'")->fetch()) {
                $slug = $baseSlug . '-' . (++$cnt);
            }
            $pdo->prepare("INSERT INTO products (name,slug,description,short_desc,price,sale_price,stock,category_id,brand_id,tea_type,featured,is_new,image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$name, $slug, $desc, $shortDesc, $price, $salePrice, $stock, $catId, $brandId, $petType, $featured, $isNew, $image]);
            $id = (int)$pdo->lastInsertId();
        }

        // Gallery images (multiple file upload)
        if (!empty($_FILES['gallery_images']['name'][0])) {
            $sortStart = count($galleryImages);
            foreach ($_FILES['gallery_images']['tmp_name'] as $k => $tmp) {
                if (!$tmp || $_FILES['gallery_images']['error'][$k] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($_FILES['gallery_images']['name'][$k], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;
                $fn = 'g_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($tmp, $uploadDir . '/' . $fn)) {
                    $pdo->prepare("INSERT INTO product_images (product_id,filename,sort_order) VALUES (?,?,?)")
                        ->execute([$id, $fn, $sortStart++]);
                }
            }
        }

        flash('success', $id ? 'Product updated!' : 'Product created!');
        redirect(pretty_url('admin/products.php'));
    }
}
require_once __DIR__ . '/admin-header.php';

// Reload gallery after save attempt
if ($id) {
    $gStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id=? ORDER BY sort_order,id");
    $gStmt->execute([$id]);
    $galleryImages = $gStmt->fetchAll();
}

// Load discount tiers
$discountTiers = [];
if ($id) {
    try {
        $dtStmt = $pdo->prepare("SELECT * FROM product_quantity_discounts WHERE product_id=? ORDER BY min_qty");
        $dtStmt->execute([$id]);
        $discountTiers = $dtStmt->fetchAll();
    } catch (Throwable $e) {}
}
?>
<style>
.pf-hero{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px}
.pf-grid{display:grid;grid-template-columns:1.3fr .7fr;gap:24px;align-items:start}
.pf-card{background:#fff;border:1.5px solid #e8edf3;border-radius:20px;padding:24px;box-shadow:0 4px 20px rgba(15,23,42,.05);margin-bottom:0}
.pf-section{margin-bottom:22px;padding-bottom:22px;border-bottom:1.5px solid #f1f5f9}
.pf-section:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0}
.pf-section-title{font-size:12px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;margin:0 0 14px}
.pf-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.pf-group{margin-bottom:14px}
.pf-group:last-child{margin-bottom:0}
.pf-label{display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.pf-inp{width:100%;border:1.5px solid #e2e8f0;border-radius:10px;padding:10px 13px;font-size:14px;outline:none;font-family:inherit;color:#0f172a;background:#fff;transition:.15s;box-sizing:border-box}
.pf-inp:focus{border-color:#1e293b;box-shadow:0 0 0 3px rgba(30,41,59,.07)}
.pf-sel{width:100%;border:1.5px solid #e2e8f0;border-radius:10px;padding:10px 13px;font-size:14px;outline:none;font-family:inherit;color:#0f172a;background:#fff;transition:.15s}
.pf-textarea{width:100%;border:1.5px solid #e2e8f0;border-radius:10px;padding:10px 13px;font-size:14px;outline:none;font-family:inherit;color:#0f172a;background:#fff;resize:vertical;transition:.15s;box-sizing:border-box}
.pf-textarea:focus{border-color:#1e293b}
.pf-check-row{display:flex;gap:18px;flex-wrap:wrap}
.pf-check-label{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;color:#374151;cursor:pointer;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:10px;background:#fff;transition:.15s}
.pf-check-label:has(input:checked){border-color:#1e293b;background:#f8fafc}
.upload-zone{border:2px dashed #d1d5db;border-radius:14px;padding:20px;text-align:center;background:#fafbfc;transition:.15s;cursor:pointer}
.upload-zone:hover{border-color:#94a3b8;background:#f1f5f9}
/* Gallery grid */
.gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(88px,1fr));gap:10px;margin-bottom:12px}
.gallery-thumb{position:relative;aspect-ratio:1;border-radius:12px;overflow:hidden;border:2px solid #e2e8f0;background:#f8fafc}
.gallery-thumb img{width:100%;height:100%;object-fit:cover}
.gallery-thumb .del-btn{position:absolute;top:4px;right:4px;width:22px;height:22px;background:rgba(0,0,0,.65);border:none;border-radius:50%;color:#fff;font-size:11px;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1}
.gallery-thumb .del-btn:hover{background:#ef4444}
.main-img-preview{width:100%;aspect-ratio:1;object-fit:cover;border-radius:12px;border:2px solid #e2e8f0;display:block;margin-bottom:10px}
.pf-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:10px;border:none;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s}
.pf-btn-primary{background:linear-gradient(135deg,#eb1700,#ff4a26);color:#fff;box-shadow:0 4px 14px rgba(235,23,0,.18)}.pf-btn-primary:hover{opacity:.9}
.pf-btn-ghost{background:#fff;color:#374151;border:1.5px solid #e2e8f0}.pf-btn-ghost:hover{background:#f8fafc}
.sidebar-tip{background:#f8fafc;border:1.5px solid #e8edf3;border-radius:14px;padding:16px;margin-bottom:16px}
.sidebar-tip h4{font-size:13px;font-weight:800;color:#1e293b;margin:0 0 6px}
.sidebar-tip p{font-size:12px;color:#64748b;line-height:1.6;margin:0}
@media(max-width:900px){.pf-grid{grid-template-columns:1fr}.pf-row{grid-template-columns:1fr}}
</style>

<div class="pf-hero">
  <div>
    <div style="font-size:24px;font-weight:900;letter-spacing:-.5px;color:#0f172a"><?= $id ? '✏️ Edit Product' : '➕ Add Product' ?></div>
    <div style="font-size:13px;color:#64748b;margin-top:4px">Fill in product details, upload images, and configure options after saving.</div>
  </div>
  <a href="<?= pretty_url('admin/products.php') ?>" class="pf-btn pf-btn-ghost">← Back to Products</a>
</div>

<form method="POST" enctype="multipart/form-data" id="productForm">
<div class="pf-grid">

  <!-- LEFT: Main fields -->
  <div class="pf-card">

    <!-- Basic info -->
    <div class="pf-section">
      <div class="pf-section-title">Basic Information</div>
      <div class="pf-group">
        <label class="pf-label">Product Name *</label>
        <input type="text" name="name" class="pf-inp" required value="<?= htmlspecialchars($product['name'] ?? '') ?>" placeholder="e.g. Royal Canin Mini Adult 2kg">
      </div>
      <div class="pf-row">
        <div class="pf-group">
          <label class="pf-label">Price ($) *</label>
          <input type="number" name="price" class="pf-inp" step="0.01" min="0" required value="<?= $product['price'] ?? '' ?>" placeholder="0.00">
        </div>
        <div class="pf-group">
          <label class="pf-label">Sale Price ($) <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
          <input type="number" name="sale_price" class="pf-inp" step="0.01" min="0" value="<?= $product['sale_price'] ?? '' ?>" placeholder="Leave empty for no sale">
        </div>
      </div>
      <div class="pf-row">
        <div class="pf-group">
          <label class="pf-label">Stock Qty</label>
          <input type="number" name="stock" class="pf-inp" min="0" value="<?= $product['stock'] ?? 0 ?>">
        </div>
        <div class="pf-group">
          <label class="pf-label">Tea Type</label>
          <select name="tea_type" class="pf-sel">
            <option value="green" <?= ($product['tea_type']??'')  === 'green'  ? 'selected' : '' ?>>🍃 Green</option>
            <option value="black" <?= ($product['tea_type']??'')  === 'black'  ? 'selected' : '' ?>>🫖 Black</option>
            <option value="all" <?= ($product['tea_type']??'both') === 'both' ? 'selected' : '' ?>>🍵 Both</option>
          </select>
        </div>
      </div>
      <div class="pf-row">
        <div class="pf-group">
          <label class="pf-label">Category</label>
          <select name="category_id" class="pf-sel">
            <option value="">— Select category —</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($product['category_id']??'') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="pf-group">
          <label class="pf-label">Brand</label>
          <select name="brand_id" class="pf-sel">
            <option value="">— Select brand —</option>
            <?php foreach ($brands as $b): ?>
            <option value="<?= $b['id'] ?>" <?= ($product['brand_id']??'') == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- Descriptions -->
    <div class="pf-section">
      <div class="pf-section-title">Descriptions</div>
      <div class="pf-group">
        <label class="pf-label">Short Description <span style="font-weight:400;text-transform:none;letter-spacing:0">(shown on card & product header)</span></label>
        <textarea name="short_desc" class="pf-textarea" rows="2" placeholder="Brief summary shown on product cards..."><?= htmlspecialchars($product['short_desc'] ?? '') ?></textarea>
      </div>
      <div class="pf-group">
        <label class="pf-label">Full Description</label>
        <textarea name="description" class="pf-textarea" rows="5" placeholder="Detailed product description, ingredients, usage..."><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- Images -->
    <div class="pf-section">
      <div class="pf-section-title">Product Images</div>

      <!-- Main image -->
      <div class="pf-group">
        <label class="pf-label">Main Image (Cover)</label>
        <?php if (!empty($product['image'])): ?>
        <img src="<?= SITE_URL ?>/assets/img/products/<?= htmlspecialchars($product['image']) ?>" class="main-img-preview" alt="Main image">
        <?php endif; ?>
        <div class="upload-zone" onclick="document.getElementById('mainImageInput').click()">
          <input type="file" id="mainImageInput" name="image" accept="image/*" style="display:none" onchange="previewMainImage(this)">
          <div style="font-size:28px;margin-bottom:6px">🖼️</div>
          <div style="font-size:13px;font-weight:700;color:#374151">Click to upload main image</div>
          <div style="font-size:11px;color:#94a3b8;margin-top:3px">JPG, PNG or WebP · Recommended 600×600px</div>
        </div>
        <div id="mainImgPreviewWrap" style="margin-top:8px;display:none">
          <img id="mainImgPreview" style="width:80px;height:80px;object-fit:cover;border-radius:10px;border:2px solid #e2e8f0" alt="">
          <span style="font-size:12px;color:#64748b;margin-left:8px">New image selected</span>
        </div>
      </div>

      <!-- Gallery images -->
      <div class="pf-group">
        <label class="pf-label">Gallery Images <span style="font-weight:400;text-transform:none;letter-spacing:0">(shown as album on product page)</span></label>
        <?php if (!empty($galleryImages)): ?>
        <div class="gallery-grid" id="galleryGrid">
          <?php foreach ($galleryImages as $gi): ?>
          <div class="gallery-thumb" id="gimg_<?= (int)$gi['id'] ?>">
            <img src="<?= SITE_URL ?>/assets/img/products/<?= htmlspecialchars($gi['filename']) ?>" alt="">
            <button type="button" class="del-btn" onclick="deleteGalleryImage(<?= (int)$gi['id'] ?>,<?= $id ?>)" title="Remove">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="upload-zone" id="galleryDropZone" onclick="document.getElementById('galleryInput').click()"
             ondragover="event.preventDefault();this.style.borderColor='var(--primary)'"
             ondragleave="this.style.borderColor=''"
             ondrop="event.preventDefault();this.style.borderColor='';handleGalleryDrop(event.dataTransfer.files)">
          <input type="file" id="galleryInput" name="gallery_images[]" accept="image/*" multiple style="display:none" onchange="previewGallery(this.files)">
          <div style="font-size:28px;margin-bottom:6px">📸</div>
          <div style="font-size:13px;font-weight:700;color:#374151">Click or drag &amp; drop images here</div>
          <div style="font-size:11px;color:#94a3b8;margin-top:3px">Select 1 or more files at once · JPG, PNG or WebP</div>
        </div>
        <div id="galleryPreviewNew" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px"></div>
        <div id="galleryFileCount" style="font-size:12px;color:#64748b;margin-top:4px"></div>
      </div>
    </div>
    <script>
    function previewGallery(files) {
        const prev = document.getElementById('galleryPreviewNew');
        const cnt  = document.getElementById('galleryFileCount');
        prev.innerHTML = '';
        if (!files || !files.length) { cnt.textContent = ''; return; }
        cnt.textContent = files.length + ' file(s) selected and ready to upload';
        Array.from(files).forEach(f => {
            const r = new FileReader();
            r.onload = e => {
                const d = document.createElement('div');
                d.style.cssText = 'position:relative;width:72px;height:72px;border-radius:10px;overflow:hidden;border:1.5px solid #e2e8f0';
                d.innerHTML = '<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:cover"><div style="position:absolute;inset:0;background:rgba(0,0,0,.3);display:flex;align-items:flex-end;justify-content:center;padding-bottom:3px"><span style="font-size:9px;color:#fff;font-weight:700;text-shadow:0 1px 3px rgba(0,0,0,.8)">NEW</span></div>';
                prev.appendChild(d);
            };
            r.readAsDataURL(f);
        });
    }
    function handleGalleryDrop(files) {
        const dt = new DataTransfer();
        Array.from(files).forEach(f => dt.items.add(f));
        document.getElementById('galleryInput').files = dt.files;
        previewGallery(files);
    }
    </script>

    <!-- Flags -->
    <div class="pf-section">
      <div class="pf-section-title">Labels</div>
      <div class="pf-check-row">
        <label class="pf-check-label">
          <input type="checkbox" name="featured" value="1" <?= ($product['featured']??0) ? 'checked' : '' ?>>
          ⭐ Featured Product
        </label>
        <label class="pf-check-label">
          <input type="checkbox" name="is_new" value="1" <?= ($product['is_new']??1) ? 'checked' : '' ?>>
          ✨ Mark as New
        </label>
      </div>
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap;padding-top:4px">
      <button type="submit" class="pf-btn pf-btn-primary" style="font-size:14px;padding:12px 24px">
        <?= $id ? '✓ Update Product' : '+ Create Product' ?>
      </button>
      <a href="<?= pretty_url('admin/products.php') ?>" class="pf-btn pf-btn-ghost" style="font-size:14px;padding:12px 24px">Cancel</a>
    </div>
  </div>

  <!-- RIGHT: Sidebar tips -->
  <div>
    <?php if ($id): ?>
    <div class="sidebar-tip" style="background:#fef9f0;border-color:#fde68a">
      <h4>🎛 Product Options</h4>
      <p>Add size, weight, flavor or any custom option groups after saving. Customers choose options before adding to cart.</p>
      <a href="<?= SITE_URL ?>/admin/product-options.php?product_id=<?= $id ?>" class="pf-btn pf-btn-ghost" style="margin-top:10px;width:100%;justify-content:center;font-size:12px">
        ⚙️ Manage Options
      </a>
    </div>

    <!-- ── Quantity Discount Tiers ── -->
    <div class="pf-card" style="margin-top:0;padding:20px;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <div>
          <div style="font-size:13px;font-weight:800;color:#0f172a;">🔥 Buy More, Save More</div>
          <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Quantity discount tiers (optional)</div>
        </div>
        <button type="button" onclick="addDiscountTier()" style="background:var(--primary,#2d6a4f);color:#fff;border:none;border-radius:9px;padding:6px 12px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;">+ Add Tier</button>
      </div>
      <div id="discountTierRows" style="display:flex;flex-direction:column;gap:8px;">
        <?php foreach ($discountTiers as $dt): ?>
        <div class="dt-row" style="display:grid;grid-template-columns:1fr 1fr 28px;gap:6px;align-items:center;">
          <div>
            <label style="font-size:10px;color:#94a3b8;font-weight:700;display:block;margin-bottom:2px;">Min Qty</label>
            <input type="number" class="pf-inp dt-min-qty" value="<?= (int)$dt['min_qty'] ?>" min="2" style="padding:7px 10px;font-size:13px;">
          </div>
          <div>
            <label style="font-size:10px;color:#94a3b8;font-weight:700;display:block;margin-bottom:2px;">Discount %</label>
            <input type="number" class="pf-inp dt-pct" value="<?= (float)$dt['discount_pct'] ?>" min="1" max="90" step="0.5" style="padding:7px 10px;font-size:13px;">
          </div>
          <button type="button" onclick="this.closest('.dt-row').remove()" style="margin-top:16px;background:#fff1f2;border:1px solid #fecdd3;color:#ef4444;border-radius:8px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;flex-shrink:0;">×</button>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if (empty($discountTiers)): ?>
      <div id="dtEmpty" style="font-size:12px;color:#94a3b8;text-align:center;padding:12px 0;">No tiers yet. Click "+ Add Tier" to start.</div>
      <?php endif; ?>
      <button type="button" onclick="saveDiscountTiers()" style="margin-top:12px;width:100%;background:#f0fdf4;border:1.5px solid #86efac;color:#166534;border-radius:10px;padding:9px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s;">💾 Save Discount Tiers</button>
      <div id="dtSaveMsg" style="font-size:11px;text-align:center;margin-top:6px;color:#16a34a;display:none;">✓ Saved!</div>
    </div>
    <?php endif; ?>
    <div class="sidebar-tip">
      <h4>📸 Image Tips</h4>
      <p>Main image: 600×600px square, clean background. Gallery: add multiple angles, packaging shots, or usage photos — these appear as an album on the product page.</p>
    </div>
    <div class="sidebar-tip">
      <h4>✍️ Writing Tips</h4>
      <p>Short description: 1–2 sentences shown on product cards.<br>Full description: ingredients, feeding guide, benefits.</p>
    </div>
    <div class="sidebar-tip">
      <h4>💡 Workflow</h4>
      <p>1. Save the product first<br>2. Add gallery images<br>3. Configure options (size, weight etc.)</p>
    </div>
  </div>

</div>
</form>

<script>
function previewMainImage(input) {
    const wrap = document.getElementById('mainImgPreviewWrap');
    const img  = document.getElementById('mainImgPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; wrap.style.display = 'flex'; wrap.style.alignItems = 'center'; };
        reader.readAsDataURL(input.files[0]);
    }
}
function previewGallery(input) {
    const container = document.getElementById('galleryPreviewNew');
    container.innerHTML = '';
    if (!input.files) return;
    Array.from(input.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.style.cssText = 'position:relative;width:72px;height:72px';
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = 'width:72px;height:72px;object-fit:cover;border-radius:10px;border:2px solid #e2e8f0';
            div.appendChild(img);
            container.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}
async function deleteGalleryImage(imgId, productId) {
    if (!confirm('Remove this image?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_gallery_image');
    fd.append('img_id', imgId);
    fd.append('product_id', productId);
    try {
        const res = await fetch(window.location.href, {method:'POST', body: fd});
        const data = await res.json();
        if (data.ok) {
            const el = document.getElementById('gimg_' + imgId);
            if (el) el.remove();
        }
    } catch(e) { alert('Could not delete image'); }
}

function addDiscountTier() {
    const empty = document.getElementById('dtEmpty');
    if (empty) empty.remove();
    const row = document.createElement('div');
    row.className = 'dt-row';
    row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 28px;gap:6px;align-items:center;';
    row.innerHTML = `
        <div>
            <label style="font-size:10px;color:#94a3b8;font-weight:700;display:block;margin-bottom:2px;">Min Qty</label>
            <input type="number" class="pf-inp dt-min-qty" value="5" min="2" style="padding:7px 10px;font-size:13px;">
        </div>
        <div>
            <label style="font-size:10px;color:#94a3b8;font-weight:700;display:block;margin-bottom:2px;">Discount %</label>
            <input type="number" class="pf-inp dt-pct" value="5" min="1" max="90" step="0.5" style="padding:7px 10px;font-size:13px;">
        </div>
        <button type="button" onclick="this.closest('.dt-row').remove()" style="margin-top:16px;background:#fff1f2;border:1px solid #fecdd3;color:#ef4444;border-radius:8px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;flex-shrink:0;">×</button>
    `;
    document.getElementById('discountTierRows').appendChild(row);
}

async function saveDiscountTiers() {
    const pid = <?= (int)$id ?>;
    if (!pid) return;
    const rows = document.querySelectorAll('.dt-row');
    const tiers = [];
    rows.forEach(r => {
        const minQty = parseInt(r.querySelector('.dt-min-qty')?.value || 0);
        const pct    = parseFloat(r.querySelector('.dt-pct')?.value || 0);
        if (minQty > 1 && pct > 0) tiers.push({min_qty: minQty, discount_pct: pct});
    });
    // Sort by min_qty
    tiers.sort((a,b) => a.min_qty - b.min_qty);

    const fd = new FormData();
    fd.append('action', 'save_discount_tiers');
    fd.append('product_id', pid);
    tiers.forEach((t,i) => {
        fd.append(`tiers[${i}][min_qty]`, t.min_qty);
        fd.append(`tiers[${i}][discount_pct]`, t.discount_pct);
    });
    try {
        const res = await fetch(window.location.href, {method:'POST', body:fd});
        const data = await res.json();
        if (data.ok) {
            const msg = document.getElementById('dtSaveMsg');
            if (msg) { msg.style.display='block'; setTimeout(()=>msg.style.display='none', 2500); }
        }
    } catch(e) { alert('Could not save tiers'); }
}
</script>
<?php require_once __DIR__ . '/admin-footer.php'; ?>
