# TeaStore REST API

Base URL: `https://yourdomain.com/api/`

All responses return JSON: `{ "success": true, "data": {...} }` or `{ "success": false, "error": "..." }`

## Authentication
Token-based. Include header: `Authorization: Bearer <token>`

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/login` | Login → returns token |
| POST | `/api/auth/register` | Register new user |
| GET | `/api/auth/me` | Get current user (auth required) |

### Login Request
```json
{ "email": "user@example.com", "password": "yourpass" }
```
### Login Response
```json
{ "success": true, "data": { "token": "eyJ...", "user": { "id": 1, "name": "...", "email": "...", "role": "customer" } } }
```

## Products

| Method | Endpoint | Params | Description |
|--------|----------|--------|-------------|
| GET | `/api/products` | `q`, `cat`, `brand`, `tea`, `sale=1`, `new=1`, `featured=1`, `page`, `limit` | List products |
| GET | `/api/products/{id}` | | Single product with variants & options |

### Tea types: `green`, `black`, `white`, `oolong`, `herbal`, `all`

## Categories & Brands

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/categories` | All categories with product counts |
| GET | `/api/brands` | All brands with product counts |

## Cart

Pass `X-Session-ID` header for guest cart, or `Authorization: Bearer` for logged-in cart.

| Method | Endpoint | Body | Description |
|--------|----------|------|-------------|
| GET | `/api/cart` | | Get cart items |
| POST | `/api/cart` | `{ product_id, qty, variant_id? }` | Add to cart |
| PUT | `/api/cart/{id}` | `{ qty }` | Update qty (0 = remove) |
| DELETE | `/api/cart/{id}` | | Remove item |

## Orders

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/orders` | User's orders (auth required) |
| GET | `/api/orders/{id}` | Order detail with items |
| POST | `/api/orders` | Create order |

### Create Order Body
```json
{
  "name": "John Tea",
  "email": "john@example.com",
  "phone": "+1234567890",
  "address": "123 Tea Street",
  "payment": "stripe",
  "payment_intent_id": "pi_...",
  "items": [
    { "product_id": 1, "qty": 2, "variant_id": null }
  ]
}
```

## Payment

| Method | Endpoint | Body | Description |
|--------|----------|------|-------------|
| POST | `/api/payment/stripe-intent` | `{ amount, ref }` | Create Stripe PaymentIntent → `client_secret` |
| POST | `/api/payment/paypal-create` | `{ amount, ref }` | Create PayPal order → `order_id`, `approve_url` |
| POST | `/api/payment/paypal-capture` | `{ paypal_order_id, order_ref }` | Capture approved PayPal payment |

## Flutter Integration Example

```dart
// Login
final res = await http.post(Uri.parse('$baseUrl/api/auth/login'),
  headers: {'Content-Type': 'application/json'},
  body: jsonEncode({'email': email, 'password': password}));
final token = jsonDecode(res.body)['data']['token'];

// Get products
final products = await http.get(Uri.parse('$baseUrl/api/products?tea=green&limit=20'),
  headers: {'Authorization': 'Bearer $token'});

// Stripe payment flow
// 1. Create intent
final intent = await http.post(Uri.parse('$baseUrl/api/payment/stripe-intent'),
  headers: {'Content-Type': 'application/json', 'Authorization': 'Bearer $token'},
  body: jsonEncode({'amount': 29.99, 'ref': 'TS-ORDER123'}));
final clientSecret = jsonDecode(intent.body)['data']['client_secret'];
// 2. Use flutter_stripe to confirm with clientSecret
// 3. POST /api/orders with payment_intent_id
```
