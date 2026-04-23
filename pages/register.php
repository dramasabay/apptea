<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = "Create Account – TeaStore";
if (isLoggedIn()) redirect(pretty_url('pages/account.php'));

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$name || !$email || !$password) $error = 'Please fill all required fields';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Invalid email address';
    elseif (strlen($password) < 6) $error = 'Password must be at least 6 characters';
    elseif ($password !== $confirm) $error = 'Passwords do not match';
    else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'Email already registered. <a href="'.SITE_URL.'/pages/login.php">Login instead</a>';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO users (name,email,password,phone) VALUES (?,?,?,?)");
            $ins->execute([$name, $email, $hash, $phone]);
            $uid = $pdo->lastInsertId();
            $_SESSION['user_id'] = $uid;
            $_SESSION['user_name'] = $name;
            $_SESSION['role'] = 'customer';
            $pdo->prepare("UPDATE cart SET user_id=? WHERE session_id=?")->execute([$uid, session_id()]);
            flash('success', "Account created! Welcome, $name!");
            redirect(SITE_URL . '/index.php');
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container">
    <div class="form-section">
        <h1>Create Account 🍵</h1>
        <p>Join TeaStore and get access to exclusive offers and easy order tracking.</p>
        <?php if ($error): ?><div class="flash error"><?= $error ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" required placeholder="Your full name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" required placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Phone Number (Optional)</label>
                <input type="tel" name="phone" placeholder="+855 xx xxx xxxx" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required placeholder="Min. 6 characters">
                </div>
                <div class="form-group">
                    <label>Confirm Password *</label>
                    <input type="password" name="confirm" required placeholder="Repeat password">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Create Account</button>
        </form>
        <div class="form-link">Already have an account? <a href="<?= pretty_url('pages/login.php') ?>">Login here</a></div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
