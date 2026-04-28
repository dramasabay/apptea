-- TeaStore Complete Database (Merged & Updated)
-- This file combines all database requirements into one complete setup script
-- Use this for fresh installations - includes Dynamic Sections, Custom CSS, Google Pay, Venmo

CREATE DATABASE IF NOT EXISTS teastore_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE teastore_db;

-- ================================================================
-- CORE TABLES
-- ================================================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('admin','customer') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) UNIQUE NOT NULL,
    parent_id INT DEFAULT NULL,
    tea_type ENUM('green','black','white','oolong','herbal','all') DEFAULT 'all',
    image VARCHAR(255),
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) UNIQUE NOT NULL,
    logo VARCHAR(255),
    description TEXT
);

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(280) UNIQUE NOT NULL,
    description TEXT,
    short_desc TEXT,
    price DECIMAL(10,2) NOT NULL,
    sale_price DECIMAL(10,2) DEFAULT NULL,
    stock INT DEFAULT 0,
    category_id INT,
    brand_id INT,
    image VARCHAR(255),
    images TEXT,
    featured TINYINT(1) DEFAULT 0,
    is_new TINYINT(1) DEFAULT 1,
    tea_type ENUM('green','black','white','oolong','herbal','all') DEFAULT 'all',
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    attribute_name VARCHAR(100),
    attribute_value VARCHAR(100),
    price DECIMAL(10,2),
    sale_price DECIMAL(10,2),
    stock INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    order_number VARCHAR(30) UNIQUE NOT NULL,
    status ENUM('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
    subtotal DECIMAL(10,2) NOT NULL,
    shipping DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    name VARCHAR(100),
    email VARCHAR(150),
    phone VARCHAR(20),
    address TEXT,
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    payment_method VARCHAR(50) DEFAULT 'paypal',
    payment_status ENUM('pending','paid','failed') DEFAULT 'pending',
    payment_intent_id VARCHAR(255) NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    qty INT NOT NULL,
    options_json TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    session_id VARCHAR(128) DEFAULT NULL,
    product_id INT NOT NULL,
    variant_id INT DEFAULT NULL,
    qty INT NOT NULL DEFAULT 1,
    options_json TEXT DEFAULT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    session_id VARCHAR(128) DEFAULT NULL,
    product_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_wishlist (user_id, product_id),
    UNIQUE KEY unique_wishlist_session (session_id, product_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title VARCHAR(255),
    comment TEXT,
    is_approved TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    discount_type ENUM('percent','fixed') DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL,
    min_order DECIMAL(10,2) DEFAULT 0,
    max_uses INT DEFAULT NULL,
    used_count INT DEFAULT 0,
    expires_at DATETIME,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) UNIQUE NOT NULL,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general'
);

CREATE TABLE IF NOT EXISTS nav_menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    url VARCHAR(255) NOT NULL,
    parent_id INT DEFAULT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (parent_id) REFERENCES nav_menu_items(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS product_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    group_name VARCHAR(100) NOT NULL,
    group_type ENUM('radio','checkbox','select') DEFAULT 'radio',
    required TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS product_option_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    option_id INT NOT NULL,
    label VARCHAR(150) NOT NULL,
    price_add DECIMAL(10,2) DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (option_id) REFERENCES product_options(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quantity_discounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    min_qty INT NOT NULL,
    discount_percent DECIMAL(5,2) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ================================================================
-- NEW: Dynamic Sections Table
-- ================================================================

CREATE TABLE IF NOT EXISTS sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    section_type VARCHAR(50) DEFAULT 'custom',
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ================================================================
-- INDEXES FOR PERFORMANCE
-- ================================================================

CREATE INDEX idx_cart_user ON cart(user_id);
CREATE INDEX idx_cart_session ON cart(session_id);
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_featured ON products(featured);
CREATE INDEX idx_products_is_new ON products(is_new);
CREATE INDEX idx_products_tea_type ON products(tea_type);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_reviews_product ON reviews(product_id);
CREATE INDEX idx_sections_active ON sections(is_active);
CREATE INDEX idx_sections_order ON sections(display_order);

-- ================================================================
-- DEFAULT DATA
-- ================================================================

-- Default admin user (password: admin123)
INSERT INTO users (name, email, password, role) VALUES
('Admin', 'admin@teastore.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Default categories
INSERT INTO categories (name, slug, tea_type, is_active) VALUES
('Green Tea', 'green-tea', 'green', 1),
('Black Tea', 'black-tea', 'black', 1),
('White Tea', 'white-tea', 'white', 1),
('Oolong Tea', 'oolong-tea', 'oolong', 1),
('Herbal Tea', 'herbal-tea', 'herbal', 1),
('Tea Sets', 'tea-sets', 'all', 1),
('Accessories', 'accessories', 'all', 1),
('Gift Sets', 'gift-sets', 'all', 1);

-- Default brands
INSERT INTO brands (name, slug) VALUES
('Twinings', 'twinings'),
('Harney & Sons', 'harney-sons'),
('Vahdam', 'vahdam'),
('Bigelow', 'bigelow'),
('Celestial Seasonings', 'celestial-seasonings'),
('Rishi Tea', 'rishi-tea'),
('Numi Organic', 'numi-organic'),
('Yogi Tea', 'yogi-tea');

-- Default products
INSERT INTO products (name, slug, description, price, sale_price, stock, category_id, brand_id, featured, is_new, tea_type) VALUES
('Twinings English Breakfast Loose Leaf 200g', 'twinings-english-breakfast-200g', 'Classic rich and robust English Breakfast tea perfect for mornings.', 18.00, NULL, 50, 2, 1, 1, 1, 'black'),
('Harney & Sons Hot Cinnamon Spice 50 Bags', 'harney-sons-hot-cinnamon-50bags', 'Sweet and spicy blend with three types of cinnamon.', 14.00, NULL, 40, 2, 2, 0, 1, 'black'),
('Vahdam Himalayan Green Tea 100g', 'vahdam-himalayan-green-100g', 'Fresh and grassy green tea sourced from Himalayan gardens.', 12.00, NULL, 60, 1, 3, 1, 0, 'green'),
('Rishi Tea Ceremonial Matcha 30g', 'rishi-tea-ceremonial-matcha-30g', 'Premium ceremonial grade matcha for traditional preparation.', 28.00, 24.00, 30, 1, 6, 1, 1, 'green'),
('Numi Organic White Tea Loose Leaf 45g', 'numi-organic-white-tea-45g', 'Delicate and sweet white tea with floral notes.', 16.00, NULL, 35, 3, 7, 0, 1, 'white'),
('Yogi Tea Bedtime Tea 16 Bags', 'yogi-tea-bedtime-16bags', 'Relaxing chamomile and valerian root blend for restful sleep.', 7.50, NULL, 70, 5, 8, 0, 1, 'herbal'),
('Premium Cast Iron Teapot 600ml', 'premium-cast-iron-teapot-600ml', 'Traditional Japanese cast iron tetsubin teapot with infuser.', 45.00, NULL, 20, 6, NULL, 1, 1, 'all'),
('Luxury Tea Gift Set - 5 Varieties', 'luxury-tea-gift-set-5-varieties', 'Curated gift box with 5 premium tea varieties. Perfect for gifting.', 38.00, 32.00, 15, 8, NULL, 1, 1, 'all');

-- Default settings (NO STRIPE - only PayPal, Google Pay, Venmo, COD)
INSERT INTO site_settings (setting_key, setting_value, setting_group) VALUES
('site_name', 'TeaStore', 'general'),
('site_tagline', 'Premium Tea & Accessories', 'general'),
('site_phone', '+1 800 TEA SHOP', 'general'),
('site_email', 'support@teastore.com', 'general'),
('site_address', 'Phnom Penh, Cambodia', 'general'),
('hero_title', 'Premium Teas<br><em>Delivered Fresh</em>', 'homepage'),
('hero_subtitle', 'Discover the world''s finest teas. Green, black, white, oolong, herbal & more.', 'homepage'),
('hero_badge', '🍵 #1 Online Tea Store', 'homepage'),
('hero_bg_color', '#1a1a1a', 'homepage'),
('free_delivery_threshold', '49', 'shipping'),
('delivery_fee', '3.50', 'shipping'),
('theme_primary_color', '#2d6a4f', 'theme'),
('theme_mode', 'light', 'theme'),
('theme_font', 'DM Sans', 'theme'),
('card_hover_style', 'primary', 'theme'),
('product_hover_action', 'both', 'theme'),
('maintenance_mode', '0', 'general'),
('show_whatsapp_btn', '1', 'general'),
('whatsapp_number', '+85512345678', 'general'),
('facebook_url', '#', 'social'),
('instagram_url', '#', 'social'),
('telegram_url', '#', 'social'),
('paypal_enabled', '1', 'payment'),
('google_pay_enabled', '0', 'payment'),
('venmo_enabled', '0', 'payment'),
('cod_enabled', '1', 'payment'),
('paypal_client_id', '', 'payment'),
('paypal_secret', '', 'payment'),
('paypal_mode', 'sandbox', 'payment'),
('google_pay_merchant_id', '', 'payment'),
('google_pay_env', 'TEST', 'payment'),
('venmo_business_username', '', 'payment'),
('products_per_page', '16', 'general'),
('show_out_of_stock', '1', 'general'),
('currency_symbol', '$', 'general'),
('currency_code', 'USD', 'general'),
('announcement_bar', '🚚 Free delivery on orders over $49 | Premium Tea Selection', 'general'),
('announcement_bar_enabled', '1', 'general'),
('site_logo', '', 'general'),
('hero_cta_text', 'Shop Now', 'homepage'),
('hero_cta2_text', 'View Deals', 'homepage'),
('show_sale_badge', '1', 'homepage'),
('show_new_badge', '1', 'homepage'),
('guest_checkout', '1', 'general'),
('reviews_enabled', '1', 'general'),
('tiktok_url', '', 'social'),
('youtube_url', '', 'social'),
('telegram_bot_token', '', 'notifications'),
('telegram_chat_id', '', 'notifications'),
('telegram_notify_orders', '1', 'notifications'),
('telegram_notify_lowstock', '1', 'notifications'),
('show_telegram_btn', '1', 'general'),
('telegram_float_url', '#', 'general'),
('home_product_cols', '4', 'theme'),
('shop_product_cols', '4', 'theme'),
('home_products_per_section', '8', 'theme'),
('shop_per_page', '8', 'theme'),
('font_size_base', '15', 'theme'),
('font_size_h1', '28', 'theme'),
('font_size_h2', '22', 'theme'),
('font_size_a', '14', 'theme'),
('font_size_nav', '14', 'theme'),
('custom_css', '', 'design');

-- Default navigation menu
INSERT INTO nav_menu_items (label, url, sort_order, is_active) VALUES
('Home', '/', 1, 1),
('Shop', '/pages/shop.php', 2, 1),
('About', '/pages/about.php', 3, 1),
('Contact', '/pages/contact.php', 4, 1);

-- Default sample sections
INSERT INTO sections (title, content, section_type, display_order, is_active) VALUES
('Welcome Banner', '<div style="background:linear-gradient(135deg,#2d6a4f,#1b4332);color:#fff;padding:60px 20px;text-align:center;border-radius:16px;margin:20px 0;"><h2 style="font-size:32px;margin-bottom:16px;">Welcome to TeaStore</h2><p style="font-size:18px;opacity:0.9;">Discover the finest teas from around the world, delivered fresh to your door.</p></div>', 'banner', 1, 1),
('Why Choose Us', '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:24px;padding:40px 0;"><div style="text-align:center;padding:24px;background:#f8f9fa;border-radius:12px;"><i class="fas fa-leaf" style="font-size:40px;color:#2d6a4f;margin-bottom:16px;"></i><h4 style="margin-bottom:8px;">100% Organic</h4><p style="color:#666;font-size:14px;">All our teas are certified organic and sustainably sourced.</p></div><div style="text-align:center;padding:24px;background:#f8f9fa;border-radius:12px;"><i class="fas fa-shipping-fast" style="font-size:40px;color:#2d6a4f;margin-bottom:16px;"></i><h4 style="margin-bottom:8px;">Fast Delivery</h4><p style="color:#666;font-size:14px;">Free shipping on orders over $49. Delivered within 2-3 days.</p></div><div style="text-align:center;padding:24px;background:#f8f9fa;border-radius:12px;"><i class="fas fa-medal" style="font-size:40px;color:#2d6a4f;margin-bottom:16px;"></i><h4 style="margin-bottom:8px;">Premium Quality</h4><p style="color:#666;font-size:14px;">Hand-picked premium teas from the best gardens worldwide.</p></div></div>', 'features', 2, 1);

