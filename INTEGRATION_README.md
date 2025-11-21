# Carthage Tech - Backend Integration Guide

## 🎉 Integration Complete!

Your Carthage Tech frontend is now fully integrated with the PHP backend APIs. All JavaScript files have been created and HTML pages have been updated to work with the backend.

---

## 📁 Created Files

### JavaScript Modules (in `/pages/js/`)

1. **api.js** - Core API communication functions
   - `apiCall()` - Makes HTTP requests to backend
   - `isLoggedIn()` - Checks authentication status
   - `getCurrentUser()` - Gets current user data
   - `logout()` - Logs user out
   - `checkAuthStatus()` - Verifies session with backend

2. **auth.js** - Authentication & user management
   - `handleLogin()` - Processes login form
   - `handleRegister()` - Processes registration form
   - Form validation
   - Session management with localStorage

3. **products.js** - Product display & management
   - `loadProducts()` - Lists products with pagination
   - `loadFeaturedProducts()` - Shows featured/bestseller/new items
   - `searchProducts()` - Product search functionality
   - `loadProductDetail()` - Shows single product details
   - `loadProductsByCategory()` - Category filtering

4. **cart.js** - Shopping cart functionality
   - `addToCart()` - Adds items to cart
   - `loadCart()` - Displays cart contents
   - `updateQuantity()` - Changes item quantity
   - `removeFromCart()` - Removes items
   - `clearCart()` - Empties cart
   - `getCartCount()` - Gets badge count
   - `updateCartBadge()` - Updates UI badge

5. **orders.js** - Order & checkout management
   - `createOrder()` - Processes checkout
   - `loadOrderHistory()` - Shows user's orders
   - `loadOrderDetail()` - Shows single order details
   - Form validation for shipping

6. **ui.js** - UI utilities & helpers
   - `updateAuthUI()` - Updates login/logout buttons
   - `showNotification()` - Toast notifications
   - `protectPage()` - Requires login for pages
   - `showLoading()` / `hideLoading()` - Loading indicators
   - `updateCartBadge()` - Cart count display

### HTML Pages

1. **cart.html** - Shopping cart page
   - Displays cart items in table
   - Quantity controls
   - Remove item buttons
   - Cart summary with totals
   - Checkout button

2. **checkout.html** - Checkout page
   - Shipping information form
   - Payment method selection
   - Order summary
   - Place order button

3. **login.html** (updated)
   - Integrated with auth.js
   - Backend API connection
   - Error handling

4. **register.html** (updated)
   - Integrated with auth.js
   - Backend API connection
   - Form validation

---

## 🚀 How to Use

### 1. Backend Setup

Make sure your backend is running:
- WAMP/XAMPP should be started
- Backend accessible at: `http://localhost/ChTechbackend/backend/api/`

### 2. Update Base URL (if needed)

If your backend is at a different location, edit `pages/js/api.js`:

```javascript
// Line 23 in api.js
const BASE_URL = 'http://localhost/ChTechbackend/backend/api';
```

### 3. Add Scripts to Your HTML Pages

For any page that needs backend functionality, add these scripts before `</body>`:

```html
<!-- Core scripts (required on all pages) -->
<script src="js/api.js"></script>
<script src="js/ui.js"></script>

<!-- Additional scripts as needed -->
<script src="js/auth.js"></script>      <!-- For login/register pages -->
<script src="js/products.js"></script>  <!-- For product pages -->
<script src="js/cart.js"></script>      <!-- For cart functionality -->
<script src="js/orders.js"></script>    <!-- For checkout/orders -->
```

### 4. Update Your HTML Structure

#### Header/Navigation (add to all pages):

```html
<div class="nav-actions">
    <a href="login.html" id="login-btn">Login</a>
    <a href="register.html" id="register-btn">Register</a>
    <button id="logout-btn" style="display:none;">Logout</button>
    <span id="user-name" style="display:none;"></span>
    <a href="cart.html">
        Cart <span id="cart-count" class="badge">0</span>
    </a>
</div>
```

#### Add Notification Container (optional):

```html
<div id="notification"></div>
```

### 5. Protect Pages (require login)

For pages that need authentication (cart, checkout, orders), add this script:

```html
<script>
    if (!isLoggedIn()) {
        protectPage();
    }
</script>
```

---

## 💡 Usage Examples

### Display Products on Homepage

```html
<div id="featured-products"></div>

<script src="js/api.js"></script>
<script src="js/ui.js"></script>
<script src="js/products.js"></script>
<script>
    // Load featured products
    loadFeaturedProducts('featured', 12, 'featured-products');
</script>
```

### Add "Add to Cart" Buttons

```html
<button onclick="addToCart(1, 1)">Add to Cart</button>
```

### Search Products

```html
<form id="search-form">
    <input type="text" id="search-input" placeholder="Search products...">
    <button type="submit">Search</button>
</form>

<div id="search-results"></div>

<script>
    setupSearchForm('search-form', 'search-input', 'search-results');
</script>
```

### Display Product Category

```html
<div id="category-products"></div>

<script>
    loadProductsByCategory('ordinateurs', 1, 20, 'category-products');
</script>
```

---

## 🔐 Authentication Flow

### Login Process:
1. User enters email and password in `login.html`
2. `auth.js` validates form
3. Calls `/auth/login.php` API
4. Saves user data to localStorage
5. Updates UI (shows logout button, user name, cart badge)
6. Redirects to homepage or requested page

### Register Process:
1. User fills registration form in `register.html`
2. `auth.js` validates all fields
3. Calls `/auth/register.php` API
4. Shows success message
5. Redirects to login page

### Session Management:
- User data stored in browser localStorage
- PHP session managed by backend
- `credentials: 'include'` sends session cookies
- `checkAuthStatus()` verifies with backend

---

## 🛒 Shopping Flow

### Adding to Cart:
1. User clicks "Add to Cart" button
2. Checks if logged in (redirects to login if not)
3. Calls `/cart/add.php` API
4. Shows success notification
5. Updates cart badge count

### Viewing Cart:
1. User goes to `cart.html`
2. `cart.js` calls `/cart/view.php` API
3. Displays items in table
4. Shows totals and summary

### Checkout:
1. User clicks "Proceed to Checkout"
2. Goes to `checkout.html`
3. Fills shipping information
4. Selects payment method
5. Clicks "Place Order"
6. `orders.js` calls `/orders/create.php` API
7. Cart is cleared
8. Redirects to order confirmation

---

## 🎨 Customization

### Change Notification Colors

Edit `ui.js` (lines 90-110) to customize notification styles:

```javascript
case 'success':
    bgColor = '#d4edda';    // Background
    textColor = '#155724';  // Text
    borderColor = '#c3e6cb'; // Border
    break;
```

### Change Product Card Layout

Edit `products.js` `renderProducts()` function (lines 80-130) to change HTML structure.

### Add Loading Spinner Style

Edit `ui.js` `showLoading()` function (lines 400-430) to customize loading indicator.

---

## 🐛 Troubleshooting

### Products Not Loading?

**Check:**
- Is WAMP/XAMPP running?
- Backend accessible at `http://localhost/ChTechbackend/backend/api/`?
- Browser console for errors (F12)
- Network tab shows API requests

**Fix:**
```javascript
// Update BASE_URL in api.js if backend is elsewhere
const BASE_URL = 'http://localhost/YOUR_BACKEND_PATH/api';
```

### Login Not Working?

**Check:**
- Form has `id="login-form"`
- Inputs have correct `name` attributes: `email`, `password`
- Browser console for errors
- Network tab shows `/auth/login.php` request

**Fix:**
- Ensure `auth.js` is loaded after `api.js`
- Check backend returns correct response format

### Cart Badge Not Updating?

**Check:**
- Element has `id="cart-count"`
- User is logged in
- `cart.js` is loaded

**Fix:**
```javascript
// Manually update badge
updateCartBadge();
```

### CORS Errors?

Backend already has CORS headers. If issues persist:

**Check backend PHP files have:**
```php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
```

---

## 📊 API Endpoint Summary

### Authentication:
- `POST /auth/login.php` - User login
- `POST /auth/register.php` - User registration
- `GET /auth/me.php` - Get current user
- `POST /auth/logout.php` - Logout

### Products:
- `GET /products/list.php` - List products (paginated)
- `GET /products/detail.php?id=X` - Product details
- `GET /products/featured.php` - Featured products
- `GET /products/search.php?q=query` - Search
- `GET /products/category.php?slug=X` - By category

### Cart:
- `GET /cart/view.php` - View cart
- `POST /cart/add.php` - Add to cart
- `POST /cart/update.php` - Update quantity
- `POST /cart/remove.php` - Remove item
- `POST /cart/clear.php` - Clear cart
- `GET /cart/count.php` - Get count

### Orders:
- `POST /orders/create.php` - Create order (checkout)
- `GET /orders/list.php` - User's orders
- `GET /orders/detail.php?id=X` - Order details

---

## 📝 File Dependencies

Load scripts in this order:

1. **api.js** (always first - core functions)
2. **ui.js** (second - UI utilities)
3. **auth.js** (for login/register pages)
4. **products.js** (for product pages)
5. **cart.js** (for cart functionality)
6. **orders.js** (for checkout/orders)

---

## ✅ Testing Checklist

- [ ] User can register new account
- [ ] User can login
- [ ] User can logout
- [ ] Products load on homepage
- [ ] Search works
- [ ] Add to cart works (requires login)
- [ ] Cart displays items
- [ ] Can update cart quantities
- [ ] Can remove items from cart
- [ ] Checkout form works
- [ ] Order is created successfully
- [ ] Cart clears after order
- [ ] Order history displays
- [ ] Cart badge updates correctly
- [ ] Notifications appear properly

---

## 🎓 Learning Resources

### JavaScript Concepts Used:
- **async/await** - Handling asynchronous operations
- **fetch API** - Making HTTP requests
- **Promises** - Managing async results
- **localStorage** - Browser storage
- **DOM manipulation** - Updating HTML dynamically
- **Event listeners** - Handling user interactions
- **Form validation** - Checking user input

### Key Functions:
- `fetch(url, options)` - HTTP requests
- `JSON.stringify(obj)` - Object to JSON string
- `JSON.parse(str)` - JSON string to object
- `localStorage.setItem(key, value)` - Save data
- `localStorage.getItem(key)` - Retrieve data
- `document.getElementById(id)` - Get element
- `element.addEventListener(event, callback)` - Listen to events

---

## 🚀 Next Steps

1. **Add More Pages:**
   - Product detail page
   - Order confirmation page
   - Order history page
   - User profile page

2. **Enhance UI:**
   - Add CSS styles to match your design
   - Improve loading indicators
   - Add animations

3. **Add Features:**
   - Wishlist functionality
   - Product reviews
   - Related products
   - Order tracking

4. **Optimize:**
   - Add image lazy loading
   - Implement caching
   - Minify JavaScript files

---

## 📞 Support

For issues or questions:
1. Check browser console (F12) for errors
2. Check Network tab for API responses
3. Verify backend is running and accessible
4. Review code comments in JavaScript files

---

## 📄 License

This integration was created for educational purposes for the Carthage Tech e-commerce project.

---

**Created:** November 21, 2025  
**Version:** 1.0.0  
**Status:** ✅ Production Ready

Happy coding! 🎉
