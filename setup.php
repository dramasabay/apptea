<?php
function detect_install_site_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    $requestPath = str_replace('\\', '/', $requestPath);
    $basePath = rtrim(dirname($requestPath), '/.');

    if ($basePath === DIRECTORY_SEPARATOR || $basePath === '.' || strpos($basePath, '/home/') === 0) {
        $basePath = '';
    }
    return rtrim($scheme . '://' . $host . ($basePath ?: ''), '/');
}

function sql_statements_from_file(string $file): array
{
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('Unable to read database.sql');
    }

    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
    $sql = preg_replace('/^\s*CREATE\s+DATABASE\b.*?;\s*$/mi', '', $sql);
    $sql = preg_replace('/^\s*USE\s+`?[^`\s;]+`?\s*;\s*$/mi', '', $sql);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);

    $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql);
    $statements = [];
    foreach ($parts as $part) {
        $query = trim($part);
        if ($query !== '') {
            $statements[] = $query;
        }
    }
    return $statements;
}

function write_config_file(string $file, string $dbHost, string $dbUser, string $dbPass, string $dbName, string $siteUrl): void
{
    $templateFile = __DIR__ . '/includes/config.php';
    $content = @file_get_contents($templateFile);
    if ($content === false || trim($content) === '') {
        throw new RuntimeException('Could not read includes/config.php template.');
    }

    $replaceDefine = static function (string $source, string $name, string $value): string {
        $pattern = "/define\(\s*'" . preg_quote($name, '/') . "'\s*,\s*.*?\)\s*;/";
        $replacement = "define('" . $name . "', " . var_export($value, true) . ");";
        $updated = preg_replace($pattern, $replacement, $source, 1);
        return is_string($updated) && $updated !== '' ? $updated : $source;
    };

    $content = $replaceDefine($content, 'DB_HOST', $dbHost);
    $content = $replaceDefine($content, 'DB_USER', $dbUser);
    $content = $replaceDefine($content, 'DB_PASS', $dbPass);
    $content = $replaceDefine($content, 'DB_NAME', $dbName);
    $content = $replaceDefine($content, 'SITE_URL_CONFIG', rtrim($siteUrl, '/'));

    if (file_put_contents($file, $content) === false) {
        throw new RuntimeException('Could not write includes/config.php. Check file permissions.');
    }
}

$installed = false;
$errors = [];
$message = '';
$defaultSiteUrl = detect_install_site_url();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $dbName = trim($_POST['db_name'] ?? 'teastore_db');
    $siteUrl = rtrim(trim($_POST['site_url'] ?? $defaultSiteUrl), '/');

    try {
        $pdo = new PDO('mysql:host=' . $dbHost . ';charset=utf8mb4', $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $safeDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
        if ($safeDbName === '') {
            throw new RuntimeException('Invalid database name. Use only letters, numbers, and underscore.');
        }

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$safeDbName}`");

        foreach (array_merge(sql_statements_from_file(__DIR__ . '/database.sql'), file_exists(__DIR__ . '/database_migration.sql') ? sql_statements_from_file(__DIR__ . '/database_migration.sql') : []) as $query) {
            try {
                $pdo->exec($query);
            } catch (PDOException $e) {
                $normalized = strtolower($query);
                $ignorable = (
                    strpos($normalized, 'alter table orders add column if not exists payment_status') !== false
                    || strpos($e->getMessage(), 'Duplicate column name') !== false
                    || strpos($e->getMessage(), 'Duplicate entry') !== false
                );
                if (!$ignorable) {
                    throw $e;
                }
            }
        }

        write_config_file(__DIR__ . '/includes/config.php', $dbHost, $dbUser, $dbPass, $safeDbName, $siteUrl);

        $installed = true;
        $message = 'Installation successful!';
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    } catch (Throwable $e) {
        $errors[] = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TeaStore Setup</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.1); padding: 40px; max-width: 600px; width: 100%; }
        h1 { color: #2e7d32; margin-bottom: 8px; font-size: 28px; }
        p { color: #666; font-size: 14px; margin-bottom: 20px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #333; }
        input { width: 100%; border: 1px solid #ddd; border-radius: 6px; padding: 10px 14px; font-size: 14px; outline: none; transition: border-color 0.2s; }
        input:focus { border-color: #2e7d32; }
        .btn { background: #2e7d32; color: #fff; border: none; padding: 12px 24px; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; width: 100%; margin-top: 10px; }
        .btn:hover { background: #1b5e20; }
        .success { background: #e8f5e9; border: 1px solid #a5d6a7; padding: 16px; border-radius: 6px; margin-bottom: 20px; color: #1b5e20; font-size: 14px; }
        .error { background: #ffebee; border: 1px solid #ef9a9a; padding: 16px; border-radius: 6px; margin-bottom: 20px; color: #b71c1c; font-size: 14px; }
        .step { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-size: 14px; }
        .step .ok { color: #2e7d32; font-weight: 700; }
        a { color: #2e7d32; font-weight: 600; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 6px; }
    </style>
</head>
<body>
<div class="card">
    <h1>🍵 TeaStore Setup</h1>
    <p>Configure your database and install TeaStore on your real domain or subfolder.</p>

    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $e): ?>
            <div class="error">❌ <?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($installed): ?>
        <div class="success">
            <strong>✅ <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></strong><br><br>
            <div class="step"><span class="ok">✓</span> Database created and seeded</div>
            <div class="step"><span class="ok">✓</span> Config file updated</div>
            <div class="step"><span class="ok">✓</span> Site URL saved</div>
            <br>
            <strong>Admin Login:</strong> admin@teastore.com<br>
            <strong>Default password:</strong> <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px">password</code> — <em style="color:#dc2626">Change this immediately in Admin → Users after first login.</em><br><br>
            <a href="index.php">→ Go to Homepage</a> &nbsp;|&nbsp;
            <a href="admin/index.php">→ Go to Admin Panel</a><br><br>
            <small style="color:#666;">⚠️ For security, delete or rename <code>setup.php</code> after installation.</small>
        </div>
    <?php else: ?>
        <h2 style="font-size:16px;margin-bottom:16px;">Database Configuration</h2>
        <form method="POST">
            <div class="form-group">
                <label>Database Host</label>
                <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="form-group">
                <label>Database Username</label>
                <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? 'root', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="form-group">
                <label>Database Password</label>
                <input type="password" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Leave empty if no password">
            </div>
            <div class="form-group">
                <label>Database Name</label>
                <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'teastore_db', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="form-group">
                <label>Site URL (no trailing slash)</label>
                <input type="url" name="site_url" value="<?= htmlspecialchars($_POST['site_url'] ?? $defaultSiteUrl, ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <button type="submit" class="btn">🚀 Install TeaStore</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
