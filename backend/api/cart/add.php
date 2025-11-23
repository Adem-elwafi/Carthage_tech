<?php
declare(strict_types=1);
/**
 * Add to Cart Endpoint
 * 
 * Adds a product to the user's shopping cart or updates quantity if already exists.
 * This endpoint demonstrates:
 * - JSON POST data handling in PHP
 * - Duplicate entry handling (product already in cart)
 * - Stock validation (prevent ordering more than available)
 * - Database transactions for data integrity
 * 
 * HOW JSON POST DATA WORKS IN PHP:
 * 
 * Unlike form data ($_POST), JSON data needs special handling:
 * 1. file_get_contents('php://input') - reads raw request body
 * 2. json_decode(..., true) - converts JSON string to PHP array
 * 3. Access data like normal array: $data['product_id']
 * 
 * Frontend example:
 * fetch('/cart/add.php', {
 *   method: 'POST',
 *   headers: { 'Content-Type': 'application/json' },
 *   body: JSON.stringify({ product_id: 5, quantity: 2 })
 * });
 * 
 * Expected POST data:
 * - product_id: ID of product to add (required)
 * - quantity: How many to add (optional, default 1)
 */

// ============================================
// CORS AND HEADERS CONFIGURATION (Unified)
// ============================================
// For credentialed requests, origin must be explicit (no *)
$allowedOrigin = 'http://localhost'; // Adjust if frontend runs on a different origin
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed. Please use POST request.', [], 405);
}

// ============================================
// GET AND PARSE JSON INPUT
// ============================================

/**
 * PARSING JSON POST DATA:
 * 
 * Step 1: Read raw request body
 * Step 2: Decode JSON to PHP array
 * Step 3: Validate the data
 */

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if JSON is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    Response::error('Invalid JSON data.', ['json_error' => json_last_error_msg()], 400);
}

// ============================================
// EXTRACT AND VALIDATE INPUT
// ============================================

$productId = isset($data['product_id']) ? (int) $data['product_id'] : 0;
$quantity = isset($data['quantity']) ? (int) $data['quantity'] : 1;

$errors = [];

// Validate product ID
if ($productId <= 0) {
    $errors['product_id'] = 'Valid product ID is required.';
}

// Validate quantity
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
// VERIFY PRODUCT EXISTS AND HAS STOCK
// ============================================

/**
 * STOCK VALIDATION:
 * 
 * Before adding to cart, we must check:
 * 1. Does the product exist?
 * 2. Is there enough stock available?
 * 
 * This prevents users from ordering unavailable products.
 */

try {
    $productSql = 'SELECT id, name, price, stock_quantity 
                   FROM products 
                   WHERE id = :product_id 
                   LIMIT 1';
    
    $stmt = $pdo->prepare($productSql);
    $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
    $stmt->execute();
    
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        Response::error('Product not found.', ['product_id' => 'Product does not exist.'], 404);
    }
    
    $stockAvailable = (int) $product['stock_quantity'];
    
    // Check if there's enough stock
    if ($quantity > $stockAvailable) {
        Response::error(
            'Insufficient stock.',
            [
                'quantity' => "Only $stockAvailable unit(s) available in stock.",
                'available' => $stockAvailable,
                'requested' => $quantity
            ],
            400
        );
    }
    
} catch (PDOException $e) {
    Response::error('Database error while checking product.', ['error' => $e->getMessage()], 500);
}

// ============================================
// CHECK IF PRODUCT ALREADY IN CART
// ============================================

/**
 * DUPLICATE HANDLING STRATEGY:
 * 
 * Two approaches:
 * 
 * Approach 1 (Used here): Check then Update/Insert
 * - SELECT to check if exists
 * - UPDATE if exists (add to quantity)
 * - INSERT if doesn't exist
 * 
 * Approach 2: INSERT...ON DUPLICATE KEY UPDATE
 * - Single query
 * - Requires UNIQUE constraint on (user_id, product_id)
 * - More efficient but requires proper schema
 * 
 * Example for Approach 2:
 * INSERT INTO cart (user_id, product_id, quantity) 
 * VALUES (5, 10, 2)
 * ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
 */

try {
    $checkSql = 'SELECT id, quantity 
                 FROM cart 
                 WHERE user_id = :user_id AND product_id = :product_id 
                 LIMIT 1';
    
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $checkStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $existingItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingItem) {
        // ============================================
        // PRODUCT ALREADY IN CART - UPDATE QUANTITY
        // ============================================
        
        $currentQuantity = (int) $existingItem['quantity'];
        $newQuantity = $currentQuantity + $quantity;
        
        // Check if new quantity exceeds stock
        if ($newQuantity > $stockAvailable) {
            Response::error(
                'Cannot add that many items.',
                [
                    'message' => "You already have $currentQuantity in cart. Maximum available: $stockAvailable",
                    'current_in_cart' => $currentQuantity,
                    'trying_to_add' => $quantity,
                    'would_be' => $newQuantity,
                    'stock_available' => $stockAvailable
                ],
                400
            );
        }
        
        // Update quantity
        $updateSql = 'UPDATE cart 
                      SET quantity = :quantity, updated_at = NOW() 
                      WHERE id = :cart_id AND user_id = :user_id';
        
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->bindValue(':quantity', $newQuantity, PDO::PARAM_INT);
        $updateStmt->bindValue(':cart_id', (int) $existingItem['id'], PDO::PARAM_INT);
        $updateStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $updateStmt->execute();
        
        Response::success(
            'Cart updated successfully.',
            [
                'action' => 'updated',
                'product' => [
                    'id' => $productId,
                    'name' => $product['name']
                ],
                'previous_quantity' => $currentQuantity,
                'added_quantity' => $quantity,
                'new_quantity' => $newQuantity
            ]
        );
        
    } else {
        // ============================================
        // PRODUCT NOT IN CART - INSERT NEW ITEM
        // ============================================
        
        $insertSql = 'INSERT INTO cart (user_id, product_id, quantity, created_at, updated_at) 
                      VALUES (:user_id, :product_id, :quantity, NOW(), NOW())';
        
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $insertStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $insertStmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $insertStmt->execute();
        
        Response::success(
            'Product added to cart successfully.',
            [
                'action' => 'added',
                'product' => [
                    'id' => $productId,
                    'name' => $product['name'],
                    'price' => number_format((float) $product['price'], 2, '.', '')
                ],
                'quantity' => $quantity
            ]
        );
    }
    
} catch (PDOException $e) {
    Response::error('Database error while updating cart.', ['error' => $e->getMessage()], 500);
}

// End of add.php
