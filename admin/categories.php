<?php
$pageTitle = "Categories – TeaStore Admin";
require_once __DIR__ . '/../includes/config.php';
if (!canAccessAdmin()) { flash('error','Admin access required'); redirect(pretty_url('pages/login.php')); }

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([(int)$_GET['delete']]);
    flash('success', 'Category deleted');
    redirect(pretty_url('admin/categories.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
    $parentId = (int)($_POST['parent_id'] ?? 0) ?: null;
    $petType = sanitize($_POST['tea_type'] ?? 'both');
    $id = (int)($_POST['id'] ?? 0);
    if ($name) {
        if ($id) {
            $pdo->prepare("UPDATE categories SET name=?,slug=?,parent_id=?,tea_type=? WHERE id=?")->execute([$name, $slug, $parentId, $petType, $id]);
            flash('success', 'Category updated');
        } else {
            $pdo->prepare("INSERT INTO categories (name,slug,parent_id,tea_type) VALUES (?,?,?,?)")->execute([$name, $slug, $parentId, $petType]);
            flash('success', 'Category created');
        }
        redirect(pretty_url('admin/categories.php'));
    }
}

$categories = $pdo->query("
    SELECT c.*, p.name AS parent_name,
           COUNT(DISTINCT CASE
               WHEN prod.category_id = c.id THEN prod.id
               WHEN child.id IS NOT NULL AND prod.category_id = child.id THEN prod.id
               ELSE NULL
           END) AS pcount
    FROM categories c
    LEFT JOIN categories p ON p.id = c.parent_id
    LEFT JOIN categories child ON child.parent_id = c.id
    LEFT JOIN products prod ON (prod.category_id = c.id OR prod.category_id = child.id)
    GROUP BY c.id
    ORDER BY COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.name
")->fetchAll();
require_once __DIR__ . '/admin-header.php';
?>

<div class="admin-form-hero"><div><div style="font-size:30px;font-weight:900;letter-spacing:-1px">🏷️ Categories</div><div style="color:var(--text-3);margin-top:6px">Organize your catalog with better structure for dogs, cats, and subcategories.</div></div><a href="<?= pretty_url('admin/products.php') ?>" class="btn" style="border:1px solid var(--border);background:#fff">View products</a></div>
<div class="admin-form-grid">
    <div>
        <div class="table-wrap admin-form-panel"><table class="admin-table">
            <thead><tr><th>Name</th><th>Parent</th><th>Pet</th><th>Products</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($categories as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['name']) ?></td>
                    <td style="font-size:12px;color:var(--text-light);"><?= htmlspecialchars($c['parent_name'] ?? '–') ?></td>
                    <td><span class="tag"><?= ucfirst($c['tea_type']) ?></span></td>
                    <td><?= $c['pcount'] ?></td>
                    <td>
                        <div class="action-btns">
                            <a href="?edit=<?= $c['id'] ?>" class="btn-edit">Edit</a>
                            <a href="?delete=<?= $c['id'] ?>" class="btn-delete" onclick="return confirm('Delete?')">Del</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div>
        <?php
        $editing = null;
        if (isset($_GET['edit'])) {
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE id=?");
            $stmt->execute([(int)$_GET['edit']]);
            $editing = $stmt->fetch();
        }
        ?>
        <div class="card admin-form-panel" style="padding:0">
            <div class="card-body"><h3 style="font-size:16px;font-weight:800;margin-bottom:16px;"><?= $editing ? 'Edit Category' : 'Add Category' ?></h3>
            <form method="POST">
                <?php if ($editing): ?><input type="hidden" name="id" value="<?= $editing['id'] ?>"><?php endif; ?>
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" class="form-input" required value="<?= htmlspecialchars($editing['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Parent Category (optional)</label>
                    <select name="parent_id" class="form-input">
                        <option value="">None (top-level)</option>
                        <?php foreach ($categories as $c): if ($editing && $c['id'] == $editing['id']) continue; ?>
                        <option value="<?= $c['id'] ?>" <?= ($editing['parent_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tea Type</label>
                    <select name="tea_type" class="form-input">
                        <option value="green" <?= ($editing['tea_type'] ?? '') === 'dog' ? 'selected' : '' ?>>Dog</option>
                        <option value="black" <?= ($editing['tea_type'] ?? '') === 'cat' ? 'selected' : '' ?>>Cat</option>
                        <option value="all" <?= ($editing['tea_type'] ?? 'both') === 'both' ? 'selected' : '' ?>>Both</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><?= $editing ? 'Update' : 'Add Category' ?></button>
                <?php if ($editing): ?><a href="?" class="btn btn-sm" style="border:1px solid var(--border);margin-left:8px;">Cancel</a><?php endif; ?>
            </form>
        </div>
    </div>
</div>

<style>.admin-form-hero{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px}.admin-form-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:24px;align-items:start}.admin-form-panel{background:linear-gradient(180deg,#fff,#fcfcfd);border:1px solid #edf0f3;border-radius:22px;box-shadow:0 18px 40px rgba(15,23,42,.06)}.admin-form-panel .card-body{padding:24px}.field-hint{font-size:12px;color:var(--text-3);margin-top:6px}.switch-chip{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border-radius:14px;background:#f8fafc;border:1px solid #e5e7eb;font-weight:600}.upload-box{padding:16px;border:1.5px dashed #dbe3ec;border-radius:18px;background:#fbfcfe}.stack-card{padding:18px;border:1px solid #edf0f3;border-radius:18px;background:#fff}.stack-card h4{font-size:14px;font-weight:800;margin-bottom:6px}.stack-card p{font-size:12px;color:var(--text-3);line-height:1.6}@media(max-width:980px){.admin-form-grid{grid-template-columns:1fr}}</style>
<?php require_once __DIR__ . '/admin-footer.php'; ?>
