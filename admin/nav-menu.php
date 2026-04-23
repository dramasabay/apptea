<?php
$pageTitle = "Nav Menu – TeaStore Admin";
require_once __DIR__ . '/../includes/config.php';
if (!canAccessAdmin()) { flash('error','Admin access required'); redirect(pretty_url('pages/login.php')); }

// ── Ensure table ─────────────────────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS nav_menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        label VARCHAR(200) NOT NULL,
        url VARCHAR(500) DEFAULT '#',
        icon VARCHAR(80) DEFAULT '',
        parent_id INT DEFAULT 0,
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        open_new_tab TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

$selfUrl = SITE_URL . '/admin/nav-menu.php';

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add / Edit item
    if ($action === 'save_item') {
        $iid      = (int)($_POST['item_id'] ?? 0);
        $label    = trim(sanitize($_POST['label'] ?? ''));
        $url      = trim($_POST['url'] ?? '#');
        $icon     = trim(sanitize($_POST['icon'] ?? ''));
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $newTab   = isset($_POST['open_new_tab']) ? 1 : 0;
        $active   = isset($_POST['is_active']) ? 1 : 0;

        if (!$label) { flash('error','Label is required'); header('Location:'.$selfUrl); exit; }

        if ($iid) {
            $pdo->prepare("UPDATE nav_menu_items SET label=?,url=?,icon=?,parent_id=?,open_new_tab=?,is_active=? WHERE id=?")
                ->execute([$label,$url,$icon,$parentId,$newTab,$active,$iid]);
            flash('success','Menu item updated!');
        } else {
            $maxSort = (int)($pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM nav_menu_items WHERE parent_id=".((int)$parentId))->fetchColumn());
            $pdo->prepare("INSERT INTO nav_menu_items (label,url,icon,parent_id,sort_order,open_new_tab,is_active) VALUES (?,?,?,?,?,?,?)")
                ->execute([$label,$url,$icon,$parentId,$maxSort+1,$newTab,$active]);
            flash('success','Menu item added!');
        }
        header('Location:'.$selfUrl); exit;
    }

    // Delete item (also delete children)
    if ($action === 'delete_item') {
        $iid = (int)($_POST['item_id'] ?? 0);
        $pdo->prepare("DELETE FROM nav_menu_items WHERE id=? OR parent_id=?")->execute([$iid,$iid]);
        flash('success','Item deleted.');
        header('Location:'.$selfUrl); exit;
    }

    // Toggle active
    if ($action === 'toggle_active') {
        $iid = (int)($_POST['item_id'] ?? 0);
        $pdo->prepare("UPDATE nav_menu_items SET is_active = 1 - is_active WHERE id=?")->execute([$iid]);
        header('Content-Type: application/json');
        echo json_encode(['ok'=>true]); exit;
    }

    // Save sort order (JSON array of ids)
    if ($action === 'save_order') {
        $ids = json_decode($_POST['ids'] ?? '[]', true);
        if (is_array($ids)) {
            foreach ($ids as $sort => $id) {
                $pdo->prepare("UPDATE nav_menu_items SET sort_order=? WHERE id=?")->execute([$sort, (int)$id]);
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['ok'=>true]); exit;
    }

    header('Location:'.$selfUrl); exit;
}

// ── Load items ────────────────────────────────────────────────────────────────
$allItems = $pdo->query("SELECT * FROM nav_menu_items ORDER BY parent_id, sort_order, id")->fetchAll();
$topLevel = array_filter($allItems, fn($r) => (int)$r['parent_id'] === 0);
$children = [];
foreach ($allItems as $r) {
    if ((int)$r['parent_id'] !== 0) $children[(int)$r['parent_id']][] = $r;
}

// Edit item?
$editItem = null;
if (isset($_GET['edit'])) {
    $estmt = $pdo->prepare("SELECT * FROM nav_menu_items WHERE id=?");
    $estmt->execute([(int)$_GET['edit']]);
    $editItem = $estmt->fetch();
}

require_once __DIR__ . '/admin-header.php';
?>

<style>
.nm-grid{display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start}
.nm-card{background:#fff;border:1.5px solid #e8edf3;border-radius:20px;padding:22px;box-shadow:0 2px 12px rgba(15,23,42,.05);margin-bottom:20px}
.nm-label{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:5px}
.nm-inp{border:1.5px solid #e2e8f0;border-radius:9px;padding:9px 12px;font-size:13px;outline:none;font-family:inherit;background:#fff;width:100%;box-sizing:border-box;transition:.15s;color:#0f172a}
.nm-inp:focus{border-color:#334155;box-shadow:0 0 0 3px rgba(51,65,85,.07)}
.nm-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:10px;border:none;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s}
.nm-btn-primary{background:var(--p);color:#fff}
.nm-btn-primary:hover{opacity:.88}
.nm-btn-ghost{background:#fff;border:1.5px solid #e2e8f0;color:#374151}
.nm-btn-ghost:hover{background:#f8fafc}
.nm-btn-danger{background:#fff1f2;border:1.5px solid #fecdd3;color:#ef4444}
.nm-btn-danger:hover{background:#fee2e2}
/* Item rows */
.nm-item-row{display:flex;align-items:center;gap:10px;padding:11px 14px;border:1.5px solid #e8edf3;border-radius:14px;background:#fff;margin-bottom:8px;cursor:grab;user-select:none;transition:.15s}
.nm-item-row:active{cursor:grabbing;box-shadow:0 8px 24px rgba(0,0,0,.12);background:#fafbff}
.nm-item-row.dragging{opacity:.4}
.nm-item-row.drag-over{border-color:var(--p);background:#fdf8ff}
.nm-child-row{margin-left:28px;background:#fafbfc}
.nm-drag-handle{color:#c4c9d4;font-size:15px;flex-shrink:0}
.nm-item-icon{width:30px;height:30px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:13px;color:#64748b;flex-shrink:0}
.nm-item-label{font-size:13px;font-weight:700;color:#0f172a;flex:1;min-width:0}
.nm-item-url{font-size:11px;color:#94a3b8;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px}
.nm-toggle{width:36px;height:20px;border-radius:10px;background:#e2e8f0;position:relative;cursor:pointer;flex-shrink:0;transition:.2s;border:none}
.nm-toggle.on{background:var(--p)}
.nm-toggle::after{content:'';position:absolute;top:2px;left:2px;width:16px;height:16px;border-radius:50%;background:#fff;transition:.2s}
.nm-toggle.on::after{left:18px}
.nm-row-actions{display:flex;gap:6px;flex-shrink:0}
.nm-row-btn{width:28px;height:28px;border-radius:8px;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;transition:.15s}
.nm-row-btn:hover{background:#f8fafc;color:#0f172a}
.nm-row-btn.del:hover{background:#fff1f2;border-color:#fecdd3;color:#ef4444}
.nm-add-child{display:flex;align-items:center;gap:6px;margin-left:28px;margin-bottom:8px;padding:8px 14px;border:1.5px dashed #e2e8f0;border-radius:12px;background:#fafbfc;color:#94a3b8;font-size:12px;font-weight:600;cursor:pointer;transition:.15s;width:fit-content}
.nm-add-child:hover{border-color:var(--p);color:var(--p);background:#fdf8ff}
.nm-empty{text-align:center;padding:40px;color:#94a3b8;font-size:13px}
.nm-chip{display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border-radius:6px;font-size:10px;font-weight:700;background:#f1f5f9;color:#64748b}
.nm-chip.new-tab{background:#eff6ff;color:#3b82f6}
@media(max-width:900px){.nm-grid{grid-template-columns:1fr}}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <h1 style="font-size:22px;font-weight:900;color:#0f172a;margin:0">🔗 Nav Menu Manager</h1>
        <p style="font-size:13px;color:#64748b;margin-top:3px">Build and manage your site's navigation menu. Drag to reorder.</p>
    </div>
    <a href="<?= SITE_URL ?>" target="_blank" class="nm-btn nm-btn-ghost"><i class="fas fa-external-link-alt"></i> Preview Site</a>
</div>


<div class="nm-grid">

    <!-- LEFT: Menu Tree -->
    <div>
        <div class="nm-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
                <div style="font-size:14px;font-weight:800;color:#0f172a">Menu Items <span style="font-size:12px;color:#94a3b8;font-weight:500">(drag to reorder)</span></div>
                <button class="nm-btn nm-btn-primary" onclick="showAddForm(0)"><i class="fas fa-plus"></i> Add Item</button>
            </div>

            <!-- Tree -->
            <div id="menuTree">
            <?php if (empty($topLevel)): ?>
                <div class="nm-empty">
                    <div style="font-size:36px;margin-bottom:10px">🔗</div>
                    No menu items yet.<br>Click <strong>"Add Item"</strong> to create your first one.
                </div>
            <?php else: ?>
                <?php foreach ($topLevel as $item): ?>
                <div class="nm-item-row" id="item_<?= $item['id'] ?>" draggable="true" data-id="<?= $item['id'] ?>" data-parent="0">
                    <span class="nm-drag-handle"><i class="fas fa-grip-vertical"></i></span>
                    <div class="nm-item-icon">
                        <?php if ($item['icon']): ?>
                            <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                        <?php else: ?>
                            <i class="fas fa-link"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div class="nm-item-label"><?= htmlspecialchars($item['label']) ?></div>
                        <div class="nm-item-url"><?= htmlspecialchars($item['url']) ?></div>
                    </div>
                    <?php if ($item['open_new_tab']): ?><span class="nm-chip new-tab">new tab</span><?php endif; ?>
                    <?php if (!empty($children[$item['id']])): ?><span class="nm-chip"><?= count($children[$item['id']]) ?> sub</span><?php endif; ?>
                    <button class="nm-toggle <?= $item['is_active']?'on':'' ?>" id="tog_<?= $item['id'] ?>" onclick="toggleActive(<?= $item['id'] ?>,this)" title="<?= $item['is_active']?'Active':'Hidden' ?>"></button>
                    <div class="nm-row-actions">
                        <button class="nm-row-btn" onclick="showAddForm(<?= $item['id'] ?>)" title="Add sub-item"><i class="fas fa-plus"></i></button>
                        <a class="nm-row-btn" href="?edit=<?= $item['id'] ?>" title="Edit"><i class="fas fa-pen"></i></a>
                        <button class="nm-row-btn del" onclick="deleteItem(<?= $item['id'] ?>,'<?= addslashes(htmlspecialchars($item['label'])) ?>')" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <?php if (!empty($children[$item['id']])): ?>
                    <?php foreach ($children[$item['id']] as $child): ?>
                    <div class="nm-item-row nm-child-row" id="item_<?= $child['id'] ?>" draggable="true" data-id="<?= $child['id'] ?>" data-parent="<?= $item['id'] ?>">
                        <span class="nm-drag-handle"><i class="fas fa-grip-vertical"></i></span>
                        <div class="nm-item-icon" style="background:#eff6ff">
                            <?php if ($child['icon']): ?>
                                <i class="<?= htmlspecialchars($child['icon']) ?>" style="color:#3b82f6"></i>
                            <?php else: ?>
                                <i class="fas fa-corner-down-right" style="color:#94a3b8;font-size:11px"></i>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1;min-width:0">
                            <div class="nm-item-label" style="font-size:12px"><?= htmlspecialchars($child['label']) ?></div>
                            <div class="nm-item-url"><?= htmlspecialchars($child['url']) ?></div>
                        </div>
                        <?php if ($child['open_new_tab']): ?><span class="nm-chip new-tab">new tab</span><?php endif; ?>
                        <button class="nm-toggle <?= $child['is_active']?'on':'' ?>" id="tog_<?= $child['id'] ?>" onclick="toggleActive(<?= $child['id'] ?>,this)" title="<?= $child['is_active']?'Active':'Hidden' ?>"></button>
                        <div class="nm-row-actions">
                            <a class="nm-row-btn" href="?edit=<?= $child['id'] ?>" title="Edit"><i class="fas fa-pen"></i></a>
                            <button class="nm-row-btn del" onclick="deleteItem(<?= $child['id'] ?>,'<?= addslashes(htmlspecialchars($child['label'])) ?>')" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>

            <?php if (!empty($topLevel)): ?>
            <div style="margin-top:14px;text-align:right">
                <button class="nm-btn nm-btn-ghost" onclick="saveOrder()" id="saveOrderBtn" style="font-size:12px">
                    <i class="fas fa-save"></i> Save Order
                </button>
                <span id="orderSaveMsg" style="font-size:11px;color:#16a34a;margin-left:8px;display:none">✓ Saved!</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick Icon Reference -->
        <div class="nm-card" style="padding:16px">
            <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:10px">Common Icons (copy into Icon field)</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                <?php
                $icons = [
                    'fas fa-home'=>'Home','fas fa-store'=>'Shop','fas fa-tags'=>'Deals',
                    'fas fa-award'=>'Brands','fas fa-leaf'=>'Tea','fas fa-gift'=>'Gifts',
                    'fas fa-percent'=>'Sale','fas fa-star'=>'New','fas fa-info-circle'=>'About',
                    'fas fa-phone'=>'Contact','fas fa-question-circle'=>'FAQ','fas fa-truck'=>'Delivery',
                    'fas fa-blog'=>'Blog','fas fa-newspaper'=>'News','fas fa-heart'=>'Wishlist',
                ];
                foreach ($icons as $cls => $name): ?>
                <span onclick="copyIcon('<?= $cls ?>')" title="Click to copy" style="display:inline-flex;align-items:center;gap:5px;padding:5px 9px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:11px;font-weight:600;color:#374151;transition:.15s" onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background='#f8fafc'">
                    <i class="<?= $cls ?>"></i> <?= $name ?>
                </span>
                <?php endforeach; ?>
            </div>
            <div id="copyMsg" style="font-size:11px;color:#16a34a;margin-top:8px;display:none">✓ Copied to clipboard!</div>
        </div>
    </div>

    <!-- RIGHT: Add / Edit Form -->
    <div>
        <div class="nm-card" id="formCard">
            <div style="font-size:14px;font-weight:800;color:#0f172a;margin-bottom:16px" id="formTitle">
                <?= $editItem ? '✏️ Edit: '.htmlspecialchars($editItem['label']) : '➕ Add Menu Item' ?>
            </div>

            <form method="POST" action="<?= $selfUrl ?>">
                <input type="hidden" name="action" value="save_item">
                <input type="hidden" name="item_id" value="<?= $editItem ? (int)$editItem['id'] : 0 ?>" id="fItemId">

                <div style="margin-bottom:13px">
                    <label class="nm-label">Label *</label>
                    <input type="text" name="label" class="nm-inp" placeholder="e.g. Shop All, Deals, About Us" required value="<?= $editItem ? htmlspecialchars($editItem['label']) : '' ?>" autofocus>
                </div>

                <div style="margin-bottom:13px">
                    <label class="nm-label">URL</label>
                    <input type="text" name="url" class="nm-inp" placeholder="/shop or https://..." value="<?= $editItem ? htmlspecialchars($editItem['url']) : '#' ?>">
                    <div style="font-size:10px;color:#94a3b8;margin-top:4px">Use relative paths like <code>/shop</code> or full URLs</div>
                </div>

                <div style="margin-bottom:13px">
                    <label class="nm-label">Icon <span style="font-weight:400">(FontAwesome class, optional)</span></label>
                    <div style="display:flex;gap:8px">
                        <input type="text" name="icon" id="fIcon" class="nm-inp" placeholder="fas fa-store" value="<?= $editItem ? htmlspecialchars($editItem['icon']) : '' ?>">
                        <div id="iconPreview" style="width:38px;height:38px;border:1.5px solid #e2e8f0;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:15px;color:#64748b;background:#f8fafc">
                            <i class="<?= $editItem && $editItem['icon'] ? htmlspecialchars($editItem['icon']) : 'fas fa-question' ?>"></i>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom:13px">
                    <label class="nm-label">Parent Item <span style="font-weight:400">(for sub-menu)</span></label>
                    <select name="parent_id" class="nm-inp" id="fParent">
                        <option value="0" <?= (!$editItem || !$editItem['parent_id']) ? 'selected' : '' ?>>— Top Level —</option>
                        <?php foreach ($topLevel as $tl): ?>
                        <option value="<?= $tl['id'] ?>" <?= ($editItem && (int)$editItem['parent_id'] === (int)$tl['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tl['label']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex;gap:14px;margin-bottom:16px;flex-wrap:wrap">
                    <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:13px;color:#374151">
                        <input type="checkbox" name="is_active" value="1" <?= (!$editItem || $editItem['is_active']) ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--p)">
                        <span>Visible</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:13px;color:#374151">
                        <input type="checkbox" name="open_new_tab" value="1" <?= ($editItem && $editItem['open_new_tab']) ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--p)">
                        <span>Open in new tab</span>
                    </label>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button type="submit" class="nm-btn nm-btn-primary" style="flex:1;justify-content:center">
                        <i class="fas fa-save"></i> <?= $editItem ? 'Update Item' : 'Add Item' ?>
                    </button>
                    <?php if ($editItem): ?>
                    <a href="<?= $selfUrl ?>" class="nm-btn nm-btn-ghost"><i class="fas fa-times"></i> Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Quick Links Card -->
        <div class="nm-card" style="padding:16px">
            <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:10px">Quick Link Presets</div>
            <div style="display:flex;flex-direction:column;gap:5px">
                <?php
                $presets = [
                    ['Shop All', '/shop', 'fas fa-store'],
                    ['New Arrivals', '/shop?new=1', 'fas fa-star'],
                    ['On Sale / Deals', '/shop?sale=1', 'fas fa-tags'],
                    ['Green Tea', '/shop?tea=green', 'fas fa-leaf'],
                    ['Black Tea', '/shop?tea=black', 'fas fa-mug-hot'],
                    ['Herbal Tea', '/shop?tea=herbal', 'fas fa-seedling'],
                    ['All Brands', '/brands', 'fas fa-award'],
                    ['My Account', '/pages/account.php', 'fas fa-user'],
                    ['Contact Us', '/pages/contact.php', 'fas fa-phone'],
                    ['FAQ', '/pages/faq.php', 'fas fa-question-circle'],
                ];
                foreach ($presets as [$label,$url,$icon]): ?>
                <button type="button" onclick="fillPreset(<?= htmlspecialchars(json_encode($label)) ?>,<?= htmlspecialchars(json_encode($url)) ?>,<?= htmlspecialchars(json_encode($icon)) ?>)"
                    style="display:flex;align-items:center;gap:8px;padding:7px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;cursor:pointer;text-align:left;font-size:12px;font-weight:600;color:#374151;transition:.15s;font-family:inherit"
                    onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background='#f8fafc'">
                    <i class="<?= $icon ?>" style="color:var(--p);width:14px;text-align:center"></i>
                    <?= htmlspecialchars($label) ?>
                    <span style="color:#94a3b8;font-weight:400;margin-left:auto;font-size:10px"><?= htmlspecialchars($url) ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<!-- Delete confirm modal -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:18px;padding:28px;max-width:360px;width:90%;box-shadow:0 24px 60px rgba(0,0,0,.2)">
        <div style="font-size:32px;text-align:center;margin-bottom:12px">🗑️</div>
        <div style="font-size:16px;font-weight:800;text-align:center;margin-bottom:8px">Delete Menu Item?</div>
        <div style="font-size:13px;color:#64748b;text-align:center;margin-bottom:20px" id="deleteModalMsg">This will also remove any sub-items.</div>
        <form method="POST" action="<?= $selfUrl ?>">
            <input type="hidden" name="action" value="delete_item">
            <input type="hidden" name="item_id" id="deleteItemId" value="">
            <div style="display:flex;gap:10px">
                <button type="button" onclick="document.getElementById('deleteModal').style.display='none'" style="flex:1;padding:11px;border:1.5px solid #e2e8f0;border-radius:12px;background:#fff;cursor:pointer;font-weight:700;font-family:inherit">Cancel</button>
                <button type="submit" style="flex:1;padding:11px;border:none;border-radius:12px;background:#ef4444;color:#fff;cursor:pointer;font-weight:700;font-family:inherit">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Add form quick-fill ────────────────────────────────────────────────────
function showAddForm(parentId) {
    document.getElementById('fItemId').value = 0;
    document.getElementById('fParent').value = parentId;
    document.getElementById('formTitle').textContent = parentId ? '➕ Add Sub-Item' : '➕ Add Menu Item';
    document.querySelector('[name="label"]').value = '';
    document.querySelector('[name="url"]').value = '#';
    document.querySelector('[name="icon"]').value = '';
    document.getElementById('iconPreview').innerHTML = '<i class="fas fa-question"></i>';
    document.getElementById('fIcon').dispatchEvent(new Event('input'));
    document.querySelector('[name="label"]').focus();
    document.getElementById('formCard').scrollIntoView({behavior:'smooth',block:'nearest'});
}

function fillPreset(label, url, icon) {
    document.querySelector('[name="label"]').value = label;
    document.querySelector('[name="url"]').value = url;
    document.querySelector('[name="icon"]').value = icon;
    document.getElementById('iconPreview').innerHTML = `<i class="${icon}"></i>`;
    document.querySelector('[name="label"]').focus();
    document.getElementById('formCard').scrollIntoView({behavior:'smooth',block:'nearest'});
}

// ── Icon preview live ──────────────────────────────────────────────────────
document.getElementById('fIcon').addEventListener('input', function() {
    const val = this.value.trim();
    const prev = document.getElementById('iconPreview');
    prev.innerHTML = val ? `<i class="${val}"></i>` : '<i class="fas fa-question"></i>';
});

// ── Copy icon ──────────────────────────────────────────────────────────────
function copyIcon(cls) {
    navigator.clipboard?.writeText(cls).then(() => {
        document.getElementById('fIcon').value = cls;
        document.getElementById('iconPreview').innerHTML = `<i class="${cls}"></i>`;
        const msg = document.getElementById('copyMsg');
        msg.style.display = 'block';
        setTimeout(() => msg.style.display='none', 1800);
    });
}

// ── Toggle active ──────────────────────────────────────────────────────────
async function toggleActive(id, btn) {
    const fd = new FormData();
    fd.append('action','toggle_active');
    fd.append('item_id', id);
    try {
        const res = await fetch(window.location.href, {method:'POST',body:fd});
        await res.json();
        btn.classList.toggle('on');
        btn.title = btn.classList.contains('on') ? 'Active' : 'Hidden';
    } catch(e) {}
}

// ── Delete item ────────────────────────────────────────────────────────────
function deleteItem(id, label) {
    document.getElementById('deleteItemId').value = id;
    document.getElementById('deleteModalMsg').textContent = `Delete "${label}"? Any sub-items will also be removed.`;
    document.getElementById('deleteModal').style.display = 'flex';
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});

// ── Drag to reorder ────────────────────────────────────────────────────────
let dragSrc = null;

document.querySelectorAll('.nm-item-row[draggable]').forEach(row => {
    row.addEventListener('dragstart', function(e) {
        dragSrc = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });
    row.addEventListener('dragend', function() {
        this.classList.remove('dragging');
        document.querySelectorAll('.nm-item-row').forEach(r => r.classList.remove('drag-over'));
    });
    row.addEventListener('dragover', function(e) {
        e.preventDefault();
        if (dragSrc && dragSrc !== this && dragSrc.dataset.parent === this.dataset.parent) {
            document.querySelectorAll('.nm-item-row').forEach(r => r.classList.remove('drag-over'));
            this.classList.add('drag-over');
        }
    });
    row.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        if (!dragSrc || dragSrc === this || dragSrc.dataset.parent !== this.dataset.parent) return;
        const parent = this.parentNode;
        const allRows = [...parent.querySelectorAll(':scope > .nm-item-row')];
        const srcIdx  = allRows.indexOf(dragSrc);
        const tgtIdx  = allRows.indexOf(this);
        if (srcIdx < tgtIdx) parent.insertBefore(dragSrc, this.nextSibling);
        else parent.insertBefore(dragSrc, this);
    });
});

// ── Save order ─────────────────────────────────────────────────────────────
async function saveOrder() {
    const btn = document.getElementById('saveOrderBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const rows = document.querySelectorAll('#menuTree .nm-item-row');
    const ids  = [...rows].map(r => r.dataset.id);

    const fd = new FormData();
    fd.append('action','save_order');
    fd.append('ids', JSON.stringify(ids));

    try {
        const res = await fetch(window.location.href, {method:'POST',body:fd});
        await res.json();
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Order';
        const msg = document.getElementById('orderSaveMsg');
        msg.style.display = 'inline';
        setTimeout(() => msg.style.display='none', 2000);
    } catch(e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Order';
        alert('Could not save order');
    }
}
</script>

<?php require_once __DIR__ . '/admin-footer.php'; ?>
