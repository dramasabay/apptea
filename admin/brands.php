<?php
$pageTitle = "Brands – TeaStore Admin";
require_once __DIR__ . '/../includes/config.php';
if (!canAccessAdmin()) { flash('error','Admin access required'); redirect(pretty_url('pages/login.php')); }

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM brands WHERE id=?")->execute([(int)$_GET['delete']]);
    flash('success', 'Brand deleted');
    redirect(pretty_url('admin/brands.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
    $desc = sanitize($_POST['description'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($name) {
        if ($id) {
            $pdo->prepare("UPDATE brands SET name=?,slug=?,description=? WHERE id=?")->execute([$name, $slug, $desc, $id]);
        } else {
            $pdo->prepare("INSERT INTO brands (name,slug,description) VALUES (?,?,?)")->execute([$name, $slug, $desc]);
        }
        flash('success', $id ? 'Brand updated' : 'Brand created');
        redirect(pretty_url('admin/brands.php'));
    }
}

$brands = $pdo->query("SELECT b.*, COUNT(p.id) AS pcount FROM brands b LEFT JOIN products p ON p.brand_id=b.id GROUP BY b.id ORDER BY b.name")->fetchAll();
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}
require_once __DIR__ . '/admin-header.php';
?>

<div class="admin-form-hero"><div><div style="font-size:30px;font-weight:900;letter-spacing:-1px">⭐ Brands</div><div style="color:var(--text-3);margin-top:6px">Manage names, descriptions, and storefront organization with a cleaner admin form.</div></div><a href="<?= pretty_url('admin/products.php') ?>" class="btn" style="border:1px solid var(--border);background:#fff">View products</a></div>

<div class="admin-form-grid">
    <div class="table-wrap admin-form-panel"><table class="admin-table">
        <thead><tr><th>Name</th><th>Products</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($brands as $b): ?>
            <tr>
                <td><strong><?= htmlspecialchars($b['name']) ?></strong></td>
                <td><?= $b['pcount'] ?></td>
                <td>
                    <div class="action-btns">
                        <a href="?edit=<?= $b['id'] ?>" class="btn-edit">Edit</a>
                        <a href="?delete=<?= $b['id'] ?>" class="btn-delete" onclick="return confirm('Delete?')">Del</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
    <div class="card" style="padding:0">
        <div class="card-body"><h3 style="font-size:16px;font-weight:800;margin-bottom:16px;"><?= $editing ? 'Edit Brand' : 'Add Brand' ?></h3>
        <form method="POST">
            <?php if ($editing): ?><input type="hidden" name="id" value="<?= $editing['id'] ?>"><?php endif; ?>
            <div class="form-group">
                <label>Brand Name</label>
                <input type="text" name="name" class="form-input" required value="<?= htmlspecialchars($editing['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Description (optional)</label>
                <textarea name="description" class="form-input"><?= htmlspecialchars($editing['description'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><?= $editing ? 'Update' : 'Add Brand' ?></button>
            <?php if ($editing): ?><a href="?" class="btn btn-sm" style="border:1px solid var(--border);margin-left:8px;">Cancel</a><?php endif; ?>
        </form></div></div>
</div>

<style>.admin-form-hero{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px}.admin-form-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:24px;align-items:start}.admin-form-panel{background:linear-gradient(180deg,#fff,#fcfcfd);border:1px solid #edf0f3;border-radius:22px;box-shadow:0 18px 40px rgba(15,23,42,.06)}.admin-form-panel .card-body{padding:24px}.field-hint{font-size:12px;color:var(--text-3);margin-top:6px}.switch-chip{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border-radius:14px;background:#f8fafc;border:1px solid #e5e7eb;font-weight:600}.upload-box{padding:16px;border:1.5px dashed #dbe3ec;border-radius:18px;background:#fbfcfe}.stack-card{padding:18px;border:1px solid #edf0f3;border-radius:18px;background:#fff}.stack-card h4{font-size:14px;font-weight:800;margin-bottom:6px}.stack-card p{font-size:12px;color:var(--text-3);line-height:1.6}@media(max-width:980px){.admin-form-grid{grid-template-columns:1fr}}</style>
<?php require_once __DIR__ . '/admin-footer.php'; ?>
