<?php
$pageTitle = "Manage Sections – TeaStore Admin";
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) { flash('error','Admin access required'); redirect(pretty_url('pages/login.php')); }

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $title = sanitize($_POST['title'] ?? '');
        $content = $_POST['content'] ?? ''; // Allow HTML
        $section_type = sanitize($_POST['section_type'] ?? 'custom');
        $display_order = (int)($_POST['display_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $id = (int)($_POST['id'] ?? 0);
        
        if (!$title) {
            flash('error', 'Title is required');
        } else {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO sections (title, content, section_type, display_order, is_active) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $content, $section_type, $display_order, $is_active]);
                flash('success', 'Section added successfully!');
            } else {
                $stmt = $pdo->prepare("UPDATE sections SET title=?, content=?, section_type=?, display_order=?, is_active=? WHERE id=?");
                $stmt->execute([$title, $content, $section_type, $display_order, $is_active, $id]);
                flash('success', 'Section updated successfully!');
            }
            redirect(pretty_url('admin/sections.php'));
        }
    }
    
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM sections WHERE id=?");
        $stmt->execute([$id]);
        flash('success', 'Section deleted successfully!');
        redirect(pretty_url('admin/sections.php'));
    }
    
    if ($action === 'reorder') {
        $order = $_POST['order'] ?? [];
        foreach ($order as $index => $id) {
            $stmt = $pdo->prepare("UPDATE sections SET display_order=? WHERE id=?");
            $stmt->execute([$index, (int)$id]);
        }
        flash('success', 'Order updated!');
        redirect(pretty_url('admin/sections.php'));
    }
}

// Get all sections
$sections = $pdo->query("SELECT * FROM sections ORDER BY display_order ASC, id DESC")->fetchAll();

// Get section being edited
$editSection = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editSection = $stmt->fetch();
}

require_once __DIR__ . '/admin-header.php';
?>
<div class="admin-header">
    <div>
        <h1>📑 Manage Sections</h1>
        <p style="font-size:12px;color:rgba(255,255,255,.5);margin-top:2px">Create custom sections displayed on homepage</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm" onclick="openAddModal()">
        <i class="fas fa-plus"></i> Add Section
    </button>
</div>

<?php if ($msg = flash('success')): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
<div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div style="background:var(--white);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-top:20px;">
    <?php if (empty($sections)): ?>
    <div style="text-align:center;padding:40px;color:var(--text-light);">
        <i class="fas fa-folder-open" style="font-size:48px;margin-bottom:16px;opacity:0.3;"></i>
        <p>No sections yet. Create your first section!</p>
    </div>
    <?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th style="width:50px;">Order</th>
                <th>Title</th>
                <th>Type</th>
                <th>Status</th>
                <th style="width:150px;">Actions</th>
            </tr>
        </thead>
        <tbody id="sections-tbody">
            <?php foreach ($sections as $sec): ?>
            <tr data-id="<?= $sec['id'] ?>">
                <td><i class="fas fa-grip-vertical" style="color:#ccc;cursor:move;"></i></td>
                <td><strong><?= htmlspecialchars($sec['title']) ?></strong></td>
                <td><span class="badge badge-info"><?= htmlspecialchars($sec['section_type']) ?></span></td>
                <td>
                    <?php if ($sec['is_active']): ?>
                    <span style="color:var(--success);font-size:12px;">● Active</span>
                    <?php else: ?>
                    <span style="color:#999;font-size:12px;">○ Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="?edit=<?= $sec['id'] ?>" class="btn btn-sm btn-ghost"><i class="fas fa-edit"></i></a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this section?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $sec['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-ghost" style="color:#dc2626;"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div id="sectionModal" class="modal" style="display:<?= $editSection ? 'flex' : 'none' ?>;">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header">
            <h3><?= $editSection ? 'Edit Section' : 'Add New Section' ?></h3>
            <button type="button" class="close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="<?= $editSection ? 'edit' : 'add' ?>">
            <?php if ($editSection): ?>
            <input type="hidden" name="id" value="<?= $editSection['id'] ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Section Title *</label>
                <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($editSection['title'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>Section Type</label>
                <select name="section_type" class="form-control">
                    <option value="custom" <?= ($editSection['section_type'] ?? '') === 'custom' ? 'selected' : '' ?>>Custom HTML</option>
                    <option value="banner" <?= ($editSection['section_type'] ?? '') === 'banner' ? 'selected' : '' ?>>Banner</option>
                    <option value="features" <?= ($editSection['section_type'] ?? '') === 'features' ? 'selected' : '' ?>>Features Grid</option>
                    <option value="testimonial" <?= ($editSection['section_type'] ?? '') === 'testimonial' ? 'selected' : '' ?>>Testimonial</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Content (HTML allowed)</label>
                <textarea name="content" class="form-control" rows="8" placeholder="<h2>Your Content Here</h2><p>Description...</p>"><?= htmlspecialchars($editSection['content'] ?? '') ?></textarea>
                <small style="color:var(--text-light);">You can use HTML tags and Bootstrap classes</small>
            </div>
            
            <div class="form-group">
                <label>Display Order</label>
                <input type="number" name="display_order" class="form-control" value="<?= $editSection['display_order'] ?? 0 ?>" min="0">
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" <?= ($editSection['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <span>Active (show on frontend)</span>
                </label>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= $editSection ? 'Update' : 'Create' ?> Section</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 9999;
}
.modal-content {
    background: var(--white); border-radius: var(--radius); width: 100%; max-width: 600px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2); animation: modalSlide .3s ease;
}
.modal-header {
    padding: 20px 24px; border-bottom: 1px solid var(--border);
    display: flex; justify-content: space-between; align-items: center;
}
.modal-header h3 { margin: 0; font-size: 18px; color: var(--text); }
.modal-footer {
    padding: 20px 24px; border-top: 1px solid var(--border);
    display: flex; justify-content: flex-end; gap: 10px;
}
.close {
    background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-light);
}
@keyframes modalSlide {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.form-group { margin-bottom: 16px; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: var(--text); }
.form-control {
    width: 100%; padding: 10px 14px; border: 1px solid var(--border);
    border-radius: 8px; font-size: 14px; font-family: inherit;
}
.form-control:focus { outline: none; border-color: var(--primary); }
.checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.checkbox-label input { width: auto; }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
.badge-info { background: #e0f2fe; color: #0369a1; }
.admin-table { width: 100%; border-collapse: collapse; }
.admin-table th, .admin-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
.admin-table th { font-weight: 600; color: var(--text-light); font-size: 12px; text-transform: uppercase; }
</style>

<script>
function openAddModal() {
    document.getElementById('sectionModal').style.display = 'flex';
}
function closeModal() {
    window.location.href = '<?= pretty_url('admin/sections.php') ?>';
}

// Simple drag-and-drop reordering
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('sections-tbody');
    if (!tbody) return;
    
    let draggedRow = null;
    
    tbody.querySelectorAll('tr').forEach(row => {
        row.style.cursor = 'grab';
        row.addEventListener('dragstart', function(e) {
            draggedRow = this;
            this.style.opacity = '0.5';
        });
        row.addEventListener('dragover', function(e) {
            e.preventDefault();
            const afterElement = getDragAfterElement(tbody, e.clientY);
            if (afterElement == null) {
                tbody.appendChild(draggedRow);
            } else {
                tbody.insertBefore(draggedRow, afterElement);
            }
        });
        row.addEventListener('dragend', function() {
            this.style.opacity = '1';
            saveOrder();
        });
    });
    
    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('tr:not(.dragging)')];
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }
    
    function saveOrder() {
        const order = [];
        tbody.querySelectorAll('tr').forEach(row => {
            order.push(row.dataset.id);
        });
        
        const formData = new FormData();
        formData.append('action', 'reorder');
        order.forEach((id, index) => {
            formData.append('order[' + index + ']', id);
        });
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
    }
});
</script>

<?php require_once __DIR__ . '/admin-footer.php'; ?>
