-- TeaStore Migration Script
-- Run this if upgrading from a previous version of the app

-- Add payment columns to orders table
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_intent_id VARCHAR(255) NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_status ENUM('pending','paid','failed') DEFAULT 'pending';

-- Rename pet_type to tea_type in products (run manually if upgrading from PetStore)
-- ALTER TABLE products CHANGE pet_type tea_type ENUM('green','black','white','oolong','herbal','all') DEFAULT 'all';
-- ALTER TABLE categories CHANGE pet_type tea_type ENUM('green','black','white','oolong','herbal','all') DEFAULT 'all';

-- Add payment_sessions table
CREATE TABLE IF NOT EXISTS payment_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_token VARCHAR(128) NOT NULL,
    order_ref VARCHAR(100) NOT NULL,
    amount DECIMAL(12,2) DEFAULT 0.00,
    payment_method VARCHAR(20) DEFAULT 'stripe',
    payment_intent_id VARCHAR(255) NULL,
    status VARCHAR(20) DEFAULT 'pending',
    expires_at DATETIME NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_payment_session_token (session_token),
    KEY idx_payment_order_ref (order_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Remove old KHQR/COD settings, add Stripe/PayPal
DELETE FROM site_settings WHERE setting_key IN ('khqr_enabled','cod_enabled','bakong_api_key','bakong_merchant_id','bakong_merchant_name','bakong_merchant_city','bakong_mcc','bakong_api_base','bakong_webhook_secret','khqr_timeout_seconds');

INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES
('stripe_enabled', '1', 'payment'),
('paypal_enabled', '1', 'payment'),
('stripe_publishable_key', '', 'payment'),
('stripe_secret_key', '', 'payment'),
('paypal_client_id', '', 'payment'),
('paypal_secret', '', 'payment'),
('paypal_mode', 'sandbox', 'payment'),
('currency_code', 'USD', 'general');

-- Update site name and branding
UPDATE site_settings SET setting_value='TeaStore' WHERE setting_key='site_name';
UPDATE site_settings SET setting_value='Premium Tea & Accessories' WHERE setting_key='site_tagline';
UPDATE site_settings SET setting_value='#2d6a4f' WHERE setting_key='theme_primary_color';
UPDATE site_settings SET setting_value='🚚 Free delivery on orders over $49 | Premium Tea Selection' WHERE setting_key='announcement_bar';

-- ── TeaStore v2 migrations ────────────────────────────────────────────────────

-- Nav Menu Items table
CREATE TABLE IF NOT EXISTS nav_menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(200) NOT NULL,
    url VARCHAR(500) DEFAULT '#',
    icon VARCHAR(80) DEFAULT '',
    parent_id INT DEFAULT 0,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    open_new_tab TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product Quantity Discount Tiers table
CREATE TABLE IF NOT EXISTS product_quantity_discounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    min_qty INT NOT NULL DEFAULT 5,
    discount_pct DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
