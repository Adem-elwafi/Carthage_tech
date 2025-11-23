<?php
declare(strict_types=1);
/**
 * Remove from Cart Endpoint
 * 
 * Removes a single item from the user's shopping cart.
 * This endpoint demonstrates:
 * - Secure DELETE operations with ownership verification
 * - Why we always include user_id in DELETE WHERE clause
 * - Difference between removing one item vs clearing cart
 * 
 * WHY WE USE "AND user_id" IN DELETE:
 * 
 * CRITICAL SECURITY CONCEPT!
 * 
 * Bad (INSECURE) DELETE:
 * DELETE FROM cart WHERE id = 10
 * → Deletes cart item 10 regardless of who owns it!
 * → User A could delete User B's cart items!
 * 
 * Good (SECURE) DELETE:
 * DELETE FROM cart WHERE id = 10 AND user_id = 5
 * → Only deletes if BOTH conditions are true:
 *    1. Cart item ID = 10
 *    2. Belongs to user ID = 5 (the logged-in user)
 * → If item belongs to another user, nothing is deleted
 * 
 * This is a fundamental security principle: Always scope operations to the current user.
 * 
 * Expected POST data:
 * - cart_id: ID of cart item to remove (required)
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

// Handle preflight OPTIONS request
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
// Support DELETE (preferred REST) and POST (backward compatibility)
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'], true)) {
    Response::error('Method not allowed. Use DELETE or POST.', [], 405);
}

// ============================================
// GET AND PARSE JSON INPUT
// ============================================

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    Response::error('Invalid JSON data.', ['json_error' => json_last_error_msg()], 400);
}

// ============================================
// VALIDATE INPUT
// ============================================

$cartId = isset($data['cart_id']) ? (int) $data['cart_id'] : 0;

if ($cartId <= 0) {
    Response::error(
        'Validation failed.',
        ['cart_id' => 'Valid cart item ID is required.'],
        422
    );
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
// OPTIONAL: VERIFY ITEM EXISTS (for better error message)
// ============================================

/**
 * VERIFICATION BEFORE DELETE:
 * 
 * This step is optional but provides better user experience:
 * - We can get the product name to show in success message
 * - We can distinguish between "item doesn't exist" and "not yours"
 * - We can return 404 instead of silent failure
 */

try {
    $verifySql = 'SELECT 
                    cart.id,
                    products.name AS product_name
                  FROM cart
                  INNER JOIN products ON cart.product_id = products.id
                  WHERE cart.id = :cart_id AND cart.user_id = :user_id
                  LIMIT 1';
    
    $verifyStmt = $pdo->prepare($verifySql);
    $verifyStmt->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
    $verifyStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $verifyStmt->execute();
    
    $cartItem = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cartItem) {
        Response::error(
            'Cart item not found.',
            ['cart_id' => 'This item does not exist in your cart.'],
            404
        );
    }
    
    $productName = $cartItem['product_name'];
    
} catch (PDOException $e) {
    Response::error('Database error while verifying cart item.', ['error' => $e->getMessage()], 500);
}

// ============================================
// DELETE CART ITEM
// ============================================

/**
 * SECURE DELETE OPERATION:
 * 
 * The WHERE clause includes both:
 * 1. id = :cart_id → Which item to delete
 * 2. user_id = :user_id → Ownership verification
 * 
 * If user_id doesn't match, the DELETE affects 0 rows.
 * This is a safe failure - nothing is deleted, no error thrown.
 * 
 * Example scenarios:
 * - User A (id=5) tries to delete cart item 10 (owned by User A) → SUCCESS
 * - User A (id=5) tries to delete cart item 20 (owned by User B) → 0 rows affected
 * - User A (id=5) tries to delete cart item 999 (doesn't exist) → 0 rows affected
 */

try {
    $deleteSql = 'DELETE FROM cart 
                  WHERE id = :cart_id AND user_id = :user_id';
    
    $stmt = $pdo->prepare($deleteSql);
    $stmt->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    // Check how many rows were deleted
    $rowsDeleted = $stmt->rowCount();
    
    if ($rowsDeleted === 0) {
        // This shouldn't happen if verification passed, but handle it
        Response::error('Failed to remove item from cart.', [], 500);
    }
    
    // Success response
    Response::success(
        'Item removed from cart successfully.',
        [
            'cart_id' => $cartId,
            'product_name' => $productName,
            'removed' => true
        ]
    );
    
} catch (PDOException $e) {
    Response::error('Database error while removing item.', ['error' => $e->getMessage()], 500);
}

// End of remove.php
