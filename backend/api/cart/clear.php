<?php
declare(strict_types=1);
/**
 * Clear Cart Endpoint
 * 
 * Removes all items from the user's shopping cart.
 * This is useful for:
 * - "Empty cart" button in UI
 * - After successful order placement
 * - User wants to start fresh
 * 
 * DIFFERENCE BETWEEN remove.php AND clear.php:
 * 
 * remove.php:
 * - Removes ONE specific item
 * - Requires cart_id parameter
 * - DELETE FROM cart WHERE id = :cart_id AND user_id = :user_id
 * 
 * clear.php:
 * - Removes ALL items for the user
 * - No parameters needed
 * - DELETE FROM cart WHERE user_id = :user_id
 * 
 * This endpoint only accepts POST (not GET) to prevent accidental clearing
 * via URL visits or browser prefetch.
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
// COUNT ITEMS BEFORE CLEARING (Optional)
// ============================================
// Get count to show in success message

try {
    $countSql = 'SELECT COUNT(*) as item_count, SUM(quantity) as total_quantity 
                 FROM cart 
                 WHERE user_id = :user_id';
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $countStmt->execute();
    
    $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $itemCount = (int) $countResult['item_count'];
    $totalQuantity = (int) $countResult['total_quantity'];
    
} catch (PDOException $e) {
    // If count fails, continue with clearing (not critical)
    $itemCount = 0;
    $totalQuantity = 0;
}

// ============================================
// CLEAR ALL CART ITEMS FOR USER
// ============================================

/**
 * SIMPLE DELETE ALL FOR USER:
 * 
 * DELETE FROM cart WHERE user_id = :user_id
 * 
 * This removes ALL rows where user_id matches the logged-in user.
 * It's safe because we're only deleting the current user's data.
 * 
 * Note: We don't need to specify individual cart item IDs.
 * The WHERE user_id = :user_id ensures we only delete this user's items.
 */

try {
    $deleteSql = 'DELETE FROM cart WHERE user_id = :user_id';
    
    $stmt = $pdo->prepare($deleteSql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    // Number of rows deleted
    $rowsDeleted = $stmt->rowCount();
    
    // Build success message
    if ($rowsDeleted > 0) {
        $message = sprintf(
            'Cart cleared successfully. Removed %d item(s) (%d total quantity).',
            $itemCount,
            $totalQuantity
        );
    } else {
        $message = 'Cart was already empty.';
    }
    
    Response::success(
        $message,
        [
            'items_removed' => $rowsDeleted,
            'total_quantity_removed' => $totalQuantity,
            'cleared' => true
        ]
    );
    
} catch (PDOException $e) {
    Response::error('Database error while clearing cart.', ['error' => $e->getMessage()], 500);
}

// End of clear.php
