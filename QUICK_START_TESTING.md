# Quick Start Guide - Testing Your Integration

## 🚀 5-Minute Setup

### Step 1: Verify Backend is Running

1. Start WAMP/XAMPP
2. Open browser and go to: `http://localhost/ChTechbackend/backend/api/products/list.php`
3. You should see JSON response with products

If you see an error, check:
- WAMP/XAMPP is running (green icon)
- Backend path is correct
- Database is imported and configured

---

### Step 2: Update API URL (if needed)

Open `pages/js/api.js` and check line 23:

```javascript
const BASE_URL = 'http://localhost/ChTechbackend/backend/api';
```

Change if your backend is at a different location.

---

### Step 3: Open the Site

Navigate to your project folder and open:
```
pages/index.html
```

Or start a local server (recommended):

**Using Python:**
```bash
cd pages
python -m http.server 8000
```
Then open: `http://localhost:8000`

**Using PHP:**
```bash
cd pages
php -S localhost:8000
```
Then open: `http://localhost:8000`

**Using VS Code Live Server:**
- Install "Live Server" extension
- Right-click `index.html` → "Open with Live Server"

---

## ✅ Testing Checklist

### Test 1: Registration

1. Go to `register.html`
2. Fill in form:
   - First Name: John
   - Last Name: Doe
   - Email: test@example.com
   - Phone: 12345678 (8 digits)
   - Password: test123
   - Confirm Password: test123
3. Click "Create Account"
4. Should see success message
5. Should redirect to login page

**Expected:** Account created, redirected to login

---

### Test 2: Login

1. On `login.html`, enter:
   - Email: test@example.com
   - Password: test123
2. Click "Login"
3. Should see success message
4. Should redirect to homepage
5. Header should show "Hello, John!" and Logout button

**Expected:** Logged in, UI updated

---

### Test 3: View Products

1. On homepage, check if products load
2. If no products container exists, create one:

```html
<div id="featured-products"></div>

<script src="js/api.js"></script>
<script src="js/ui.js"></script>
<script src="js/products.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        loadFeaturedProducts('featured', 8, 'featured-products');
    });
</script>
```

**Expected:** Products displayed with images, prices, "Add to Cart" buttons

---

### Test 4: Add to Cart

1. Click "Add to Cart" on any product
2. Should see notification: "Product added to cart!"
3. Cart badge should update (show "1")

**Expected:** Notification shown, badge updated

---

### Test 5: View Cart

1. Click on cart link (with badge)
2. Should see cart page with item
3. Should show:
   - Product image and name
   - Price
   - Quantity controls
   - Subtotal
   - Cart summary with total

**Expected:** Cart page displays items correctly

---

### Test 6: Update Cart

1. On cart page, click "+" button on quantity
2. Should see "Cart updated successfully" notification
3. Quantity should increase
4. Subtotal should update

**Expected:** Quantity and prices update

---

### Test 7: Checkout

1. On cart page, click "Proceed to Checkout"
2. Should go to `checkout.html`
3. Fill in shipping form:
   - Address: 123 Main Street, Apartment 4B
   - City: Tunis
   - Postal Code: 1000
   - Payment Method: Cash on Delivery
4. Click "Place Order"
5. Should see success notification
6. Should redirect to order confirmation

**Expected:** Order created successfully

---

### Test 8: Logout

1. Click "Logout" button in header
2. Should see confirmation dialog
3. Click OK
4. Should redirect to login page
5. Header should show "Login" and "Register" buttons again

**Expected:** Logged out, UI updated

---

## 🐛 Common Issues & Fixes

### Issue: "Failed to load products"

**Cause:** Backend not accessible

**Fix:**
1. Check WAMP/XAMPP is running
2. Test backend directly: `http://localhost/ChTechbackend/backend/api/products/list.php`
3. Check BASE_URL in `api.js`

---

### Issue: "Please log in first"

**Cause:** Not authenticated

**Fix:**
1. Make sure you're logged in
2. Check localStorage: Open DevTools (F12) → Application → Local Storage
3. Should see "user" key with user data
4. If not there, log in again

---

### Issue: Cart badge shows "0" but items are in cart

**Cause:** Badge not updating

**Fix:**
Open browser console (F12) and run:
```javascript
updateCartBadge();
```

Or reload the page.

---

### Issue: "CORS error" in console

**Cause:** CORS not configured properly

**Fix:**
1. Check backend PHP files have CORS headers
2. Make sure `credentials: 'include'` is in fetch calls
3. Use same domain for frontend and backend (both localhost)

---

### Issue: Login/Register form not submitting

**Cause:** JavaScript not loaded or form IDs wrong

**Fix:**
1. Check browser console for errors
2. Verify scripts are loaded:
   ```html
   <script src="js/api.js"></script>
   <script src="js/auth.js"></script>
   <script src="js/ui.js"></script>
   ```
3. Check form has correct ID:
   - Login: `id="login-form"`
   - Register: `id="register-form"`

---

### Issue: Notifications not showing

**Cause:** showNotification function not working

**Fix:**
1. Make sure `ui.js` is loaded
2. Check browser console for errors
3. Manually test:
   ```javascript
   showNotification('Test message', 'success');
   ```

---

## 🔍 Debugging Tips

### Open Browser DevTools (F12)

**Console Tab:**
- See JavaScript errors
- Test functions manually
- View API responses

**Network Tab:**
- See all API requests
- Check request/response data
- Find failed requests (red)

**Application Tab:**
- View localStorage data
- See cookies
- Check session storage

### Test API Directly

Use browser to test endpoints:

```
http://localhost/ChTechbackend/backend/api/products/list.php
http://localhost/ChTechbackend/backend/api/auth/me.php
http://localhost/ChTechbackend/backend/api/cart/view.php
```

Should return JSON responses.

### Check JavaScript Errors

Open console (F12) and look for:
- Red error messages
- "404 Not Found" errors
- "Uncaught ReferenceError" errors

---

## 📱 Test on Different Browsers

Test your integration on:
- ✅ Chrome/Edge (recommended)
- ✅ Firefox
- ✅ Safari (Mac)
- ✅ Mobile browsers

---

## 🎯 Next Steps After Testing

Once everything works:

1. **Style Your Pages:**
   - Add CSS to cart.html
   - Style checkout.html
   - Improve notifications appearance

2. **Add More Features:**
   - Product detail page
   - Order history page
   - User profile page
   - Search functionality

3. **Optimize:**
   - Add loading spinners
   - Improve error messages
   - Add form validation hints

4. **Deploy:**
   - Upload to web server
   - Update BASE_URL to production URL
   - Test on live server

---

## 💡 Pro Tips

1. **Always check the console first** when something doesn't work
2. **Test with real data** - create multiple products, orders, etc.
3. **Clear localStorage** if you encounter session issues (DevTools → Application → Local Storage → Clear)
4. **Use network throttling** to test slow connections (DevTools → Network → Throttling)
5. **Test error scenarios** - try wrong passwords, empty forms, etc.

---

## 📞 Need Help?

If you're stuck:

1. Check the console for errors
2. Review the main README: `INTEGRATION_README.md`
3. Check the backend API responses in Network tab
4. Look at the code comments in JavaScript files
5. Test each function individually in console

---

**Happy Testing! 🎉**

If all tests pass, your integration is working perfectly and you're ready to build more features!
