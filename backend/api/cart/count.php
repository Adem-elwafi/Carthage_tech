<?php
declare(strict_types=1);
/**
 * Cart Count Endpoint
 * 
 * Returns the total number of items in the user's cart.
 * This is perfect for displaying a badge/counter on the cart icon in the UI.
 * 
 * COUNT(*) vs SUM(quantity) EXPLAINED:
 * 
 * Example cart contents:
 * | id | product_id | quantity |
 * | 1  | 10         | 2        |  (2 laptops)
 * | 2  | 15         | 3        |  (3 mice)
 * | 3  | 20         | 1        |  (1 keyboard)
 * 
 * COUNT(*):
 * - Counts the number of ROWS
 * - Result: 3 (three different products)
 * - Shows: "You have 3 items in cart"
 * 
 * SUM(quantity):
 * - Adds up all the quantity values
 * - Calculation: 2 + 3 + 1 = 6
 * - Result: 6 (total units of all products)
 * - Shows: "You have 6 items in cart" (more accurate)
 * 
 * Which to use?
 * - SUM(quantity) is better for cart badges (shows total items)
 * - COUNT(*) is better for "You have 3 different products"
 * 
 * This endpoint returns SUM(quantity) for accurate cart count.
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

// Handle preflight OPTIONS request once
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================
// REQUIRE AUTHENTICATION
// ============================================
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

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
// GET CART COUNT
// ============================================

/**
 * QUERY EXPLANATION:
 * 
 * SELECT 
 *   COUNT(*) as unique_products,
 *   COALESCE(SUM(quantity), 0) as total_items
 * 
 * COUNT(*):
 * - Number of different products in cart
 * 
 * SUM(quantity):
 * - Total quantity of all products
 * 
 * COALESCE(..., 0):
 * - If SUM returns NULL (empty cart), use 0 instead
 * - Prevents NULL in response (0 is clearer than NULL)
 */

try {
    $sql = 'SELECT 
                COUNT(*) as unique_products,
                COALESCE(SUM(quantity), 0) as total_items
            FROM cart
            WHERE user_id = :user_id';
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $uniqueProducts = (int) $result['unique_products'];
    $totalItems = (int) $result['total_items'];
    
    // ============================================
    // BUILD RESPONSE
    // ============================================
    
    /**
     * RESPONSE STRUCTURE:
     * 
     * We return both counts because they're useful for different purposes:
     * 
     * total_items (SUM):
     * - Use for cart badge: "🛒 6"
     * - Shows total units to purchase
     * 
     * unique_products (COUNT):
     * - Use for description: "3 different products"
     * - Shows variety in cart
     */
    
    Response::success(
        'Cart count retrieved successfully.',
        [
            'count' => $totalItems, // Main count for badge (sum of quantities)
            'total_items' => $totalItems, // Same as count (for clarity)
            'unique_products' => $uniqueProducts, // Number of different products
            'is_empty' => $totalItems === 0
        ]
    );
    
} catch (PDOException $e) {
    Response::error('Database error while counting cart items.', ['error' => $e->getMessage()], 500);
}

// End of count.php
