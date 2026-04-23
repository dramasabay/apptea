<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = "Login – TeaStore";

if (isLoggedIn()) redirect(pretty_url('pages/account.php'));

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please fill in all fields';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            // Merge guest cart
            $pdo->prepare("UPDATE cart SET user_id=?, session_id=NULL WHERE session_id=?")->execute([$user['id'], session_id()]);
            flash('success', "Welcome back, {$user['name']}!");
            if ($user['role'] === 'admin') redirect(pretty_url('admin/index.php'));
            redirect($_GET['redirect'] ?? SITE_URL . '/index.php');
        } else {
            $error = 'Invalid email or password';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<meta name="site-url" content="<?= SITE_URL ?>">

<div class="container">
    <div class="form-section">
        <h1>Welcome Back 🍵</h1>
        <p>Login to your TeaStore account to manage orders and wishlist.</p>
        <?php if ($error): ?><div class="flash error"><?= sanitize($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Your password">
            </div>
            <button type="submit" class="btn btn-primary btn-full">Login to Account</button>
        </form>
        <div class="form-link">
            Don't have an account? <a href="<?= pretty_url('pages/register.php') ?>">Create one</a>
        </div>
        <div style="text-align:center;margin-top:20px;font-size:13px;color:var(--text-light);">

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
