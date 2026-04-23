<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = "How to Buy – TeaStore";
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container" style="padding:40px 20px 80px;max-width:800px;">
<h1 class="page-title">How to Buy</h1>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin:30px 0;">
<?php $steps=[['🔍','Browse','Find products by pet type, category, or brand'],['🛒','Add to Cart','Select your items and add them to your cart'],['📝','Checkout','Fill in your details and choose a payment method'],['🚚','Receive','We deliver right to your door!']]; ?>
<?php foreach ($steps as $i=>$s): ?>
<div style="text-align:center;padding:24px 16px;border:1px solid var(--border);border-radius:8px;">
<div style="font-size:40px;margin-bottom:12px;"><?= $s[0] ?></div>
<div style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:6px;">Step <?= $i+1 ?></div>
<h3 style="font-size:15px;margin-bottom:6px;"><?= $s[1] ?></h3>
<p style="font-size:12px;color:var(--text-light);"><?= $s[2] ?></p>
</div>
<?php endforeach; ?>
</div>
<p style="font-size:14px;color:var(--text-light);">Need help? <a href="contact.php" style="color:var(--primary);">Contact us</a> via Telegram or phone.</p>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
