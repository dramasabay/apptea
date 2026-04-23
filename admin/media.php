<?php
$pageTitle = "Media Library – TeaStore Admin";
require_once __DIR__ . '/../includes/config.php';
if (!canAccessAdmin()) { flash('error','Access required'); redirect(pretty_url('pages/login.php')); }

$uploadDir = __DIR__ . '/../assets/img/products';
$bannerDir = __DIR__ . '/../assets/img/banners';
$imgDir    = __DIR__ . '/../assets/img';

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['media_files']['name'][0])) {
    $uploaded = 0;
    foreach ($_FILES['media_files']['tmp_name'] as $k => $tmp) {
        if (!$tmp || $_FILES['media_files']['error'][$k] !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($_FILES['media_files']['name'][$k], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif','svg'])) continue;
        $fn = 'media_'.time().'_'.$k.'.'.$ext;
        if (move_uploaded_file($tmp, $uploadDir.'/'.$fn)) $uploaded++;
    }
    flash('success', $uploaded.' file(s) uploaded!');
    header('Location: '.SITE_URL.'/admin/media.php'); exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $fn = basename($_POST['delete_file']);
    foreach ([$uploadDir,$bannerDir,$imgDir] as $d) {
        $path = $d.'/'.$fn;
        if (file_exists($path) && is_file($path)) { @unlink($path); break; }
    }
    flash('success', 'File deleted.');
    header('Location: '.SITE_URL.'/admin/media.php'); exit;
}

// Gather all images
function scanImgDir($dir, $baseUrl, $label) {
    if (!is_dir($dir)) return [];
    $files = [];
    foreach (glob($dir.'/*.{jpg,jpeg,png,webp,gif,svg}', GLOB_BRACE) as $f) {
        $files[] = [
            'path' => $f,
            'name' => basename($f),
            'url'  => $baseUrl.'/'.basename($f),
            'size' => filesize($f),
            'mtime'=> filemtime($f),
            'label'=> $label,
        ];
    }
    return $files;
}
$allFiles = array_merge(
    scanImgDir($uploadDir, SITE_URL.'/assets/img/products', 'Products'),
    scanImgDir($bannerDir, SITE_URL.'/assets/img/banners', 'Banners'),
    scanImgDir($imgDir,    SITE_URL.'/assets/img', 'General'),
);
usort($allFiles, fn($a,$b) => $b['mtime'] - $a['mtime']);

require_once __DIR__ . '/admin-header.php';
?>
<style>
.media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-top:20px}
.media-thumb{background:#fff;border:1.5px solid #edf0f3;border-radius:14px;overflow:hidden;position:relative;transition:.2s;cursor:pointer}
.media-thumb:hover{box-shadow:0 8px 24px rgba(0,0,0,.1);border-color:#cbd5e1}
.media-thumb img{width:100%;height:120px;object-fit:cover;display:block;background:#f8fafc}
.media-thumb .info{padding:8px 10px}
.media-thumb .fname{font-size:11px;font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.media-thumb .fmeta{font-size:10px;color:#94a3b8;margin-top:2px}
.media-thumb .del-btn{position:absolute;top:6px;right:6px;width:26px;height:26px;background:rgba(239,68,68,.9);color:#fff;border:none;border-radius:50%;font-size:11px;cursor:pointer;display:flex;align-items:center;justify-content:center;opacity:0;transition:.2s}
.media-thumb:hover .del-btn{opacity:1}
.label-pill{position:absolute;top:6px;left:6px;background:rgba(0,0,0,.55);color:#fff;font-size:9px;font-weight:700;padding:2px 7px;border-radius:20px;backdrop-filter:blur(4px)}
.dz{border:2px dashed #d1d5db;border-radius:14px;padding:28px;text-align:center;cursor:pointer;background:#f9fafb;transition:.2s}
.dz:hover,.dz.over{border-color:var(--primary);background:#fff8f8}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <h1 style="font-size:22px;font-weight:900;color:#0f172a;margin:0">🖼️ Media Library</h1>
        <p style="font-size:13px;color:#64748b;margin-top:3px"><?= count($allFiles) ?> files · Products, Banners & General images</p>
    </div>
</div>

<!-- Upload zone -->
<form method="POST" enctype="multipart/form-data" id="uploadForm">
    <div class="dz" id="dropZone" onclick="document.getElementById('mediaInput').click()"
         ondragover="event.preventDefault();this.classList.add('over')"
         ondragleave="this.classList.remove('over')"
         ondrop="event.preventDefault();this.classList.remove('over');handleDrop(event.dataTransfer.files)">
        <input type="file" id="mediaInput" name="media_files[]" accept="image/*" multiple style="display:none" onchange="document.getElementById('uploadForm').submit()">
        <div style="font-size:32px;margin-bottom:8px">📤</div>
        <div style="font-size:14px;font-weight:700;color:#374151">Click or drag &amp; drop images to upload</div>
        <div style="font-size:12px;color:#94a3b8;margin-top:4px">JPG, PNG, WebP, GIF — multiple files at once</div>
    </div>
</form>
<script>
function handleDrop(files){
    const dt=new DataTransfer();
    Array.from(files).forEach(f=>dt.items.add(f));
    document.getElementById('mediaInput').files=dt.files;
    document.getElementById('uploadForm').submit();
}
</script>

<!-- Filter tabs -->
<div style="display:flex;gap:8px;margin-top:20px;flex-wrap:wrap" id="filterTabs">
    <button class="btn btn-primary btn-sm active-tab" onclick="filterMedia('all',this)">All (<?= count($allFiles) ?>)</button>
    <?php
    $labels = array_unique(array_column($allFiles,'label'));
    foreach ($labels as $lbl):
        $cnt = count(array_filter($allFiles, fn($f) => $f['label'] === $lbl));
    ?>
    <button class="btn btn-ghost btn-sm" onclick="filterMedia('<?= htmlspecialchars($lbl) ?>',this)"><?= htmlspecialchars($lbl) ?> (<?= $cnt ?>)</button>
    <?php endforeach; ?>
</div>

<!-- Media Grid -->
<div class="media-grid" id="mediaGrid">
    <?php foreach ($allFiles as $f): ?>
    <div class="media-thumb" data-label="<?= htmlspecialchars($f['label']) ?>">
        <span class="label-pill"><?= htmlspecialchars($f['label']) ?></span>
        <img src="<?= htmlspecialchars($f['url']) ?>" alt="" loading="lazy" onclick="copyUrl('<?= htmlspecialchars($f['url']) ?>')" title="Click to copy URL">
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this file?')">
            <input type="hidden" name="delete_file" value="<?= htmlspecialchars($f['name']) ?>">
            <button type="submit" class="del-btn" title="Delete">✕</button>
        </form>
        <div class="info">
            <div class="fname" title="<?= htmlspecialchars($f['name']) ?>"><?= htmlspecialchars($f['name']) ?></div>
            <div class="fmeta"><?= round($f['size']/1024,1) ?>KB · <?= date('d M', $f['mtime']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Copy toast -->
<div id="copyToast" style="display:none;position:fixed;bottom:28px;left:50%;transform:translateX(-50%);background:#1a1a1a;color:#fff;padding:10px 20px;border-radius:20px;font-size:13px;font-weight:600;z-index:9999">URL copied!</div>

<script>
function copyUrl(url){
    navigator.clipboard?.writeText(url).then(()=>{
        const t=document.getElementById('copyToast');
        t.style.display='block';
        setTimeout(()=>t.style.display='none',2000);
    });
}
function filterMedia(label,btn){
    document.querySelectorAll('#filterTabs button').forEach(b=>b.classList.replace('btn-primary','btn-ghost'));
    btn.classList.replace('btn-ghost','btn-primary');
    document.querySelectorAll('.media-thumb').forEach(el=>{
        el.style.display = (label==='all' || el.dataset.label===label) ? 'block' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/admin-footer.php'; ?>
