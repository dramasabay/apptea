# 🍵 TeaStore — Premium Tea E-Commerce

A full-featured PHP/MySQL e-commerce platform for tea & accessories, rebuilt from PetStore with Stripe & PayPal payments and a REST API for Flutter mobile app development.

---

## 🚀 Quick Start

1. **Create database & import schema**
   ```sql
   mysql -u root -p < database.sql
   ```

2. **Configure `includes/config.php`**
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_pass');
   define('DB_NAME', 'teastore_db');
   define('SITE_URL_CONFIG', 'https://yourdomain.com');
   ```
   Or run `setup.php` in your browser for a guided setup.

3. **Add payment keys in Admin → Settings → Payment**
   - Stripe: Add `stripe_publishable_key` + `stripe_secret_key`
   - PayPal: Add `paypal_client_id` + `paypal_secret` + set mode (sandbox/live)

4. **Admin login**
   - URL: `/admin`
   - Email: `admin@teastore.com`
   - Password: `admin123` ← **Change this immediately**

---

## 💳 Payment Setup

### Stripe
1. Sign up at [stripe.com](https://stripe.com)
2. Go to Developers → API Keys
3. Copy **Publishable key** and **Secret key** into Admin → Settings → Payment
4. Use test cards: `4242 4242 4242 4242` (any future date, any CVC)

### PayPal
1. Sign up at [developer.paypal.com](https://developer.paypal.com)
2. Create an App → copy **Client ID** and **Secret**
3. Set mode to `sandbox` for testing, `live` for production
4. Add both keys in Admin → Settings → Payment

---

## 📱 Flutter REST API

Base URL: `https://yourdomain.com/api/`

Full API documentation: **`api/README.md`**

### Key Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/login` | Login → JWT token |
| POST | `/api/auth/register` | Register |
| GET | `/api/products` | Products (filterable) |
| GET | `/api/products/{id}` | Product detail |
| GET | `/api/categories` | All categories |
| GET | `/api/brands` | All brands |
| GET/POST | `/api/cart` | Cart management |
| POST | `/api/orders` | Create order |
| POST | `/api/payment/stripe-intent` | Create Stripe intent |
| POST | `/api/payment/paypal-create` | Create PayPal order |
| POST | `/api/payment/paypal-capture` | Capture PayPal payment |

### Flutter Stripe Flow
```
1. POST /api/payment/stripe-intent { amount, ref } → client_secret
2. Use flutter_stripe package to confirm card with client_secret
3. POST /api/orders { payment_intent_id, items, ... } → order created
```

### Flutter PayPal Flow
```
1. POST /api/payment/paypal-create { amount, ref } → order_id
2. Open PayPal approve_url in webview / in-app browser
3. On return, POST /api/payment/paypal-capture { paypal_order_id }
4. POST /api/orders { paypal_order_id, items, ... } → order created
```

---

## 📁 File Structure

```
teastore/
├── index.php                  # Homepage
├── includes/
│   ├── config.php             # DB config, helper functions
│   ├── header.php             # Site header + nav
│   ├── footer.php             # Site footer
│   ├── stripe.php             # Stripe API helper
│   ├── paypal.php             # PayPal API helper
│   └── telegram.php           # Telegram notifications
├── pages/
│   ├── shop.php               # Product listing
│   ├── product.php            # Product detail
│   ├── cart.php               # Shopping cart
│   ├── checkout.php           # Checkout (Stripe + PayPal)
│   ├── stripe-create-intent.php   # AJAX: Stripe intent
│   ├── paypal-create-order.php    # AJAX: PayPal order
│   ├── paypal-capture.php         # AJAX: PayPal capture
│   ├── order-success.php      # Order confirmation
│   └── ...                    # account, wishlist, login, etc.
├── admin/
│   ├── index.php              # Admin dashboard
│   ├── products.php           # Product management
│   ├── orders.php             # Order management
│   ├── categories.php         # Category management
│   ├── brands.php             # Brand management
│   ├── settings.php           # Site & payment settings
│   └── ...
├── api/
│   ├── index.php              # Full REST API router
│   ├── .htaccess              # API URL rewriting
│   └── README.md              # API documentation
├── assets/
│   ├── css/style.css
│   ├── js/main.js
│   └── img/
├── database.sql               # Fresh install schema + seed data
├── database_migration.sql     # Upgrade script from old version
└── setup.php                  # Browser-based setup wizard
```

---

## ☕ Tea Categories (Default Seed Data)

- Green Tea, Black Tea, White Tea, Oolong Tea, Herbal Tea
- Loose Leaf, Tea Bags, Matcha, Chamomile
- Tea Accessories, Tea Sets, Gift Sets

## 🏷️ Tea Brands (Default)
Twinings, Harney & Sons, Vahdam, Bigelow, Celestial Seasonings, Rishi Tea, Numi Organic, Republic of Tea, Mighty Leaf, Yogi Tea

---

## 🔧 Admin Features

- **Dashboard** — sales overview, recent orders, low stock alerts
- **Products** — add/edit with variants, images, option groups (e.g. size/weight), tea type filter
- **Orders** — view, update status, print invoice
- **Categories & Brands** — full CRUD
- **Settings** — theme, colors, fonts, Stripe/PayPal keys, announcement bar, Telegram notifications
- **Media** — image upload manager
- **Reports** — revenue, top products, customer reports

---

## 🌿 What Changed from PetStore

| Feature | Before | After |
|---------|--------|-------|
| Branding | Broteach Pet Store | TeaStore |
| Categories | Dogs, Cats | Green, Black, White, Oolong, Herbal, Accessories |
| Product type field | `pet_type` (dog/cat/both) | `tea_type` (green/black/white/oolong/herbal/all) |
| Nav menu | Shop by Pet | Shop by Tea |
| Payment 1 | KHQR (Bakong) | **Stripe** (card payments) |
| Payment 2 | Cash on Delivery | **PayPal** |
| API | Activation stub only | Full REST API for Flutter |
| Primary color | Red `#eb1700` | Tea green `#2d6a4f` |
