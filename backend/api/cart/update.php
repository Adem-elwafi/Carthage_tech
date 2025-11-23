<?php
declare(strict_types=1);
/**
 * Update Cart Item Endpoint
 * 
 * Updates the quantity of an existing cart item.
 * This endpoint demonstrates:
 * - Security: Verify cart item belongs to current user
 * - Quantity validation and stock checking
 * - Proper UPDATE query with WHERE conditions
 * 
 * WHY WE CHECK user_id IN THE WHERE CLAUSE:
 * 
 * SECURITY CRITICAL!
 * 
 * Without user_id check:
 * UPDATE cart SET quantity = 5 WHERE id = 10
 * → This would update ANY cart item with id=10, even if it belongs to another user!
 * 
 * With user_id check:
 * UPDATE cart SET quantity = 5 WHERE id = 10 AND user_id = 5
 * → This only updates the item if BOTH conditions are true:
 *    1. Cart item ID matches
 *    2. Cart item belongs to the logged-in user
 * 
 * This prevents users from modifying other users' carts by guessing IDs.
 * 
 * Expected POST data:
 * - cart_id: ID of cart item to update (required)
 * - quantity: New quantity (required, must be > 0)
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
// Support PUT (preferred REST) and POST (backward compatibility)
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'], true)) {
    Response::error('Method not allowed. Use PUT or POST.', [], 405);
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
$quantity = isset($data['quantity']) ? (int) $data['quantity'] : 0;

$errors = [];

if ($cartId <= 0) {
    $errors['cart_id'] = 'Valid cart item ID is required.';
}

if ($quantity <= 0) {
    $errors['quantity'] = 'Quantity must be at least 1.';
}

if (!empty($errors)) {
    Response::error('Validation failed.', $errors, 422);
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
// VERIFY CART ITEM EXISTS AND BELONGS TO USER
// ============================================

/**
 * SECURITY VERIFICATION:
 * 
 * Before updating, we must verify:
 * 1. The cart item exists
 * 2. It belongs to the current user (not someone else's cart)
 * 3. The associated product still exists
 * 
 * This is CRITICAL to prevent unauthorized access.
 */

try {
    $verifySql = 'SELECT 
                    cart.id,
                    cart.product_id,
                    cart.quantity AS current_quantity,
                    products.name AS product_name,
                    products.stock_quantity,
                    products.price
                  FROM cart
                  INNER JOIN products ON cart.product_id = products.id
                  WHERE cart.id = :cart_id AND cart.user_id = :user_id
                  LIMIT 1';
    
    $stmt = $pdo->prepare($verifySql);
    $stmt->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no result, either cart item doesn't exist or doesn't belong to user
    if (!$cartItem) {
        Response::error(
            'Cart item not found.',
            ['cart_id' => 'This cart item does not exist or does not belong to you.'],
            404
        );
    }
    
    // ============================================
    // VALIDATE STOCK AVAILABILITY
    // ============================================
    
    $stockAvailable = (int) $cartItem['stock_quantity'];
    
    if ($quantity > $stockAvailable) {
        Response::error(
            'Insufficient stock.',
            [
                'quantity' => "Only $stockAvailable unit(s) available in stock.",
                'requested' => $quantity,
                'available' => $stockAvailable
            ],
            400
        );
    }
    
    // ============================================
    // UPDATE CART ITEM QUANTITY
    // ============================================
    
    /**
     * SECURE UPDATE QUERY:
     * 
     * Notice the WHERE clause has TWO conditions:
     * - id = :cart_id (which item)
     * - user_id = :user_id (ownership verification)
     * 
     * Both must be true for the update to happen.
     * If someone tries to update another user's cart item,
     * the query will affect 0 rows (safe failure).
     */
    
    $updateSql = 'UPDATE cart 
                  SET quantity = :quantity, updated_at = NOW() 
                  WHERE id = :cart_id AND user_id = :user_id';
    
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
    $updateStmt->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
    $updateStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $updateStmt->execute();
    
    // Check if update was successful
    $rowsAffected = $updateStmt->rowCount();
    
    if ($rowsAffected === 0) {
        // This shouldn't happen if verification passed, but just in case
        Response::error('Failed to update cart item.', [], 500);
    }
    
    // Calculate new subtotal
    $newSubtotal = (float) $cartItem['price'] * $quantity;
    
    Response::success(
        'Cart item updated successfully.',
        [
            'cart_id' => $cartId,
            'product' => [
                'id' => (int) $cartItem['product_id'],
                'name' => $cartItem['product_name']
            ],
            'previous_quantity' => (int) $cartItem['current_quantity'],
            'new_quantity' => $quantity,
            'new_subtotal' => number_format($newSubtotal, 2, '.', '')
        ]
    );
    
} catch (PDOException $e) {
    Response::error('Database error while updating cart.', ['error' => $e->getMessage()], 500);
}

// End of update.php
