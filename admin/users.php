<?php
$pageTitle = "Users & Permissions – TeaStore Admin";
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) { flash('error','Admin access required'); redirect(pretty_url('pages/login.php')); }

// Ensure role column exists
try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(30) DEFAULT 'customer'"); } catch(Throwable $e){}

$selfUrl = SITE_URL . '/admin/users.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Create user
    if ($action === 'create_user') {
        $name  = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $role  = in_array($_POST['role'] ?? '', ['admin','store_manager','customer']) ? $_POST['role'] : 'customer';
        $pass  = $_POST['password'] ?? '';
        if (!$name || !$email || !$pass) { flash('error','Name, email and password are required.'); }
        else {
            $exists = $pdo->prepare("SELECT id FROM users WHERE email=?"); $exists->execute([$email]);
            if ($exists->fetch()) { flash('error','Email already exists.'); }
            else {
                $pdo->prepare("INSERT INTO users (name,email,phone,password,role,created_at) VALUES (?,?,?,?,?,NOW())")
                    ->execute([$name,$email,$phone,password_hash($pass,PASSWORD_DEFAULT),$role]);
                flash('success','User created successfully!');
            }
        }
        header('Location: '.$selfUrl); exit;
    }

    // Update role
    if ($action === 'update_role') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $role = in_array($_POST['role'] ?? '', ['admin','store_manager','customer']) ? $_POST['role'] : 'customer';
        $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role,$uid]);
        flash('success','Role updated!');
        header('Location: '.$selfUrl); exit;
    }

    // Delete user
    if ($action === 'delete_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $pdo->prepare("DELETE FROM users WHERE id=? AND role!='admin'")->execute([$uid]);
        flash('success','User deleted.');
        header('Location: '.$selfUrl); exit;
    }

    header('Location: '.$selfUrl); exit;
}

$users = $pdo->query("SELECT u.*, COUNT(o.id) AS order_count, COALESCE(SUM(o.total),0) AS total_spent FROM users u LEFT JOIN orders o ON o.user_id=u.id GROUP BY u.id ORDER BY u.created_at DESC")->fetchAll();

require_once __DIR__ . '/admin-header.php';

$roleColors = ['admin'=>'#eb1700','store_manager'=>'#7c3aed','customer'=>'#22c55e'];
$roleLabels = ['admin'=>'Admin','store_manager'=>'Store Manager','customer'=>'Customer'];
?>
<style>
.role-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;color:#fff}
.perm-table{width:100%;border-collapse:collapse;font-size:13px}
.perm-table th{background:#f8fafc;padding:10px 14px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;border-bottom:2px solid #edf0f3}
.perm-table td{padding:12px 14px;border-bottom:1px solid #edf0f3;vertical-align:middle}
.perm-check{color:#22c55e;font-size:16px}
.perm-cross{color:#cbd5e1;font-size:16px}
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center}
.modal-bg.open{display:flex}
.modal-box{background:#fff;border-radius:20px;padding:28px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.f-inp{border:1.5px solid #e2e8f0;border-radius:9px;padding:9px 12px;font-size:13px;outline:none;font-family:inherit;background:#fff;width:100%;box-sizing:border-box}
.f-inp:focus{border-color:#eb1700}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <div>
        <h1 style="font-size:22px;font-weight:900;color:#0f172a;margin:0">👥 Users & Permissions</h1>
        <p style="font-size:13px;color:#64748b;margin-top:3px">Manage user accounts and roles</p>
    </div>
    <button onclick="document.getElementById('createModal').classList.add('open')" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> Create User
    </button>
</div>

<!-- Permission Overview -->
<div style="background:#fff;border:1.5px solid #edf0f3;border-radius:18px;padding:20px;margin-bottom:24px;box-shadow:0 2px 12px rgba(0,0,0,.04)">
    <h3 style="font-size:14px;font-weight:800;margin:0 0 14px">🔐 Permission Levels</h3>
    <table class="perm-table">
        <thead>
            <tr>
                <th>Permission</th>
                <th><span class="role-badge" style="background:#eb1700">Admin</span></th>
                <th><span class="role-badge" style="background:#7c3aed">Store Manager</span></th>
                <th><span class="role-badge" style="background:#22c55e">Customer</span></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $perms = [
                ['View Dashboard','✅','✅','❌'],
                ['Manage Products','✅','✅','❌'],
                ['Manage Orders','✅','✅','❌'],
                ['Manage Banners & Homepage','✅','✅','❌'],
                ['View Reports & Export CSV','✅','✅','❌'],
                ['Manage Categories & Brands','✅','✅','❌'],
                ['Edit Site Settings','✅','❌','❌'],
                ['Create & Delete Users','✅','❌','❌'],
                ['Change User Roles','✅','❌','❌'],
                ['View Invoices','✅','✅','✅ (own only)'],
            ];
            foreach ($perms as [$label,$a,$sm,$c]): ?>
            <tr>
                <td style="font-weight:500;color:#1e293b"><?= $label ?></td>
                <td><?= $a ?></td>
                <td><?= $sm ?></td>
                <td><?= $c ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Users Table -->
<div style="background:#fff;border:1.5px solid #edf0f3;border-radius:18px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.04)">
    <table class="admin-table">
        <thead>
            <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Orders</th><th>Total Spent</th><th>Joined</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td style="color:var(--text-light);font-size:12px">#<?= $u['id'] ?></td>
                <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                <td style="font-size:13px"><?= htmlspecialchars($u['email']) ?></td>
                <td style="font-size:13px"><?= htmlspecialchars($u['phone'] ?? '–') ?></td>
                <td>
                    <span class="role-badge" style="background:<?= $roleColors[$u['role']] ?? '#22c55e' ?>"><?= $roleLabels[$u['role']] ?? ucfirst($u['role']) ?></span>
                </td>
                <td><?= $u['order_count'] ?></td>
                <td><?= formatPrice($u['total_spent']) ?></td>
                <td style="font-size:12px"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:6px;align-items:center">
                        <!-- Change Role -->
                        <form method="POST" style="display:inline-flex;gap:4px;align-items:center">
                            <input type="hidden" name="action" value="update_role">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <select name="role" onchange="this.form.submit()" style="border:1px solid #e2e8f0;border-radius:6px;padding:4px 8px;font-size:11px;outline:none;cursor:pointer">
                                <option value="customer" <?= $u['role']==='customer'?'selected':'' ?>>Customer</option>
                                <option value="store_manager" <?= $u['role']==='store_manager'?'selected':'' ?>>Store Manager</option>
                                <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                            </select>
                        </form>
                        <?php if ($u['role'] !== 'admin'): ?>
                        <form method="POST" onsubmit="return confirm('Delete this user?')">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn-delete" style="font-size:11px;padding:4px 8px">🗑</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Create User Modal -->
<div class="modal-bg" id="createModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="modal-box">
        <h3 style="font-size:17px;font-weight:800;margin:0 0 18px">➕ Create New User</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create_user">
            <div style="display:grid;gap:12px">
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px">Full Name *</label>
                    <input type="text" name="name" required class="f-inp" placeholder="John Doe">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px">Email *</label>
                    <input type="email" name="email" required class="f-inp" placeholder="user@example.com">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px">Phone</label>
                    <input type="text" name="phone" class="f-inp" placeholder="+855 xx xxx xxxx">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px">Password *</label>
                    <input type="password" name="password" required class="f-inp" placeholder="Min 6 characters">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px">Role</label>
                    <select name="role" class="f-inp">
                        <option value="customer">Customer</option>
                        <option value="store_manager">Store Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px">
                <button type="submit" class="btn btn-primary btn-sm" style="flex:1;justify-content:center">Create User</button>
                <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/admin-footer.php'; ?>
