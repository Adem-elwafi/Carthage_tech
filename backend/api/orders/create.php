<?php
/**
 * CREATE ORDER ENDPOINT
 * 
 * This endpoint handles the checkout process:
 * 1. Takes cart items and converts them to an order
 * 2. Captures shipping information
 * 3. Calculates totals (subtotal, tax, total)
 * 4. Updates product stock quantities
 * 5. Clears the user's cart
 * 
 * CRITICAL CONCEPT: DATABASE TRANSACTIONS
 * 
 * A transaction is a sequence of database operations that must ALL succeed or ALL fail.
 * Think of it like a bank transfer: money must leave one account AND enter another.
 * If one fails, both must be cancelled (rolled back).
 * 
 * WHY TRANSACTIONS ARE CRITICAL FOR ORDERS:
 * 
 * Imagine this scenario WITHOUT transactions:
 * 1. ✅ Create order (success)
 * 2. ✅ Add order items (success)
 * 3. ❌ Update stock (fails - database error)
 * 4. Cart is cleared
 * 
 * RESULT: Order exists but stock wasn't decreased - you oversold!
 * Customer's cart is empty but order is incomplete. DISASTER!
 * 
 * WITH transactions:
 * 1. BEGIN TRANSACTION
 * 2. ✅ Create order
 * 3. ✅ Add order items
 * 4. ❌ Update stock (fails)
 * 5. ROLLBACK - undo everything
 * 
 */

declare(strict_types=1);

// CORS headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight OPTIONS request
// Browsers send this before actual POST to check if cross-origin request is allowed
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once '../../config/database.php';
require_once '../../utils/Response.php';
require_once '../../middleware/auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed. Use POST.', null, 405);
}

// STEP 1: Authenticate user
// User must be logged in to create an order
$user = requireAuth();
$userId = $user['id'];

// STEP 2: Parse JSON request body
// Frontend sends order data as JSON (shipping address, payment method, etc.)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if JSON is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    Response::error('Invalid JSON data.', ['error' => json_last_error_msg()]);
}

// STEP 3: Validate required fields
$errors = [];

// Shipping address fields are required
if (empty($data['shipping_address'])) {
    $errors['shipping_address'] = 'Shipping address is required.';
}

if (empty($data['shipping_city'])) {
    $errors['shipping_city'] = 'Shipping city is required.';
}

if (empty($data['shipping_postal_code'])) {
    $errors['shipping_postal_code'] = 'Postal code is required.';
}

// Payment method is optional, default to 'cash_on_delivery'
$paymentMethod = $data['payment_method'] ?? 'cash_on_delivery';

// Valid payment methods
$validPaymentMethods = ['cash_on_delivery', 'bank_transfer', 'card'];
if (!in_array($paymentMethod, $validPaymentMethods)) {
    $errors['payment_method'] = 'Invalid payment method. Must be: cash_on_delivery, bank_transfer, or card.';
}

// If validation errors exist, return them
if (!empty($errors)) {
    Response::error('Validation failed.', $errors, 400);
}

// STEP 4: Get database connection
try {
    $pdo = getDB();
} catch (Exception $e) {
    Response::error('Database connection failed.', ['error' => $e->getMessage()], 500);
}

/**
 * MAIN TRANSACTION BLOCK
 * 
 * Everything inside this try block either:
 * - ALL succeeds (commit) - order is created successfully
 * - Or ALL fails (rollback) - database returns to original state
 * 
 * No partial success is possible with transactions!
 */
try {
    // ============================================
    // BEGIN TRANSACTION
    // ============================================
    // From this point, all database changes are temporary until we call commit()
    $pdo->beginTransaction();
    
    // ============================================
    // STEP 5: Get cart items with product details
    // ============================================
    // We need to know:
    // - What products are in cart
    // - Current price of each product (this becomes price_at_purchase)
    // - Quantity of each item
    // - Available stock for each product
    
    $stmtCart = $pdo->prepare("
        SELECT 
            c.id as cart_id,
            c.product_id,
            c.quantity,
            p.name as product_name,
            p.price,
            p.stock_quantity
        FROM cart c
        INNER JOIN products p ON c.product_id = p.id
        WHERE c.user_id = :user_id
    ");
    $stmtCart->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmtCart->execute();
    $cartItems = $stmtCart->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if cart is empty
    if (empty($cartItems)) {
        // Rollback transaction (though nothing changed yet)
        $pdo->rollBack();
        Response::error('Cart is empty. Add products to cart before creating an order.', null, 400);
    }
    
    // ============================================
    // STEP 6: Validate stock availability
    // ============================================
    // Before creating order, ensure we have enough stock for all items
    // This prevents overselling (selling more than we have)
    
    $stockErrors = [];
    foreach ($cartItems as $item) {
        if ($item['quantity'] > $item['stock_quantity']) {
            $stockErrors[] = [
                'product_name' => $item['product_name'],
                'requested' => $item['quantity'],
                'available' => $item['stock_quantity']
            ];
        }
    }
    
    // If any item has insufficient stock, rollback and return error
    if (!empty($stockErrors)) {
        $pdo->rollBack();
        Response::error(
            'Insufficient stock for one or more items.',
            [
                'items_with_insufficient_stock' => $stockErrors,
                'message' => 'Please reduce quantities or remove items.'
            ],
            400
        );
    }
    
    // ============================================
    // STEP 7: Calculate order totals
    // ============================================
    // Subtotal = sum of (price × quantity) for all items
    // Tax = subtotal × 0.19 (19% TVA in Tunisia)
    // Total = subtotal + tax
    
    $subtotal = 0.0;
    foreach ($cartItems as $item) {
        $itemTotal = floatval($item['price']) * intval($item['quantity']);
        $subtotal += $itemTotal;
    }
    
    // Calculate tax (19% TVA)
    $taxRate = 0.19;
    $taxAmount = $subtotal * $taxRate;
    
    // Calculate total
    $totalPrice = $subtotal + $taxAmount;
    
    // ============================================
    // STEP 8: Generate unique order number
    // ============================================
    // Format: CT + YYYYMMDD + 4 random digits
    // Example: CT202511210001, CT202511215847
    // 
    // Why unique order numbers?
    // - Easy for customers to reference ("My order is CT202511210001")
    // - Easy to search in database
    // - Professional appearance
    // - Can encode information (date, store location, etc.)
    
    $orderNumber = 'CT' . date('Ymd') . str_pad((string)rand(0, 9999), 4, '0', STR_PAD_LEFT);
    
    // Note: In production, you should check if order_number already exists
    // and regenerate if duplicate. For simplicity, we're using random numbers.
    
    // ============================================
    // STEP 9: Create order record
    // ============================================
    // Insert into orders table with all calculated values
    
    $stmtOrder = $pdo->prepare("
        INSERT INTO orders (
            user_id,
            order_number,
            subtotal,
            tax_amount,
            total_price,
            status,
            payment_method,
            payment_status,
            shipping_address,
            shipping_city,
            shipping_postal_code
        ) VALUES (
            :user_id,
            :order_number,
            :subtotal,
            :tax_amount,
            :total_price,
            'pending',
            :payment_method,
            'pending',
            :shipping_address,
            :shipping_city,
            :shipping_postal_code
        )
    ");
    
    $stmtOrder->execute([
        ':user_id' => $userId,
        ':order_number' => $orderNumber,
        ':subtotal' => $subtotal,
        ':tax_amount' => $taxAmount,
        ':total_price' => $totalPrice,
        ':payment_method' => $paymentMethod,
        ':shipping_address' => $data['shipping_address'],
        ':shipping_city' => $data['shipping_city'],
        ':shipping_postal_code' => $data['shipping_postal_code']
    ]);
    
    // Get the ID of the order we just created
    // LAST_INSERT_ID() returns the auto-increment ID from the last INSERT
    $orderId = (int) $pdo->lastInsertId();
    
    // ============================================
    // STEP 10: Copy cart items to order_items
    // ============================================
    // Why copy to order_items?
    // - Cart is temporary (cleared after order)
    // - Order items are permanent (historical record)
    // - We need to preserve order details forever
    
    // IMPORTANT: We store price_at_purchase (not just product_id)
    // WHY? Product prices can change over time!
    // 
    // Scenario without price_at_purchase:
    // - Customer orders laptop for $1000 (today)
    // - Next week, laptop price changes to $1200
    // - Order history shows $1200 (WRONG! They paid $1000)
    // - Accounting nightmare!
    // 
    // With price_at_purchase:
    // - We store $1000 in order_items
    // - Even if product price changes, order shows correct historical price
    // - Accounting is accurate
    
    $stmtOrderItem = $pdo->prepare("
        INSERT INTO order_items (
            order_id,
            product_id,
            quantity,
            price_at_purchase
        ) VALUES (
            :order_id,
            :product_id,
            :quantity,
            :price_at_purchase
        )
    ");
    
    foreach ($cartItems as $item) {
        $stmtOrderItem->execute([
            ':order_id' => $orderId,
            ':product_id' => $item['product_id'],
            ':quantity' => $item['quantity'],
            ':price_at_purchase' => $item['price']  // Store current price
        ]);
    }
    
    // ============================================
    // STEP 11: Update product stock quantities
    // ============================================
    // Decrease stock by ordered quantity
    // This prevents overselling
    
    $stmtUpdateStock = $pdo->prepare("
        UPDATE products 
        SET stock_quantity = stock_quantity - :quantity 
        WHERE id = :product_id
    ");
    
    foreach ($cartItems as $item) {
        $stmtUpdateStock->execute([
            ':quantity' => $item['quantity'],
            ':product_id' => $item['product_id']
        ]);
        
        // Additional check: Ensure stock didn't go negative
        // This shouldn't happen (we validated earlier), but double-check for safety
        $stmtCheckStock = $pdo->prepare("
            SELECT stock_quantity FROM products WHERE id = :product_id
        ");
        $stmtCheckStock->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
        $stmtCheckStock->execute();
        $remainingStock = $stmtCheckStock->fetchColumn();
        
        if ($remainingStock < 0) {
            // This should never happen, but if it does, rollback everything
            throw new Exception("Stock went negative for product ID {$item['product_id']}. This should not happen.");
        }
    }
    
    // ============================================
    // STEP 12: Clear user's cart
    // ============================================
    // Order is created successfully, cart is no longer needed
    
    $stmtClearCart = $pdo->prepare("
        DELETE FROM cart WHERE user_id = :user_id
    ");
    $stmtClearCart->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmtClearCart->execute();
    
    // ============================================
    // COMMIT TRANSACTION
    // ============================================
    // All operations succeeded! Make changes permanent.
    // After commit():
    // - Order is saved in database
    // - Order items are saved
    // - Product stock is updated
    // - Cart is cleared
    // - Changes are permanent and visible to all users
    
    $pdo->commit();
    
    // ============================================
    // SUCCESS RESPONSE
    // ============================================
    // Return order details to frontend
    
    Response::success(
        'Order created successfully.',
        [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'subtotal' => number_format($subtotal, 2),
            'tax_amount' => number_format($taxAmount, 2),
            'tax_rate' => ($taxRate * 100) . '%',
            'total_price' => number_format($totalPrice, 2),
            'status' => 'pending',
            'payment_method' => $paymentMethod,
            'items_count' => count($cartItems),
            'shipping_info' => [
                'address' => $data['shipping_address'],
                'city' => $data['shipping_city'],
                'postal_code' => $data['shipping_postal_code']
            ],
            'message' => 'Your order has been placed. Order number: ' . $orderNumber
        ],
        201
    );
    
} catch (Exception $e) {
    // ============================================
    // ROLLBACK ON ERROR
    // ============================================
    // Something went wrong! Undo ALL changes.
    // 
    // Common errors that trigger rollback:
    // - Database connection lost during transaction
    // - SQL syntax error in one of the queries
    // - Constraint violation (foreign key, unique, etc.)
    // - Deadlock (two transactions blocking each other)
    // - Stock went negative (our custom check)
    // 
    // What rollback does:
    // - Undoes ALL database changes since beginTransaction()
    // - Database returns to exact state before transaction started
    // - It's like the transaction never happened
    // 
    // Why this is critical:
    // - Prevents partial orders (order without items)
    // - Prevents stock errors (order created but stock not updated)
    // - Maintains data integrity
    // - Customer can try again
    
    // Check if transaction is active before rolling back
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error for debugging (in production, use proper logging)
    error_log("Order creation failed for user $userId: " . $e->getMessage());
    
    Response::error(
        'Order creation failed. Please try again.',
        [
            'error' => $e->getMessage(),
            'message' => 'Your cart has not been modified. Please try placing the order again.'
        ],
        500
    );
}

/**
 * TRANSACTION BEST PRACTICES:
 * 
 * 1. Keep transactions SHORT
 *    - Don't include slow operations (email sending, API calls)
 *    - Only include database operations that must be atomic
 * 
 * 2. Always use try-catch
 *    - Catch exceptions and rollback
 *    - Never leave transaction uncommitted
 * 
 * 3. Check inTransaction() before rollback
 *    - Prevents "there is no active transaction" error
 * 
 * 4. Be careful with nested transactions
 *    - PDO doesn't support nested transactions
 *    - Use savepoints for complex scenarios
 * 
 * 5. Validate BEFORE transaction when possible
 *    - Check cart not empty before beginTransaction()
 *    - Reduces unnecessary transactions
 * 
 * WHEN TO USE TRANSACTIONS:
 * ✅ Creating orders (this file)
 * ✅ Transferring money between accounts
 * ✅ Updating related records that must stay consistent
 * ✅ Inventory management (stock updates)
 * 
 * WHEN NOT TO USE TRANSACTIONS:
 * ❌ Simple single INSERT/UPDATE operations
 * ❌ Read-only operations (SELECT queries)
 * ❌ Operations that include external API calls
 * ❌ Long-running batch processes (lock contention)
 */
