# Laravel + Bagisto E-commerce API

## Project Overview
This project is a lightweight e-commerce backend built with Laravel and the Bagisto framework.  
It exposes key e-commerce operations via REST API including product/category listing, cart, and checkout functionality.

---

## Setup Instructions

### 1. Clone the repository
```bash
git clone https://github.com/aswinsasi/bagisto-ecommerce.git
cd bagisto-ecommerce 
```

### 2. Install dependencies via Composer
```bash
composer install
```

### 3. Create .env file
Copy the example environment file and update your DB credentials:
```bash
cp .env.example .env
```

### 4. Generate application key
```bash
php artisan key:generate
```

### 5. Run migrations and seed database
```bash
php artisan migrate --seed
```

### 6. Install Bagisto
You can also install Bagisto via artisan command if starting fresh:

```bash
php artisan bagisto:install
```

Running the Application
Start the local development server:

```bash
php artisan serve
```

### Visit:

Admin Panel: http://localhost/admin/login
Default credentials:
Email: admin@example.com
Password: admin123

API Base URL: http://localhost/custom-api


### API Endpoints

| Method | Endpoint               | Description                              |
|--------|------------------------|------------------------------------------|
| GET    | `/custom-api/categories` | Fetch all product categories             |
| GET    | `/custom-api/products`   | Fetch all products with filters: category, price, color, size |
| POST   | `/custom-api/cart`       | Add a product to the cart                 |
| POST   | `/custom-api/checkout`   | Checkout with customer details            |

---

### Filters Available on `/custom-api/products`

- `category`  
  Filter by category ID or slug (e.g., `men-shirts`)

- `price_min` and `price_max`  
  Filter products within a price range

- `color`  
  Filter by product attribute **color**

- `size`  
  Filter by product attribute **size**

---

### Example Request



### Example request:
GET /api/products?category=men-shirts&price_min=100&price_max=500&color=red&size=medium