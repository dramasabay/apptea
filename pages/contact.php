<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = "Contact Us – TeaStore";
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = true;
    flash('success', 'Message sent! We will contact you within 24 hours.');
}
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container" style="padding:40px 20px 80px;max-width:800px;">
<h1 class="page-title">Contact Us</h1>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:40px;">
<div>
<form method="POST">
<div class="form-group"><label>Name *</label><input type="text" name="name" required placeholder="Your name"></div>
<div class="form-group"><label>Email *</label><input type="email" name="email" required placeholder="your@email.com"></div>
<div class="form-group"><label>Message *</label><textarea name="message" required rows="5" placeholder="How can we help?"></textarea></div>
<button type="submit" class="btn btn-primary">Send Message</button>
</form>
</div>
<div>
<h3 style="margin-bottom:16px;">Get in Touch</h3>
<div style="font-size:14px;color:var(--text-light);line-height:2.2;">
<p>📍 Phnom Penh, Cambodia</p>
<p>📞 +855 12 345 678</p>
<p>✉️ hello@teastore.com</p>
<p>🕐 Mon-Sat: 8am - 6pm</p>
<p>💬 Telegram: @teastore</p>
</div>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
