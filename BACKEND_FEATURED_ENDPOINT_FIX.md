# 🔧 Backend Fix: Add Featured Products Endpoint

## Issue
The "Nouveautés" and "Meilleures ventes" pages are showing "Failed to load featured products" because the backend is missing the `/products/featured.php` endpoint.

## ✅ Quick Fix Applied
I've updated the frontend to automatically fallback to `/products/list.php` with filters if `featured.php` doesn't exist. **This should work now**, but for better performance, add the proper endpoint.

---

## 🎯 PROMPT FOR BACKEND: Add Featured Products Endpoint

**Copy this and send to your backend developer:**

---

### Task: Create `/products/featured.php` Endpoint

**Location:** `ChTechbackend/backend/api/products/featured.php`

**Purpose:** Return featured, new, or bestseller products based on query parameters.

### PHP Code for `featured.php`:

```php
<?php
/**
 * Featured Products API Endpoint
 * Returns products filtered by is_featured, is_new, or is_bestseller flags
 * 
 * Query Parameters:
 * - type: 'featured', 'new', 'bestseller', or omit for all special products
 * - limit: Maximum number of products (default: 12)
 * 
 * Examples:
 * - /products/featured.php?type=new&limit=20
 * - /products/featured.php?type=bestseller
 * - /products/featured.php (returns all featured products)
 */

header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
require_once '../config/database.php';

// Get query parameters
$type = isset($_GET['type']) ? $_GET['type'] : null;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 12;

// Validate limit
if ($limit < 1 || $limit > 100) {
    $limit = 12;
}

try {
    // Build SQL query based on type
    $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.in_stock = 1";
    
    // Add filter based on type
    switch ($type) {
        case 'new':
            $sql .= " AND p.is_new = 1";
            break;
        case 'bestseller':
            $sql .= " AND p.is_bestseller = 1";
            break;
        case 'featured':
            $sql .= " AND p.is_featured = 1";
            break;
        default:
            // If no type specified, get all special products (featured, new, or bestseller)
            $sql .= " AND (p.is_featured = 1 OR p.is_new = 1 OR p.is_bestseller = 1)";
            break;
    }
    
    // Order by newest first
    $sql .= " ORDER BY p.created_at DESC LIMIT ?";
    
    // Prepare and execute
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit]);
    
    // Fetch products
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response
    $response = [
        'success' => true,
        'data' => [
            'products' => $products,
            'count' => count($products),
            'type' => $type,
            'limit' => $limit
        ]
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
}
?>
```

### How to Add This File:

1. **Navigate to your backend folder:**
   ```
   cd C:\xampp\htdocs\ChTechbackend\backend\api\products
   ```

2. **Create `featured.php`:**
   - Copy the code above
   - Save as `featured.php` in `/products/` folder

3. **Test the endpoint:**
   - Open browser: `http://localhost/ChTechbackend/backend/api/products/featured.php?type=new`
   - Should return JSON with products where `is_new = 1`

4. **Test other types:**
   - Bestsellers: `?type=bestseller`
   - Featured: `?type=featured`
   - All special: no type parameter

### Expected Response Format:

```json
{
  "success": true,
  "data": {
    "products": [
      {
        "id": 1,
        "name": "Dell XPS 15",
        "description": "...",
        "price": "1299.99",
        "category_id": 1,
        "category_name": "Ordinateurs",
        "category_slug": "ordinateurs",
        "brand": "Dell",
        "image_url": "...",
        "in_stock": 1,
        "is_featured": 0,
        "is_new": 1,
        "is_bestseller": 1,
        "created_at": "2025-11-21 10:00:00"
      }
    ],
    "count": 5,
    "type": "new",
    "limit": 12
  }
}
```

---

## Alternative: Modify Existing `list.php`

If you prefer not to create a new file, you can modify `/products/list.php` to accept these filters:

```php
// Add to existing list.php, after other filters
if (isset($_GET['is_new']) && $_GET['is_new'] == 1) {
    $conditions[] = "p.is_new = 1";
}

if (isset($_GET['is_bestseller']) && $_GET['is_bestseller'] == 1) {
    $conditions[] = "p.is_bestseller = 1";
}

if (isset($_GET['is_featured']) && $_GET['is_featured'] == 1) {
    $conditions[] = "p.is_featured = 1";
}
```

**Note:** The frontend already has a fallback to use `list.php` with these parameters, so this alternative should work immediately!

---

## ✅ Testing

After adding the endpoint:

1. **Open browser console (F12)**
2. **Navigate to:**
   - http://localhost/pages/nouveautes.html
   - http://localhost/pages/meilleures-ventes.html

3. **Check Network tab:**
   - Should see request to `featured.php?type=new` or `list.php?is_new=1`
   - Response should have `success: true` and array of products

4. **Verify products display:**
   - "Nouveautés" page should show products with `is_new = 1`
   - "Meilleures ventes" should show products with `is_bestseller = 1`

---

## 🔍 Troubleshooting

### Still seeing errors?

1. **Check database has flagged products:**
   ```sql
   SELECT COUNT(*) FROM products WHERE is_new = 1;
   SELECT COUNT(*) FROM products WHERE is_bestseller = 1;
   ```
   - If 0, add products using `ADD_PRODUCTS_GUIDE.md`

2. **Check CORS headers:**
   - Make sure `Access-Control-Allow-Origin` is set correctly
   - Must allow credentials if using sessions

3. **Check API response in browser:**
   - Visit: `http://localhost/ChTechbackend/backend/api/products/featured.php?type=new`
   - Should return JSON, not HTML error

4. **Browser console errors:**
   - Open F12 → Console tab
   - Look for red errors
   - Check Network tab for failed requests

---

## 📊 Current Status

✅ **Frontend:** Fixed with automatic fallback  
⚠️ **Backend:** Needs `featured.php` endpoint OR modify `list.php` to accept filters

The pages **should work now** with the fallback, but adding the proper endpoint is recommended for clarity and better error handling.
