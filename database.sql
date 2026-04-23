-- TeaStore Database
CREATE DATABASE IF NOT EXISTS teastore_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE teastore_db;

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
    payment_method VARCHAR(50) DEFAULT 'stripe',
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
    product_name VARCHAR(255),
    variant_info VARCHAR(255),
    qty INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100),
    user_id INT,
    product_id INT NOT NULL,
    variant_id INT,
    qty INT DEFAULT 1,
    options_json TEXT,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES
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
('stripe_enabled', '1', 'payment'),
('paypal_enabled', '1', 'payment'),
('stripe_publishable_key', '', 'payment'),
('stripe_secret_key', '', 'payment'),
('paypal_client_id', '', 'payment'),
('paypal_secret', '', 'payment'),
('paypal_mode', 'sandbox', 'payment'),
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
('font_size_nav', '14', 'theme');

CREATE TABLE IF NOT EXISTS wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (user_id, product_id)
);

CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS product_option_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255),
    is_required TINYINT(1) DEFAULT 0,
    min_select INT DEFAULT 0,
    max_select INT DEFAULT 1,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS product_option_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    price_add DECIMAL(10,2) DEFAULT 0.00,
    is_default TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (group_id) REFERENCES product_option_groups(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (name, email, password, role) VALUES
('Admin', 'admin@teastore.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT INTO categories (name, slug, parent_id, tea_type) VALUES
('Green Tea', 'green-tea', NULL, 'green'),
('Black Tea', 'black-tea', NULL, 'black'),
('White Tea', 'white-tea', NULL, 'white'),
('Oolong Tea', 'oolong-tea', NULL, 'oolong'),
('Herbal Tea', 'herbal-tea', NULL, 'herbal'),
('Loose Leaf', 'loose-leaf', NULL, 'all'),
('Tea Bags', 'tea-bags', NULL, 'all'),
('Tea Accessories', 'tea-accessories', NULL, 'all'),
('Tea Sets', 'tea-sets', NULL, 'all'),
('Gift Sets', 'gift-sets', NULL, 'all'),
('Matcha', 'matcha', 1, 'green'),
('Chamomile', 'chamomile', 5, 'herbal');

INSERT INTO brands (name, slug) VALUES
('Twinings', 'twinings'),
('Harney & Sons', 'harney-sons'),
('Vahdam', 'vahdam'),
('Bigelow', 'bigelow'),
('Celestial Seasonings', 'celestial-seasonings'),
('Rishi Tea', 'rishi-tea'),
('Numi Organic', 'numi-organic'),
('Republic of Tea', 'republic-of-tea'),
('Mighty Leaf', 'mighty-leaf'),
('Yogi Tea', 'yogi-tea');

INSERT INTO products (name, slug, description, price, sale_price, stock, category_id, brand_id, featured, is_new, tea_type) VALUES
('Twinings English Breakfast Loose Leaf 200g', 'twinings-english-breakfast-200g', 'Classic rich and robust English Breakfast tea perfect for mornings.', 18.00, NULL, 50, 6, 1, 1, 1, 'black'),
('Harney & Sons Hot Cinnamon Spice 50 Bags', 'harney-sons-hot-cinnamon-50bags', 'Sweet and spicy blend with three types of cinnamon.', 14.00, NULL, 40, 7, 2, 0, 1, 'black'),
('Vahdam Himalayan Green Tea 100g', 'vahdam-himalayan-green-100g', 'Fresh and grassy green tea sourced from Himalayan gardens.', 12.00, NULL, 60, 1, 3, 1, 0, 'green'),
('Rishi Tea Ceremonial Matcha 30g', 'rishi-tea-ceremonial-matcha-30g', 'Premium ceremonial grade matcha for traditional preparation.', 28.00, 24.00, 30, 11, 6, 1, 1, 'green'),
('Numi Organic White Tea Loose Leaf 45g', 'numi-organic-white-tea-45g', 'Delicate and sweet white tea with floral notes.', 16.00, NULL, 35, 3, 7, 0, 1, 'white'),
('Republic of Tea Ginger Peach 50 Bags', 'republic-of-tea-ginger-peach-50bags', 'Warming ginger with sweet peach for a soothing herbal blend.', 11.50, NULL, 55, 5, 8, 0, 0, 'herbal'),
('Bigelow Constant Comment 40 Bags', 'bigelow-constant-comment-40bags', 'Spiced orange peel and sweet spices blend.', 8.95, NULL, 80, 7, 4, 0, 0, 'black'),
('Yogi Tea Bedtime Tea 16 Bags', 'yogi-tea-bedtime-16bags', 'Relaxing chamomile and valerian root blend for restful sleep.', 7.50, NULL, 70, 5, 10, 0, 1, 'herbal'),
('Celestial Seasonings Sleepytime 40 Bags', 'celestial-seasonings-sleepytime-40bags', 'Iconic chamomile blend for calming evenings.', 6.99, NULL, 90, 12, 5, 0, 0, 'herbal'),
('Mighty Leaf Organic Oolong 15 Bags', 'mighty-leaf-organic-oolong-15bags', 'Hand-picked oolong in whole leaf silken pouches.', 13.00, NULL, 45, 4, 9, 1, 0, 'oolong'),
('Twinings Pure Chamomile 50 Bags', 'twinings-pure-chamomile-50bags', 'Pure golden chamomile flowers for a calming cup.', 9.50, 7.50, 60, 12, 1, 0, 0, 'herbal'),
('Premium Cast Iron Teapot 600ml', 'premium-cast-iron-teapot-600ml', 'Traditional Japanese cast iron tetsubin teapot with infuser.', 45.00, NULL, 20, 9, NULL, 1, 1, 'all'),
('Bamboo Tea Tray Serving Board', 'bamboo-tea-tray-serving-board', 'Natural bamboo tea serving tray for a complete tea experience.', 22.00, 18.00, 25, 8, NULL, 0, 0, 'all'),
('Ceramic Tea Infuser Mug 350ml', 'ceramic-tea-infuser-mug-350ml', 'Elegant ceramic mug with built-in stainless infuser and lid.', 16.00, NULL, 40, 8, NULL, 1, 0, 'all'),
('Harney & Sons Tokyo Blend 50 Bags', 'harney-sons-tokyo-blend-50bags', 'Green tea with coconut, ginger and vanilla.', 15.00, NULL, 38, 1, 2, 0, 1, 'green'),
('Vahdam Earl Grey Loose Leaf 100g', 'vahdam-earl-grey-loose-leaf-100g', 'Classic bergamot-infused black tea sourced from Darjeeling.', 11.00, NULL, 50, 6, 3, 0, 0, 'black'),
('Luxury Tea Gift Set - 5 Varieties', 'luxury-tea-gift-set-5-varieties', 'Curated gift box with 5 premium tea varieties. Perfect for gifting.', 38.00, 32.00, 15, 10, NULL, 1, 1, 'all'),
('Rishi Turmeric Ginger Herbal 15 Bags', 'rishi-turmeric-ginger-herbal-15bags', 'Anti-inflammatory blend of turmeric, ginger and lemon.', 12.50, NULL, 45, 5, 6, 0, 0, 'herbal'),
('Electric Gooseneck Kettle 1L', 'electric-gooseneck-kettle-1l', 'Variable temperature gooseneck kettle for perfect tea brewing.', 55.00, NULL, 18, 8, NULL, 1, 0, 'all'),
('Numi Organic Dragon Well Green Tea 18 Bags', 'numi-organic-dragon-well-18bags', 'Nutty and sweet Chinese pan-fired green tea.', 9.00, NULL, 60, 1, 7, 0, 0, 'green');
