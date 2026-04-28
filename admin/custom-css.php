<?php
$pageTitle = "Custom CSS – TeaStore Admin";
require_once __DIR__ . '/../includes/config.php';
if (!isAdmin()) { flash('error','Admin access required'); redirect(pretty_url('pages/login.php')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $custom_css = $_POST['custom_css'] ?? '';
    setSetting('custom_css', $custom_css, 'design');
    clearSettingCache();
    flash('success', 'Custom CSS saved successfully! Changes are now live on frontend.');
    redirect(pretty_url('admin/custom-css.php'));
}

$custom_css = getSetting('custom_css', '');

require_once __DIR__ . '/admin-header.php';
?>
<div class="admin-header">
    <div>
        <h1>🎨 Custom CSS</h1>
        <p style="font-size:12px;color:rgba(255,255,255,.5);margin-top:2px">Add custom styles that apply to your entire store</p>
    </div>
    <a href="<?= pretty_url('index.php') ?>" target="_blank" class="btn btn-ghost btn-sm">
        <i class="fas fa-external-link-alt"></i> Preview Store
    </a>
</div>

<?php if ($msg = flash('success')): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
<div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div style="background:var(--white);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-top:20px;">
    <form method="POST">
        <div class="form-group">
            <label><i class="fas fa-code"></i> Custom CSS Code</label>
            <textarea name="custom_css" id="css-editor" rows="25" style="width:100%;font-family:'Fira Code','Consolas',monospace;font-size:13px;line-height:1.6;background:#1e1e1e;color:#d4d4d4;padding:16px;border-radius:8px;border:1px solid var(--border);" placeholder="/* Add your custom CSS here */\n.example {\n  color: red;\n}"><?= htmlspecialchars($custom_css) ?></textarea>
            <small style="color:var(--text-light);display:block;margin-top:8px;">
                <i class="fas fa-info-circle"></i> This CSS will be loaded on every page of your store. Use it to override default styles or add custom designs.
            </small>
        </div>
        
        <div style="display:flex;gap:10px;margin-top:16px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save CSS
            </button>
            <button type="button" class="btn btn-ghost" onclick="resetCss()">
                <i class="fas fa-undo"></i> Reset to Default
            </button>
        </div>
    </form>
    
    <div style="margin-top:30px;padding:16px;background:#f8f9fa;border-radius:8px;border:1px solid var(--border);">
        <h4 style="margin:0 0 12px 0;font-size:14px;color:var(--text);"><i class="fas fa-lightbulb" style="color:#f59e0b;"></i> Quick Tips</h4>
        <ul style="margin:0;padding-left:20px;font-size:13px;color:var(--text-light);line-height:1.8;">
            <li>Use browser DevTools (F12) to inspect elements and find class names</li>
            <li>Add <code>!important</code> to override inline styles</li>
            <li>Test changes in a private/incognito window first</li>
            <li>Backup your CSS before making major changes</li>
            <li>Use comments <code>/* like this */</code> to document your code</li>
        </ul>
    </div>
    
    <div style="margin-top:20px;padding:16px;background:#fffbeb;border-radius:8px;border:1px solid #fcd34d;">
        <h4 style="margin:0 0 8px 0;font-size:14px;color:#92400e;"><i class="fas fa-exclamation-triangle"></i> Example Snippets</h4>
        <pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;overflow-x:auto;font-size:12px;font-family:monospace;margin:0;"><code>/* Change primary button color */
.btn-primary {
  background: #your-color !important;
}

/* Hide announcement bar */
.announcement-bar {
  display: none !important;
}

/* Custom font for headings */
h1, h2, h3 {
  font-family: 'Your Font', sans-serif;
}

/* Round all product images */
.product-card img {
  border-radius: 50%;
}</code></pre>
    </div>
</div>

<script>
function resetCss() {
    if (confirm('Are you sure you want to remove all custom CSS? This cannot be undone.')) {
        document.getElementById('css-editor').value = '';
    }
}

// Auto-resize textarea
const textarea = document.getElementById('css-editor');
textarea.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = (this.scrollHeight) + 'px';
});
</script>

<style>
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text);
    font-size: 14px;
}
code {
    background: #e5e7eb;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
    color: #dc2626;
}
</style>

<?php require_once __DIR__ . '/admin-footer.php'; ?>
