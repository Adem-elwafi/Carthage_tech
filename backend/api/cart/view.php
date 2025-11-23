<?php
declare(strict_types=1);
/**
 * View Shopping Cart Endpoint
 * 
 * Returns the current user's shopping cart with full product details.
 * This endpoint demonstrates:
 * - Authentication requirement (user must be logged in)
 * - Multi-table JOIN to enrich cart data with product information
 * - Aggregation calculations (subtotals, total)
 * - Security: Only show cart items for the logged-in user
 * 
 * WHY AUTHENTICATION IS REQUIRED:
 * Shopping carts are personal and contain sensitive purchasing information.
 * Each user should only see and modify their own cart. We use sessions to:
 * 1. Identify who is making the request
 * 2. Ensure users can't access other users' carts
 * 3. Track cart items persistently across page loads
 * 
 * Returns:
 * - Array of cart items with product details
 * - Subtotal for each item (price × quantity)
 * - Total cart value (sum of all subtotals)
 * - Total items count
 */

// ============================================
// CORS AND HEADERS CONFIGURATION (Unified)
// ============================================
$allowedOrigin = 'http://localhost';
header("Access-Control-Allow-Origin: $allowedOrigin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================
// REQUIRE AUTHENTICATION
// ============================================
/**
 * AUTHENTICATION CHECK:
 * 
 * The requireAuth() function from auth middleware:
 * 1. Checks if user has a valid session
 * 2. Returns user data if authenticated
 * 3. Sends 401 error and exits if not authenticated
 * 
 * After this line, we're guaranteed the user is logged in.
 */
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

// Get authenticated user info from session
$user = requireAuth();
$userId = (int) $user['id'];

// ============================================
// CHECK REQUEST METHOD
// ============================================
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed. Please use GET request.', [], 405);
}

// ============================================
// DATABASE CONNECTION
// ============================================
try {
    $pdo = getDatabaseConnection();
    
    if ($pdo === null) {
        Response::error('Database connection failed.', [], 500);
    }
    
} catch (Exception $e) {
    Response::error('Server error: Unable to connect to database.', [], 500);
}

// ============================================
// GET CART ITEMS WITH PRODUCT DETAILS
// ============================================

/**
 * MULTI-TABLE JOIN EXPLANATION:
 * 
 * We're joining three tables to get complete cart information:
 * 
 * cart table:
 * | id | user_id | product_id | quantity |
 * | 1  | 5       | 10         | 2        |
 * 
 * products table:
 * | id | name      | price  | image_url | stock_quantity |
 * | 10 | Laptop HP | 2499   | image.jpg | 15             |
 * 
 * After JOIN:
 * | cart.id | cart.quantity | products.name | products.price |
 * | 1       | 2             | Laptop HP     | 2499           |
 * 
 * This allows us to display product info in the cart without separate queries.
 * 
 * INNER JOIN vs LEFT JOIN:
 * - INNER JOIN: Only returns rows where product exists (preferred here)
 * - LEFT JOIN: Returns cart items even if product was deleted (NULL product data)
 * 
 * We use INNER JOIN because if a product is deleted, we want to remove it from carts too.
 */

$sql = 'SELECT 
            cart.id AS cart_id,
            cart.product_id,
            cart.quantity,
            cart.created_at AS added_at,
            products.name AS product_name,
            products.slug AS product_slug,
            products.price,
            products.image_url,
            products.stock_quantity,
            products.brand,
            (products.price * cart.quantity) AS subtotal
        FROM cart
        INNER JOIN products ON cart.product_id = products.id
        WHERE cart.user_id = :user_id
        ORDER BY cart.created_at DESC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // CALCULATE TOTALS
    // ============================================
    
    /**
     * CART CALCULATIONS:
     * 
     * For each item:
     * - Subtotal = price × quantity
     * 
     * For the whole cart:
     * - Total Items = SUM of all quantities
     * - Cart Total = SUM of all subtotals
     * 
     * Example:
     * Item 1: $100 × 2 qty = $200 subtotal
     * Item 2: $50 × 3 qty = $150 subtotal
     * ----------------------------------------
     * Total Items: 2 + 3 = 5 items
     * Cart Total: $200 + $150 = $350
     */
    
    $totalItems = 0;
    $cartTotal = 0.0;
    $formattedCartItems = [];
    
    foreach ($cartItems as $item) {
        // Calculate subtotal for this item
        $quantity = (int) $item['quantity'];
        $price = (float) $item['price'];
        $subtotal = $price * $quantity;
        
        // Add to totals
        $totalItems += $quantity;
        $cartTotal += $subtotal;
        
        // Format item data
        $formattedCartItems[] = [
            'cart_id' => (int) $item['cart_id'],
            'product' => [
                'id' => (int) $item['product_id'],
                'name' => $item['product_name'],
                'slug' => $item['product_slug'],
                'brand' => $item['brand'],
                'price' => number_format($price, 2, '.', ''),
                'price_numeric' => $price,
                'image_url' => $item['image_url']
            ],
            'quantity' => $quantity,
            'stock_available' => (int) $item['stock_quantity'],
            'in_stock' => (int) $item['stock_quantity'] > 0,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'subtotal_numeric' => $subtotal,
            'added_at' => $item['added_at']
        ];
    }
    
    // ============================================
    // BUILD RESPONSE
    // ============================================
    
    $responseData = [
        'cart_items' => $formattedCartItems,
        'summary' => [
            'total_items' => $totalItems,
            'total_unique_products' => count($formattedCartItems),
            'cart_total' => number_format($cartTotal, 2, '.', ''),
            'cart_total_numeric' => $cartTotal
        ],
        'user_id' => $userId
    ];
    
    $message = count($formattedCartItems) > 0
        ? sprintf('Cart retrieved successfully. You have %d item(s) in your cart.', $totalItems)
        : 'Your cart is empty.';
    
    Response::success($message, $responseData);
    
} catch (PDOException $e) {
    Response::error(
        'Database error while fetching cart.',
        ['error' => $e->getMessage()],
        500
    );
}

// End of view.php
