<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = "FAQ – TeaStore";
require_once __DIR__ . '/../includes/header.php';
$faqs = [
    ['Q: How long does delivery take?', 'A: Phnom Penh orders are delivered within 1-2 business days. Provinces take 3-5 business days.'],
    ['Q: What payment methods do you accept?', 'A: We accept Cash on Delivery, ABA Bank Transfer, Wing Money, and PayWay credit/debit cards.'],
    ['Q: Can I track my order?', 'A: Yes! Login to your account to view your order status. Our team will also contact you via phone or Telegram.'],
    ['Q: Do you offer free delivery?', 'A: Yes! Orders over $49 in Phnom Penh get free delivery. Orders over $89 get free delivery to provinces.'],
    ['Q: What is your return policy?', 'A: We accept returns within 7 days of delivery for unused items in original packaging.'],
    ['Q: Are your products authentic?', 'A: 100% yes. All our products are sourced directly from authorized distributors and official brand representatives.'],
];
?>
<div class="container" style="padding:40px 20px 80px;max-width:800px;">
<h1 class="page-title">Frequently Asked Questions</h1>
<?php foreach ($faqs as $faq): ?>
<div style="border:1px solid var(--border);border-radius:8px;padding:20px;margin-bottom:16px;">
<h3 style="font-size:15px;font-weight:600;margin-bottom:8px;color:var(--primary);"><?= $faq[0] ?></h3>
<p style="font-size:14px;color:var(--text-light);"><?= $faq[1] ?></p>
</div>
<?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
