<?php
/**
 * ORDER DETAIL ENDPOINT
 * 
 * Returns complete details for a single order including:
 * - Order information (number, totals, status, dates)
 * - Shipping information
 * - Payment information
 * - All order items with product details
 * - Price at purchase (historical pricing)
 * 
 * CRITICAL SECURITY:
 * Users can ONLY view their OWN orders
 * Query must include: WHERE id = :order_id AND user_id = :user_id
 * 
 * Why this security check is critical:
 * Without it, a user could access any order by guessing order IDs:
 * - /api/orders/detail.php?id=1
 * - /api/orders/detail.php?id=2
 * - /api/orders/detail.php?id=3
 * ...and see other customers' orders, addresses, purchases!
 * 
 * This is called IDOR (Insecure Direct Object Reference) vulnerability.
 * Always validate that the user owns the resource they're accessing.
 */

declare(strict_types=1);

// CORS headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once '../../config/database.php';
require_once '../../utils/Response.php';
require_once '../../middleware/auth.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed. Use GET.', null, 405);
}

// Require authentication
$user = requireAuth();
$userId = $user['id'];

// ============================================
// VALIDATE REQUEST PARAMETERS
// ============================================

// Get order ID from query string
if (!isset($_GET['id']) || empty($_GET['id'])) {
    Response::error('Order ID is required.', ['parameter' => 'id'], 400);
}

$orderId = intval($_GET['id']);

if ($orderId <= 0) {
    Response::error('Invalid order ID.', ['parameter' => 'id'], 400);
}

// Get database connection
try {
    $pdo = getDB();
} catch (Exception $e) {
    Response::error('Database connection failed.', ['error' => $e->getMessage()], 500);
}

try {
    // ============================================
    // STEP 1: Get order details with security check
    // ============================================
    // SECURITY: WHERE clause includes BOTH id AND user_id
    // This ensures user can only access their own orders
    // 
    // Attack scenario without user_id check:
    // - Attacker logs in as user_id = 5
    // - Tries: /api/orders/detail.php?id=100
    // - If order 100 belongs to user_id = 8, attacker shouldn't see it
    // 
    // With user_id check:
    // - WHERE id = 100 AND user_id = 5
    // - Returns no results (order 100 doesn't belong to user 5)
    // - Attacker gets "Order not found" error
    
    $stmtOrder = $pdo->prepare("
        SELECT 
            id,
            order_number,
            subtotal,
            tax_amount,
            total_price,
            status,
            payment_method,
            payment_status,
            shipping_address,
            shipping_city,
            shipping_postal_code,
            created_at,
            updated_at
        FROM orders
        WHERE id = :order_id AND user_id = :user_id
    ");
    
    $stmtOrder->execute([
        ':order_id' => $orderId,
        ':user_id' => $userId
    ]);
    
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);
    
    // If order not found or doesn't belong to user
    if (!$order) {
        Response::error(
            'Order not found.',
            [
                'message' => 'The order does not exist or you do not have permission to view it.',
                'order_id' => $orderId
            ],
            404
        );
    }
    
    // ============================================
    // STEP 2: Get order items with product details
    // ============================================
    // We need to show what products were in this order
    // JOIN with products table to get product names, images, etc.
    // 
    // IMPORTANT: Use price_at_purchase, not current product price
    // Why?
    // - Product price today might be different from when order was placed
    // - Customer paid the historical price, not today's price
    // - For accounting and legal reasons, show what customer actually paid
    
    $stmtItems = $pdo->prepare("
        SELECT 
            oi.id as order_item_id,
            oi.product_id,
            oi.quantity,
            oi.price_at_purchase,
            p.name as product_name,
            p.slug as product_slug,
            p.brand as product_brand,
            p.image_url as product_image
        FROM order_items oi
        INNER JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = :order_id
        ORDER BY oi.id ASC
    ");
    
    $stmtItems->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmtItems->execute();
    $orderItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // STEP 3: Format order items
    // ============================================
    // Calculate subtotal for each item (price × quantity)
    // Format prices for display
    
    $formattedItems = array_map(function($item) {
        $priceAtPurchase = floatval($item['price_at_purchase']);
        $quantity = intval($item['quantity']);
        $subtotal = $priceAtPurchase * $quantity;
        
        return [
            'order_item_id' => (int) $item['order_item_id'],
            'product' => [
                'id' => (int) $item['product_id'],
                'name' => $item['product_name'],
                'slug' => $item['product_slug'],
                'brand' => $item['product_brand'],
                'image_url' => $item['product_image']
            ],
            'quantity' => $quantity,
            'price_at_purchase' => number_format($priceAtPurchase, 2),
            'price_at_purchase_numeric' => $priceAtPurchase,
            'subtotal' => number_format($subtotal, 2),
            'subtotal_numeric' => $subtotal
        ];
    }, $orderItems);
    
    // ============================================
    // STEP 4: Format complete order response
    // ============================================
    
    $formattedOrder = [
        'id' => (int) $order['id'],
        'order_number' => $order['order_number'],
        
        // Pricing information
        'pricing' => [
            'subtotal' => number_format((float) $order['subtotal'], 2),
            'subtotal_numeric' => (float) $order['subtotal'],
            'tax_amount' => number_format((float) $order['tax_amount'], 2),
            'tax_amount_numeric' => (float) $order['tax_amount'],
            'tax_rate' => '19%',
            'total_price' => number_format((float) $order['total_price'], 2),
            'total_price_numeric' => (float) $order['total_price']
        ],
        
        // Order status
        'status' => [
            'order_status' => $order['status'],
            'order_status_label' => ucfirst($order['status']),
            'payment_status' => $order['payment_status'],
            'payment_method' => $order['payment_method']
        ],
        
        // Shipping information
        'shipping' => [
            'address' => $order['shipping_address'],
            'city' => $order['shipping_city'],
            'postal_code' => $order['shipping_postal_code'],
            'full_address' => $order['shipping_address'] . ', ' . $order['shipping_city'] . ' ' . $order['shipping_postal_code']
        ],
        
        // Order items
        'items' => $formattedItems,
        'items_count' => count($formattedItems),
        'total_quantity' => array_sum(array_column($formattedItems, 'quantity')),
        
        // Timestamps
        'dates' => [
            'created_at' => $order['created_at'],
            'created_at_formatted' => date('F j, Y, g:i a', strtotime($order['created_at'])),
            'updated_at' => $order['updated_at'],
            'updated_at_formatted' => date('F j, Y, g:i a', strtotime($order['updated_at']))
        ]
    ];
    
    // ============================================
    // SUCCESS RESPONSE
    // ============================================
    
    Response::success(
        'Order details retrieved successfully.',
        [
            'order' => $formattedOrder
        ]
    );
    
} catch (Exception $e) {
    Response::error(
        'Failed to retrieve order details.',
        ['error' => $e->getMessage()],
        500
    );
}

/**
 * EXAMPLE RESPONSE:
 * 
 * {
 *   "success": true,
 *   "message": "Order details retrieved successfully.",
 *   "data": {
 *     "order": {
 *       "id": 5,
 *       "order_number": "CT202511210015",
 *       "pricing": {
 *         "subtotal": "2499.00",
 *         "subtotal_numeric": 2499,
 *         "tax_amount": "474.81",
 *         "tax_amount_numeric": 474.81,
 *         "tax_rate": "19%",
 *         "total_price": "2973.81",
 *         "total_price_numeric": 2973.81
 *       },
 *       "status": {
 *         "order_status": "pending",
 *         "order_status_label": "Pending",
 *         "payment_status": "pending",
 *         "payment_method": "cash_on_delivery"
 *       },
 *       "shipping": {
 *         "address": "123 Main St",
 *         "city": "Tunis",
 *         "postal_code": "1000",
 *         "full_address": "123 Main St, Tunis 1000"
 *       },
 *       "items": [
 *         {
 *           "order_item_id": 10,
 *           "product": {
 *             "id": 1,
 *             "name": "Laptop HP Pavilion 15",
 *             "slug": "laptop-hp-pavilion-15",
 *             "brand": "HP",
 *             "image_url": "..."
 *           },
 *           "quantity": 1,
 *           "price_at_purchase": "2499.00",
 *           "price_at_purchase_numeric": 2499,
 *           "subtotal": "2499.00",
 *           "subtotal_numeric": 2499
 *         }
 *       ],
 *       "items_count": 1,
 *       "total_quantity": 1,
 *       "dates": {
 *         "created_at": "2025-11-21 14:30:00",
 *         "created_at_formatted": "November 21, 2025, 2:30 pm",
 *         "updated_at": "2025-11-21 14:30:00",
 *         "updated_at_formatted": "November 21, 2025, 2:30 pm"
 *       }
 *     }
 *   }
 * }
 */

/**
 * SECURITY CONCEPTS EXPLAINED:
 * 
 * 1. IDOR (Insecure Direct Object Reference):
 *    Vulnerability where user can access resources by changing IDs in URLs
 *    
 *    Example attack:
 *    - User views their order: /api/orders/detail.php?id=50
 *    - User tries: /api/orders/detail.php?id=51
 *    - If no security check, user sees someone else's order!
 *    
 *    Prevention:
 *    - Always check: WHERE id = :id AND user_id = :user_id
 *    - Verify resource belongs to requesting user
 * 
 * 2. Authorization vs Authentication:
 *    - Authentication: Who are you? (login check)
 *    - Authorization: What can you do? (permission check)
 *    
 *    This endpoint checks BOTH:
 *    - requireAuth() → Authentication (are you logged in?)
 *    - WHERE user_id = :user_id → Authorization (is this YOUR order?)
 * 
 * 3. Historical Data Integrity:
 *    - Use price_at_purchase, not current product price
 *    - Customer should see what they actually paid
 *    - Product prices change over time
 *    - Legal requirement for invoices and receipts
 *    
 *    Example:
 *    Order placed: Laptop cost $1000
 *    Today: Laptop costs $1200
 *    
 *    Wrong: Show $1200 (current price)
 *    Right: Show $1000 (price_at_purchase)
 * 
 * 4. Information Disclosure:
 *    Be careful what error messages reveal
 *    
 *    Bad: "Order 51 belongs to user_id 8, but you are user_id 5"
 *    → Reveals that order 51 exists and who owns it
 *    
 *    Good: "Order not found."
 *    → Doesn't reveal if order exists or who owns it
 * 
 * FRONTEND USAGE:
 * 
 * // Get order details
 * fetch('/api/orders/detail.php?id=5', {
 *   credentials: 'include'
 * })
 * .then(res => res.json())
 * .then(data => {
 *   const order = data.data.order;
 *   console.log(`Order ${order.order_number}`);
 *   console.log(`Total: ${order.pricing.total_price}`);
 *   
 *   order.items.forEach(item => {
 *     console.log(`${item.product.name} x${item.quantity} = ${item.subtotal}`);
 *   });
 * });
 */
