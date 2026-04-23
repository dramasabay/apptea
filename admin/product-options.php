<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = "Product Options – TeaStore Admin";
if (!canAccessAdmin()) { flash('error','Admin access required'); redirect(pretty_url('pages/login.php')); }

// Ensure tables exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_option_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        name VARCHAR(191) NOT NULL,
        is_required TINYINT(1) NOT NULL DEFAULT 0,
        min_select INT NOT NULL DEFAULT 0,
        max_select INT NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_option_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        name VARCHAR(191) NOT NULL,
        price_add DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

$productId = (int)($_GET['product_id'] ?? 0);
if (!$productId) { redirect(pretty_url('admin/products.php')); }

$productStmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
$productStmt->execute([$productId]);
$product = $productStmt->fetch();
if (!$product) { redirect(pretty_url('admin/products.php')); }

// ─── Self URL (always the real PHP file to avoid htaccess redirect losing POST) ─
$selfUrl = SITE_URL . '/admin/product-options.php?product_id=' . $productId;

// ─── Handle POST actions ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_group') {
        $name      = sanitize($_POST['name'] ?? '');
        $isReq     = (int)($_POST['is_required'] ?? 0);
        $minSel    = max(0, (int)($_POST['min_select'] ?? 0));
        $maxSel    = max(1, (int)($_POST['max_select'] ?? 1));
        if ($name !== '') {
            // Only block exact duplicate name for same product
            $dup = $pdo->prepare("SELECT id FROM product_option_groups WHERE product_id=? AND name=? LIMIT 1");
            $dup->execute([$productId, $name]);
            if ($dup->fetchColumn()) {
                flash('error', 'A group named "' . htmlspecialchars($name) . '" already exists. Choose a different name.');
            } else {
                $pdo->prepare("INSERT INTO product_option_groups (product_id,name,is_required,min_select,max_select) VALUES (?,?,?,?,?)")
                    ->execute([$productId, $name, $isReq, $minSel, $maxSel]);
                flash('success', 'Group "' . htmlspecialchars($name) . '" added!');
            }
        }
        header('Location: ' . $selfUrl); exit;
    }

    if ($action === 'edit_group') {
        $gid   = (int)($_POST['group_id'] ?? 0);
        $name  = sanitize($_POST['name'] ?? '');
        $isReq = (int)($_POST['is_required'] ?? 0);
        $minS  = max(0, (int)($_POST['min_select'] ?? 0));
        $maxS  = max(1, (int)($_POST['max_select'] ?? 1));
        if ($gid && $name !== '') {
            $pdo->prepare("UPDATE product_option_groups SET name=?,is_required=?,min_select=?,max_select=? WHERE id=? AND product_id=?")
                ->execute([$name, $isReq, $minS, $maxS, $gid, $productId]);
            flash('success', 'Group updated.');
        }
        header('Location: ' . $selfUrl); exit;
    }

    if ($action === 'delete_group') {
        $gid = (int)($_POST['group_id'] ?? 0);
        if ($gid) {
            $pdo->prepare("DELETE FROM product_option_items WHERE group_id IN (SELECT id FROM product_option_groups WHERE id=? AND product_id=?)")->execute([$gid, $productId]);
            $pdo->prepare("DELETE FROM product_option_groups WHERE id=? AND product_id=?")->execute([$gid, $productId]);
            flash('success', 'Group deleted.');
        }
        header('Location: ' . $selfUrl); exit;
    }

    if ($action === 'add_item') {
        $gid      = (int)($_POST['group_id'] ?? 0);
        $name     = sanitize($_POST['name'] ?? '');
        $priceAdd = (float)($_POST['price_add'] ?? 0);
        $isDef    = (int)($_POST['is_default'] ?? 0);
        if ($gid && $name !== '') {
            // Verify group belongs to this product
            $gCheck = $pdo->prepare("SELECT id FROM product_option_groups WHERE id=? AND product_id=?");
            $gCheck->execute([$gid, $productId]);
            if ($gCheck->fetchColumn()) {
                $dup = $pdo->prepare("SELECT id FROM product_option_items WHERE group_id=? AND name=? LIMIT 1");
                $dup->execute([$gid, $name]);
                if ($dup->fetchColumn()) {
                    flash('error', 'Option "' . htmlspecialchars($name) . '" already exists in this group.');
                } else {
                    $pdo->prepare("INSERT INTO product_option_items (group_id,name,price_add,is_default) VALUES (?,?,?,?)")
                        ->execute([$gid, $name, $priceAdd, $isDef]);
                    flash('success', 'Option "' . htmlspecialchars($name) . '" added!');
                }
            }
        }
        header('Location: ' . $selfUrl); exit;
    }

    if ($action === 'delete_item') {
        $iid = (int)($_POST['item_id'] ?? 0);
        if ($iid) {
            $pdo->prepare("DELETE FROM product_option_items WHERE id=?")->execute([$iid]);
            flash('success', 'Option removed.');
        }
        header('Location: ' . $selfUrl); exit;
    }

    if ($action === 'reorder_group') {
        $gid   = (int)($_POST['group_id'] ?? 0);
        $order = (int)($_POST['sort_order'] ?? 0);
        $pdo->prepare("UPDATE product_option_groups SET sort_order=? WHERE id=? AND product_id=?")->execute([$order, $gid, $productId]);
        header('Location: ' . $selfUrl); exit;
    }

    header('Location: ' . $selfUrl); exit;
}

// ─── Load groups with items ──────────────────────────────────────────────────
$groupsStmt = $pdo->prepare("SELECT * FROM product_option_groups WHERE product_id=? ORDER BY sort_order, id");
$groupsStmt->execute([$productId]);
$groups = $groupsStmt->fetchAll();
foreach ($groups as &$g) {
    $iStmt = $pdo->prepare("SELECT * FROM product_option_items WHERE group_id=? ORDER BY sort_order, id");
    $iStmt->execute([$g['id']]);
    $g['items'] = $iStmt->fetchAll();
}
unset($g);

require_once __DIR__ . '/admin-header.php';
?>
<style>
.po-wrap{max-width:1080px;margin:0 auto;padding:0 0 60px}
.po-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px}
.po-topbar h1{font-size:22px;font-weight:800;color:#0f172a;margin:0;display:flex;align-items:center;gap:10px}
.po-product-chip{font-size:12px;background:#f1f5f9;color:#475569;padding:4px 12px;border-radius:20px;font-weight:600;margin-top:4px;display:inline-block}
.po-layout{display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start}
.po-card{background:#fff;border:1.5px solid #e8edf3;border-radius:18px;overflow:hidden;margin-bottom:18px;box-shadow:0 2px 12px rgba(15,23,42,.05)}
.po-card-head{padding:14px 18px;background:linear-gradient(135deg,#f8fafc,#f1f5f9);border-bottom:1.5px solid #e8edf3;display:flex;align-items:center;justify-content:space-between;gap:10px}
.po-card-head-left{display:flex;align-items:center;gap:12px}
.po-group-avatar{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#1e293b,#334155);color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:800;flex-shrink:0}
.po-group-name{font-size:14px;font-weight:800;color:#0f172a}
.po-group-sub{font-size:11px;color:#64748b;margin-top:2px}
.badge-req{background:#0f172a;color:#fff;font-size:10px;font-weight:700;padding:2px 9px;border-radius:20px;text-transform:uppercase;letter-spacing:.4px}
.badge-opt{background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;font-size:10px;font-weight:700;padding:2px 9px;border-radius:20px;text-transform:uppercase;letter-spacing:.4px}
.po-items{padding:0 18px}
.po-item{display:flex;align-items:center;justify-content:space-between;padding:11px 0;border-bottom:1px solid #f0f4f8}
.po-item:last-child{border-bottom:none}
.po-item-info{display:flex;align-items:center;gap:10px}
.po-dot{width:7px;height:7px;border-radius:50%;background:#cbd5e1;flex-shrink:0}
.po-dot.default{background:#16a34a}
.po-item-name{font-size:13px;font-weight:600;color:#1e293b}
.po-item-default{font-size:10px;background:#dcfce7;color:#16a34a;font-weight:700;padding:1px 7px;border-radius:10px;margin-left:6px}
.po-item-price{font-size:12px;font-weight:700;color:#0f172a}
.po-item-price.free{color:#94a3b8;font-weight:400}
.po-del-item{background:none;border:none;color:#cbd5e1;cursor:pointer;font-size:13px;padding:3px 6px;border-radius:6px;transition:.15s}
.po-del-item:hover{background:#fef2f2;color:#ef4444}
.po-add-item-row{padding:12px 18px;background:#f8fafc;border-top:1.5px solid #e8edf3}
.po-inline{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
.po-lbl{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px}
.po-inp{border:1.5px solid #e2e8f0;border-radius:9px;padding:8px 11px;font-size:13px;outline:none;font-family:inherit;background:#fff;transition:.15s;width:100%}
.po-inp:focus{border-color:#1e293b;box-shadow:0 0 0 3px rgba(30,41,59,.07)}
.po-sel{border:1.5px solid #e2e8f0;border-radius:9px;padding:8px 11px;font-size:13px;outline:none;font-family:inherit;background:#fff;transition:.15s}
.po-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:9px;border:none;font-size:12px;font-weight:700;cursor:pointer;transition:.15s;white-space:nowrap;font-family:inherit}
.po-btn-dark{background:#1e293b;color:#fff}.po-btn-dark:hover{background:#0f172a}
.po-btn-primary{background:linear-gradient(135deg,#eb1700,#ff4a26);color:#fff;box-shadow:0 4px 12px rgba(235,23,0,.18)}.po-btn-primary:hover{opacity:.9}
.po-btn-ghost{background:#fff;color:#475569;border:1.5px solid #e2e8f0}.po-btn-ghost:hover{background:#f8fafc}
.po-btn-danger{background:#fff;border:1.5px solid #fecaca;color:#ef4444;font-size:12px}.po-btn-danger:hover{background:#fef2f2}
.po-empty{background:#fff;border:2px dashed #e2e8f0;border-radius:18px;padding:52px 24px;text-align:center;color:#94a3b8}
.po-empty-icon{font-size:44px;margin-bottom:14px}
.po-sidebar-card{background:#fff;border:1.5px solid #e8edf3;border-radius:18px;padding:22px;box-shadow:0 2px 12px rgba(15,23,42,.05);position:sticky;top:80px}
.po-sidebar-card h3{font-size:14px;font-weight:800;color:#0f172a;margin:0 0 16px;padding-bottom:12px;border-bottom:1.5px solid #f1f5f9}
.po-form-group{margin-bottom:13px}
.po-hint{font-size:11px;color:#94a3b8;background:#f8fafc;border-radius:8px;padding:9px 11px;margin-bottom:14px;line-height:1.6}
.po-hint b{color:#475569}
.no-items-msg{font-size:12px;color:#94a3b8;padding:14px 0;text-align:center}
@media(max-width:760px){.po-layout{grid-template-columns:1fr}.po-sidebar-card{position:static}}
</style>

<div class="po-wrap">
  <div class="po-topbar">
    <div>
      <h1>⚙️ Product Options</h1>
      <span class="po-product-chip">📦 <?= htmlspecialchars($product['name']) ?></span>
    </div>
    <a href="<?= pretty_url('admin/products.php') ?>" class="po-btn po-btn-ghost">← Back to Products</a>
  </div>

  <div class="po-layout">

    <!-- ── Groups list ───────────────────────────────────── -->
    <div>
      <?php if (empty($groups)): ?>
      <div class="po-empty">
        <div class="po-empty-icon">⚙️</div>
        <div style="font-size:15px;font-weight:700;color:#475569;margin-bottom:8px">No option groups yet</div>
        <div style="font-size:13px">Add groups like "Weight", "Size", "Flavor" →</div>
      </div>
      <?php endif; ?>

      <?php foreach ($groups as $g): ?>
      <div class="po-card">
        <!-- Group header -->
        <div class="po-card-head">
          <div class="po-card-head-left">
            <div class="po-group-avatar"><?= mb_strtoupper(mb_substr(htmlspecialchars($g['name']),0,1)) ?></div>
            <div>
              <div class="po-group-name"><?= htmlspecialchars($g['name']) ?></div>
              <div class="po-group-sub">
                <span class="<?= $g['is_required'] ? 'badge-req' : 'badge-opt' ?>"><?= $g['is_required'] ? 'Required' : 'Optional' ?></span>
                &nbsp;·&nbsp;Select up to <?= (int)$g['max_select'] ?>
                &nbsp;·&nbsp;<?= count($g['items']) ?> option<?= count($g['items']) !== 1 ? 's' : '' ?>
              </div>
            </div>
          </div>
          <form method="POST" action="<?= htmlspecialchars($selfUrl) ?>" onsubmit="return confirm('Delete this group and all its options?')">
            <input type="hidden" name="action" value="delete_group">
            <input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
            <button type="submit" class="po-btn po-btn-danger">🗑 Delete</button>
          </form>
        </div>

        <!-- Items list -->
        <div class="po-items">
          <?php if (empty($g['items'])): ?>
          <p class="no-items-msg">No options yet — add one below ↓</p>
          <?php endif; ?>
          <?php foreach ($g['items'] as $item): ?>
          <div class="po-item">
            <div class="po-item-info">
              <div class="po-dot<?= $item['is_default'] ? ' default' : '' ?>"></div>
              <div>
                <span class="po-item-name"><?= htmlspecialchars($item['name']) ?></span>
                <?php if ($item['is_default']): ?><span class="po-item-default">Default</span><?php endif; ?>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px">
              <span class="po-item-price<?= (float)$item['price_add'] == 0 ? ' free' : '' ?>">
                <?php
                  $pa = (float)$item['price_add'];
                  echo $pa > 0 ? '+$'.number_format($pa,2) : ($pa < 0 ? '-$'.number_format(abs($pa),2) : 'Included');
                ?>
              </span>
              <form method="POST" action="<?= htmlspecialchars($selfUrl) ?>" onsubmit="return confirm('Remove this option?')">
                <input type="hidden" name="action" value="delete_item">
                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                <button type="submit" class="po-del-item" title="Delete">✕</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Add item to group -->
        <div class="po-add-item-row">
          <form method="POST" action="<?= htmlspecialchars($selfUrl) ?>">
            <input type="hidden" name="action" value="add_item">
            <input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
            <div class="po-inline">
              <div style="flex:2;min-width:130px">
                <label class="po-lbl">Option Name</label>
                <input type="text" name="name" class="po-inp" placeholder='e.g. "500g", "Large", "Chicken"' required>
              </div>
              <div style="flex:1;min-width:90px">
                <label class="po-lbl">Extra Price ($)</label>
                <input type="number" name="price_add" value="0" step="0.01" class="po-inp">
              </div>
              <div>
                <label class="po-lbl">Default?</label>
                <select name="is_default" class="po-sel">
                  <option value="0">No</option>
                  <option value="1">Yes</option>
                </select>
              </div>
              <button type="submit" class="po-btn po-btn-dark" style="align-self:flex-end">+ Add</button>
            </div>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ── Sidebar: Add Group ──────────────────────────────── -->
    <div>
      <div class="po-sidebar-card">
        <h3>➕ Add Option Group</h3>
        <form method="POST" action="<?= htmlspecialchars($selfUrl) ?>">
          <input type="hidden" name="action" value="add_group">
          <div class="po-form-group">
            <label class="po-lbl">Group Name *</label>
            <input type="text" name="name" class="po-inp" placeholder='e.g. "Weight", "Size", "Color"' required>
          </div>
          <div class="po-form-group">
            <label class="po-lbl">Required?</label>
            <select name="is_required" class="po-sel" style="width:100%">
              <option value="0">Optional – customer can skip</option>
              <option value="1">Required – must choose</option>
            </select>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:13px">
            <div>
              <label class="po-lbl">Min Select</label>
              <input type="number" name="min_select" value="0" min="0" class="po-inp">
            </div>
            <div>
              <label class="po-lbl">Max Select</label>
              <input type="number" name="max_select" value="1" min="1" class="po-inp">
            </div>
          </div>
          <div class="po-hint">
            <b>Max = 1:</b> Radio buttons (pick one)<br>
            <b>Max &gt; 1:</b> Checkboxes (pick many)
          </div>
          <button type="submit" class="po-btn po-btn-primary" style="width:100%;justify-content:center;padding:11px">
            + Add Group
          </button>
        </form>

        <div style="margin-top:18px;padding-top:16px;border-top:1.5px solid #f1f5f9">
          <a href="<?= pretty_url('pages/product.php') ?>?slug=<?= urlencode($product['slug']) ?>" target="_blank" class="po-btn po-btn-ghost" style="width:100%;justify-content:center">
            <i class="fas fa-external-link-alt"></i> Preview Product Page
          </a>
        </div>
      </div>
    </div>

  </div><!-- po-layout -->
</div><!-- po-wrap -->

<?php require_once __DIR__ . '/admin-footer.php'; ?>
