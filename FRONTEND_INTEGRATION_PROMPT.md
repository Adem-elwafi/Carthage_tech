# Frontend Integration Prompt for AI Agent

## 🎯 Project Overview
Integrate the existing frontend pages located at `C:\Users\Adem\Desktop\mini-project\Carthage_tech\pages` with the ChTechBackend REST API backend located at `c:\wamp64\www\ChTechbackend`.

## 📦 Backend API Information

### Base URL
```
http://localhost/ChTechbackend/backend/api/
```

### Authentication System
The backend uses **PHP sessions** for authentication. All authenticated requests must include credentials.

**Session Management:**
- Successful login/register creates a PHP session
- Session ID is stored in browser cookies automatically
- Include `credentials: 'include'` in fetch requests
- CORS is already configured with `Access-Control-Allow-Origin: *`

---

## 🔐 Authentication Endpoints

### 1. Register User
```
POST /auth/register.php
Content-Type: application/json

Body:
{
  "email": "user@example.com",
  "password": "password123",
  "first_name": "John",
  "last_name": "Doe",
  "phone": "12345678"
}

Success Response (200):
{
  "success": true,
  "message": "Registration successful! You can now log in...",
  "data": {
    "user": {
      "id": 5,
      "email": "user@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "phone": "12345678",
      "role": "customer"
    }
  }
}
```

### 2. Login User
```
POST /auth/login.php
Content-Type: application/json

Body:
{
  "email": "user@example.com",
  "password": "password123"
}

Success Response (200):
{
  "success": true,
  "message": "Login successful! Welcome back, John!",
  "data": {
    "user": {
      "id": 5,
      "email": "user@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "phone": "12345678",
      "role": "customer"
    },
    "session_id": "abc123..."
  }
}
```

### 3. Get Current User
```
GET /auth/me.php

Success Response (200):
{
  "success": true,
  "message": "User data retrieved successfully.",
  "data": {
    "user": {
      "id": 5,
      "email": "user@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "phone": "12345678",
      "address": "123 Main St",
      "city": "Tunis",
      "postal_code": "1000",
      "role": "customer",
      "created_at": "2025-11-21 10:00:00"
    },
    "authenticated": true
  }
}

Error Response (401):
{
  "success": false,
  "message": "Authentication required. Please log in first."
}
```

### 4. Logout User
```
POST /auth/logout.php
or
GET /auth/logout.php

Success Response (200):
{
  "success": true,
  "message": "Logout successful. You have been logged out.",
  "data": {
    "logged_out": true
  }
}
```

---

## 🛍️ Products Endpoints

### 1. List Products (with Pagination)
```
GET /products/list.php?page=1&limit=20&category_id=1

Query Parameters:
- page: Page number (default: 1)
- limit: Items per page (default: 20, max: 100)
- category_id: Filter by category (optional)

Success Response (200):
{
  "success": true,
  "message": "Products retrieved successfully.",
  "data": {
    "products": [
      {
        "id": 1,
        "name": "Laptop HP Pavilion 15",
        "description": "Powerful laptop...",
        "price": "2499.00",
        "category": {
          "id": 1,
          "name": "Ordinateurs",
          "slug": "ordinateurs"
        },
        "image_url": "http://localhost/ChTechbackend/uploads/laptop.jpg",
        "stock_quantity": 15,
        "in_stock": true,
        "is_featured": true,
        "is_bestseller": false,
        "is_new": true,
        "brand": "HP",
        "rating": 4.5,
        "review_count": 120,
        "created_at": "2025-11-20 10:00:00"
      }
    ],
    "pagination": {
      "total": 50,
      "count": 20,
      "per_page": 20,
      "current_page": 1,
      "total_pages": 3,
      "has_next": true,
      "has_prev": false
    }
  }
}
```

### 2. Product Detail
```
GET /products/detail.php?id=1

Success Response (200):
{
  "success": true,
  "message": "Product details retrieved successfully.",
  "data": {
    "product": {
      "id": 1,
      "name": "Laptop HP Pavilion 15",
      "description": "Full description...",
      "price": "2499.00",
      "price_numeric": 2499.0,
      "category": {
        "id": 1,
        "name": "Ordinateurs",
        "slug": "ordinateurs"
      },
      "main_image": "http://localhost/.../laptop.jpg",
      "images": [
        {
          "id": 1,
          "url": "http://localhost/.../laptop-1.jpg",
          "is_primary": true,
          "display_order": 1
        }
      ],
      "stock": {
        "quantity": 15,
        "in_stock": true,
        "status": "available"
      },
      "flags": {
        "is_featured": true,
        "is_bestseller": false,
        "is_new": true
      },
      "brand": "HP",
      "rating": {
        "average": 4.5,
        "count": 120
      },
      "timestamps": {
        "created_at": "2025-11-20 10:00:00",
        "updated_at": "2025-11-21 08:30:00"
      }
    }
  }
}
```

### 3. Featured Products
```
GET /products/featured.php?limit=12&type=featured

Query Parameters:
- limit: Max products (default: 12, max: 50)
- type: featured | bestseller | new (optional, shows all if omitted)

Success Response (200):
{
  "success": true,
  "message": "Found 12 Featured product(s)",
  "data": {
    "products": [...],
    "count": 12,
    "filter": {
      "type": "featured",
      "limit": 12
    }
  }
}
```

### 4. Search Products
```
GET /products/search.php?q=laptop&limit=50

Query Parameters:
- q: Search term (required, min 2 characters)
- limit: Max results (default: 50, max: 100)

Success Response (200):
{
  "success": true,
  "message": "Found 8 product(s) matching 'laptop'",
  "data": {
    "query": "laptop",
    "count": 8,
    "products": [...]
  }
}
```

### 5. Products by Category
```
GET /products/category.php?slug=ordinateurs&page=1&limit=20

Query Parameters:
- slug: Category slug (required)
- page: Page number (default: 1)
- limit: Items per page (default: 20, max: 100)

Success Response (200):
{
  "success": true,
  "message": "Found 15 product(s) in category 'Ordinateurs'",
  "data": {
    "category": {
      "id": 1,
      "name": "Ordinateurs",
      "slug": "ordinateurs",
      "description": "Laptops and computers..."
    },
    "products": [...],
    "pagination": {...}
  }
}
```

---

## 🛒 Shopping Cart Endpoints (Requires Authentication)

### 1. View Cart
```
GET /cart/view.php

Success Response (200):
{
  "success": true,
  "message": "Cart retrieved successfully. You have 5 item(s) in your cart.",
  "data": {
    "cart_items": [
      {
        "cart_id": 1,
        "product": {
          "id": 1,
          "name": "Laptop HP Pavilion 15",
          "slug": "laptop-hp-pavilion-15",
          "brand": "HP",
          "price": "2499.00",
          "price_numeric": 2499.0,
          "image_url": "..."
        },
        "quantity": 2,
        "stock_available": 15,
        "in_stock": true,
        "subtotal": "4998.00",
        "subtotal_numeric": 4998.0,
        "added_at": "2025-11-21 10:00:00"
      }
    ],
    "summary": {
      "total_items": 5,
      "total_unique_products": 2,
      "cart_total": "5498.00",
      "cart_total_numeric": 5498.0
    },
    "user_id": 5
  }
}
```

### 2. Add to Cart
```
POST /cart/add.php
Content-Type: application/json

Body:
{
  "product_id": 1,
  "quantity": 2
}

Success Response (200):
{
  "success": true,
  "message": "Product added to cart successfully.",
  "data": {
    "action": "added",
    "product": {
      "id": 1,
      "name": "Laptop HP Pavilion 15",
      "price": "2499.00"
    },
    "quantity": 2
  }
}

Or if product already in cart (updates quantity):
{
  "success": true,
  "message": "Cart updated successfully.",
  "data": {
    "action": "updated",
    "product": {...},
    "previous_quantity": 1,
    "added_quantity": 2,
    "new_quantity": 3
  }
}
```

### 3. Update Cart Item Quantity
```
POST /cart/update.php
Content-Type: application/json

Body:
{
  "cart_id": 1,
  "quantity": 5
}

Success Response (200):
{
  "success": true,
  "message": "Cart item updated successfully.",
  "data": {
    "cart_id": 1,
    "product": {
      "id": 1,
      "name": "Laptop HP Pavilion 15"
    },
    "previous_quantity": 2,
    "new_quantity": 5,
    "new_subtotal": "12495.00"
  }
}
```

### 4. Remove from Cart
```
POST /cart/remove.php
Content-Type: application/json

Body:
{
  "cart_id": 1
}

Success Response (200):
{
  "success": true,
  "message": "Item removed from cart successfully.",
  "data": {
    "cart_id": 1,
    "product_name": "Laptop HP Pavilion 15",
    "removed": true
  }
}
```

### 5. Clear Cart
```
POST /cart/clear.php

Success Response (200):
{
  "success": true,
  "message": "Cart cleared successfully. Removed 3 item(s) (5 total quantity).",
  "data": {
    "items_removed": 3,
    "total_quantity_removed": 5,
    "cleared": true
  }
}
```

### 6. Get Cart Count
```
GET /cart/count.php

Success Response (200):
{
  "success": true,
  "message": "Cart count retrieved successfully.",
  "data": {
    "count": 5,
    "total_items": 5,
    "unique_products": 2,
    "is_empty": false
  }
}
```

---

## 📦 Orders Endpoints (Requires Authentication)

### 1. Create Order (Checkout)
```
POST /orders/create.php
Content-Type: application/json

Body:
{
  "shipping_address": "123 Main Street",
  "shipping_city": "Tunis",
  "shipping_postal_code": "1000",
  "payment_method": "cash_on_delivery"
}

Valid payment_methods: "cash_on_delivery", "bank_transfer", "card"

Success Response (201):
{
  "success": true,
  "message": "Order created successfully.",
  "data": {
    "order_id": 15,
    "order_number": "CT20251121",
    "subtotal": "2499.00",
    "tax_amount": "474.81",
    "tax_rate": "19%",
    "total_price": "2973.81",
    "status": "pending",
    "payment_method": "cash_on_delivery",
    "items_count": 2,
    "shipping_info": {
      "address": "123 Main Street",
      "city": "Tunis",
      "postal_code": "1000"
    },
    "message": "Your order has been placed. Order number: CT20251121"
  }
}
```

### 2. List User Orders
```
GET /orders/list.php?page=1&limit=10

Query Parameters:
- page: Page number (default: 1)
- limit: Orders per page (default: 10, max: 100)

Success Response (200):
{
  "success": true,
  "message": "Orders retrieved successfully.",
  "data": {
    "orders": [
      {
        "id": 15,
        "order_number": "CT20251121",
        "subtotal": "2499.00",
        "tax_amount": "474.81",
        "total_price": "2973.81",
        "total_price_numeric": 2973.81,
        "status": "pending",
        "status_label": "Pending",
        "payment_method": "cash_on_delivery",
        "payment_status": "pending",
        "shipping": {
          "address": "123 Main Street",
          "city": "Tunis",
          "postal_code": "1000"
        },
        "item_count": 2,
        "total_quantity": 3,
        "created_at": "2025-11-21 14:30:00",
        "created_at_formatted": "November 21, 2025, 2:30 pm",
        "updated_at": "2025-11-21 14:30:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 10,
      "total_orders": 5,
      "total_pages": 1,
      "has_more": false,
      "showing_from": 1,
      "showing_to": 5
    }
  }
}
```

### 3. Order Details
```
GET /orders/detail.php?id=15

Success Response (200):
{
  "success": true,
  "message": "Order details retrieved successfully.",
  "data": {
    "order": {
      "id": 15,
      "order_number": "CT20251121",
      "pricing": {
        "subtotal": "2499.00",
        "subtotal_numeric": 2499.0,
        "tax_amount": "474.81",
        "tax_amount_numeric": 474.81,
        "tax_rate": "19%",
        "total_price": "2973.81",
        "total_price_numeric": 2973.81
      },
      "status": {
        "order_status": "pending",
        "order_status_label": "Pending",
        "payment_status": "pending",
        "payment_method": "cash_on_delivery"
      },
      "shipping": {
        "address": "123 Main Street",
        "city": "Tunis",
        "postal_code": "1000",
        "full_address": "123 Main Street, Tunis 1000"
      },
      "items": [
        {
          "order_item_id": 1,
          "product": {
            "id": 1,
            "name": "Laptop HP Pavilion 15",
            "slug": "laptop-hp-pavilion-15",
            "brand": "HP",
            "image_url": "..."
          },
          "quantity": 1,
          "price_at_purchase": "2499.00",
          "price_at_purchase_numeric": 2499.0,
          "subtotal": "2499.00",
          "subtotal_numeric": 2499.0
        }
      ],
      "items_count": 1,
      "total_quantity": 1,
      "dates": {
        "created_at": "2025-11-21 14:30:00",
        "created_at_formatted": "November 21, 2025, 2:30 pm",
        "updated_at": "2025-11-21 14:30:00",
        "updated_at_formatted": "November 21, 2025, 2:30 pm"
      }
    }
  }
}
```

### 4. Update Order Status (ADMIN ONLY)
```
POST /orders/update-status.php
Content-Type: application/json

Body:
{
  "order_id": 15,
  "status": "shipped"
}

Valid statuses: "pending", "confirmed", "processing", "shipped", "delivered", "cancelled"

Success Response (200):
{
  "success": true,
  "message": "Order status updated successfully.",
  "data": {
    "order_id": 15,
    "order_number": "CT20251121",
    "previous_status": "confirmed",
    "new_status": "shipped",
    "updated_by": {
      "admin_id": 1,
      "admin_email": "admin@carthagetech.com"
    }
  }
}

Error (403 - Not Admin):
{
  "success": false,
  "message": "Access denied. This action requires administrator privileges.",
  "data": {
    "required_role": "admin",
    "current_role": "customer"
  },
  "status_code": 403
}
```

---

## 🔧 Implementation Guidelines

### 1. **JavaScript Fetch Configuration**

All API requests should use this pattern:

```javascript
// Base configuration
const API_BASE_URL = 'http://localhost/ChTechbackend/backend/api';

// Helper function for API calls
async function apiCall(endpoint, options = {}) {
  const config = {
    credentials: 'include', // IMPORTANT: Include cookies for session
    headers: {
      'Content-Type': 'application/json',
      ...options.headers
    },
    ...options
  };

  const response = await fetch(`${API_BASE_URL}${endpoint}`, config);
  const data = await response.json();
  
  if (!data.success) {
    throw new Error(data.message || 'API request failed');
  }
  
  return data;
}

// Example usage - Login
async function login(email, password) {
  try {
    const data = await apiCall('/auth/login.php', {
      method: 'POST',
      body: JSON.stringify({ email, password })
    });
    
    console.log('Login successful:', data.data.user);
    return data.data.user;
  } catch (error) {
    console.error('Login failed:', error.message);
    throw error;
  }
}

// Example usage - Add to cart
async function addToCart(productId, quantity = 1) {
  try {
    const data = await apiCall('/cart/add.php', {
      method: 'POST',
      body: JSON.stringify({ 
        product_id: productId, 
        quantity: quantity 
      })
    });
    
    console.log('Added to cart:', data.data);
    return data.data;
  } catch (error) {
    console.error('Add to cart failed:', error.message);
    throw error;
  }
}
```

### 2. **Error Handling**

All endpoints return consistent error responses:

```javascript
// Error response structure
{
  "success": false,
  "message": "Human-readable error message",
  "data": {
    "field_name": "Specific error for this field"
  },
  "status_code": 400
}

// Common status codes
// 400 - Bad Request (validation errors)
// 401 - Unauthorized (not logged in)
// 403 - Forbidden (not admin)
// 404 - Not Found
// 422 - Unprocessable Entity (validation failed)
// 500 - Server Error

// Error handling example
try {
  const data = await apiCall('/cart/add.php', {...});
} catch (error) {
  if (error.message.includes('Authentication required')) {
    // Redirect to login
    window.location.href = '/login.html';
  } else {
    // Show error to user
    showErrorMessage(error.message);
  }
}
```

### 3. **Authentication State Management**

```javascript
// Check if user is logged in
async function checkAuth() {
  try {
    const data = await apiCall('/auth/me.php');
    return data.data.user;
  } catch (error) {
    return null; // Not authenticated
  }
}

// Update UI based on auth state
async function updateAuthUI() {
  const user = await checkAuth();
  
  if (user) {
    // User is logged in
    document.getElementById('user-name').textContent = user.first_name;
    document.getElementById('login-btn').style.display = 'none';
    document.getElementById('logout-btn').style.display = 'block';
  } else {
    // User is not logged in
    document.getElementById('login-btn').style.display = 'block';
    document.getElementById('logout-btn').style.display = 'none';
  }
}

// Call on page load
document.addEventListener('DOMContentLoaded', updateAuthUI);
```

### 4. **Cart Count Badge**

```javascript
// Update cart count in navbar
async function updateCartCount() {
  try {
    const data = await apiCall('/cart/count.php');
    const count = data.data.count;
    
    const badge = document.getElementById('cart-count');
    if (count > 0) {
      badge.textContent = count;
      badge.style.display = 'inline-block';
    } else {
      badge.style.display = 'none';
    }
  } catch (error) {
    console.error('Failed to update cart count:', error);
  }
}

// Update after cart operations
async function addToCartAndUpdate(productId, quantity) {
  await addToCart(productId, quantity);
  await updateCartCount();
}
```

### 5. **Product Listing with Pagination**

```javascript
async function loadProducts(page = 1, limit = 20, categoryId = null) {
  let endpoint = `/products/list.php?page=${page}&limit=${limit}`;
  if (categoryId) {
    endpoint += `&category_id=${categoryId}`;
  }
  
  const data = await apiCall(endpoint);
  const { products, pagination } = data.data;
  
  // Render products
  const container = document.getElementById('products-container');
  container.innerHTML = products.map(product => `
    <div class="product-card">
      <img src="${product.image_url}" alt="${product.name}">
      <h3>${product.name}</h3>
      <p class="price">${product.price} TND</p>
      ${product.in_stock ? 
        `<button onclick="addToCart(${product.id}, 1)">Add to Cart</button>` :
        `<span class="out-of-stock">Out of Stock</span>`
      }
    </div>
  `).join('');
  
  // Render pagination
  renderPagination(pagination);
}

function renderPagination(pagination) {
  const container = document.getElementById('pagination');
  let html = '';
  
  if (pagination.has_prev) {
    html += `<button onclick="loadProducts(${pagination.current_page - 1})">Previous</button>`;
  }
  
  html += `<span>Page ${pagination.current_page} of ${pagination.total_pages}</span>`;
  
  if (pagination.has_next) {
    html += `<button onclick="loadProducts(${pagination.current_page + 1})">Next</button>`;
  }
  
  container.innerHTML = html;
}
```

### 6. **Checkout Flow**

```javascript
async function checkout(shippingInfo) {
  try {
    // Create order
    const orderData = await apiCall('/orders/create.php', {
      method: 'POST',
      body: JSON.stringify({
        shipping_address: shippingInfo.address,
        shipping_city: shippingInfo.city,
        shipping_postal_code: shippingInfo.postalCode,
        payment_method: shippingInfo.paymentMethod || 'cash_on_delivery'
      })
    });
    
    // Show success message
    showSuccessMessage(`Order ${orderData.data.order_number} created successfully!`);
    
    // Redirect to order confirmation page
    window.location.href = `/order-confirmation.html?order_id=${orderData.data.order_id}`;
    
  } catch (error) {
    if (error.message.includes('Cart is empty')) {
      showErrorMessage('Your cart is empty. Add products before checkout.');
    } else if (error.message.includes('Insufficient stock')) {
      showErrorMessage('Some items are out of stock. Please update your cart.');
    } else {
      showErrorMessage('Checkout failed: ' + error.message);
    }
  }
}
```

---

## ✅ Integration Checklist

### Pages to Integrate:

1. **Homepage**
   - [ ] Load featured products (`/products/featured.php`)
   - [ ] Load bestsellers (`/products/featured.php?type=bestseller`)
   - [ ] Load new arrivals (`/products/featured.php?type=new`)
   - [ ] Check auth state and update UI

2. **Products Page**
   - [ ] Load all products with pagination (`/products/list.php`)
   - [ ] Implement category filtering
   - [ ] Add search functionality (`/products/search.php`)
   - [ ] Add to cart buttons

3. **Product Detail Page**
   - [ ] Load single product (`/products/detail.php?id=X`)
   - [ ] Show image gallery
   - [ ] Implement add to cart with quantity selector
   - [ ] Show stock status

4. **Cart Page**
   - [ ] Load cart items (`/cart/view.php`)
   - [ ] Update quantity functionality (`/cart/update.php`)
   - [ ] Remove items (`/cart/remove.php`)
   - [ ] Clear cart button (`/cart/clear.php`)
   - [ ] Show totals
   - [ ] Proceed to checkout button

5. **Checkout Page**
   - [ ] Require authentication check
   - [ ] Load cart summary
   - [ ] Shipping address form
   - [ ] Payment method selection
   - [ ] Create order (`/orders/create.php`)

6. **Orders Page (User Account)**
   - [ ] Require authentication
   - [ ] List user orders (`/orders/list.php`)
   - [ ] View order details (`/orders/detail.php?id=X`)
   - [ ] Show order status

7. **Login/Register Pages**
   - [ ] Login form (`/auth/login.php`)
   - [ ] Register form (`/auth/register.php`)
   - [ ] Logout button (`/auth/logout.php`)
   - [ ] Redirect after login

8. **Navbar/Header (Global)**
   - [ ] Cart count badge (`/cart/count.php`)
   - [ ] User info display (`/auth/me.php`)
   - [ ] Login/Logout buttons
   - [ ] Search bar

---

## 🚀 Quick Start Commands

### Start WAMP Server
Make sure WAMP is running and the backend is accessible at:
```
http://localhost/ChTechbackend/backend/api/
```

### Test API Endpoints
Use browser console or test files:
```javascript
// Test in browser console
fetch('http://localhost/ChTechbackend/backend/api/products/list.php')
  .then(r => r.json())
  .then(d => console.log(d));
```

---

## 📝 Notes for AI Agent

1. **Session Management**: The backend uses PHP sessions. Always include `credentials: 'include'` in fetch calls.

2. **CORS**: Already configured. No additional CORS setup needed.

3. **Error Handling**: All endpoints return `{ success: boolean, message: string, data: any }`. Check `success` field.

4. **Authentication**: Protected endpoints return 401 if not logged in. Redirect to login page.

5. **Cart Operations**: Cart is per-user, persisted in database. Cart count should update after every cart operation.

6. **Order Flow**: Cart → Checkout (collect shipping) → Create Order → Clear Cart → Order Confirmation

7. **Image URLs**: Backend returns absolute URLs for images. Use them directly in `<img src="">`.

8. **Prices**: Backend returns formatted prices (e.g., "2499.00") and numeric values. Use formatted for display, numeric for calculations.

9. **Pagination**: All list endpoints support pagination. Implement "Load More" or page numbers.

10. **Admin Features**: `/orders/update-status.php` requires admin role. Regular users get 403.

---

## 🎨 UI/UX Recommendations

1. Show loading indicators during API calls
2. Display clear error messages from API responses
3. Update cart count badge after cart operations
4. Show "Out of Stock" for unavailable products
5. Implement form validation before API calls
6. Show success messages for user actions (added to cart, order placed, etc.)
7. Use skeleton loaders for product listings
8. Implement search debouncing (delay API calls while typing)

---

## 📞 Support

Backend API is fully documented with extensive comments in each PHP file. Check the PHP files for detailed explanations of:
- SQL queries and concepts
- Security considerations
- Data validation
- Error handling
- Transaction management (for orders)

**Backend Location:** `c:\wamp64\www\ChTechbackend\backend\api\`

Good luck with the integration! 🚀
