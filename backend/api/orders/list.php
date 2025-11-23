<?php
/**
 * LIST ORDERS ENDPOINT
 * 
 * Returns all orders for the currently logged-in user.
 * Orders are returned in reverse chronological order (newest first).
 * Includes order summary information and item counts.
 * 
 * SECURITY: Only returns orders that belong to the current user
 * Each user can ONLY see their own orders, not other users' orders
 * 
 * FEATURES:
 * - Pagination support (optional page and limit parameters)
 * - Order by created_at DESC (newest orders first)
 * - Item count per order using GROUP BY and COUNT
 * - Formatted dates and prices
 * - Total count for pagination controls
 */

declare(strict_types=1);

// CORS headers for cross-origin requests
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
// PAGINATION PARAMETERS
// ============================================
// Pagination allows splitting large result sets into pages
// Example: 100 orders → 10 pages of 10 orders each
// 
// Parameters:
// - page: Which page to return (1, 2, 3, ...)
// - limit: How many orders per page (default 10)

// Get page number from query string (default: page 1)
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Get limit from query string (default: 10 orders per page)
// Max 100 to prevent loading too many orders at once
$limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 10;

// Calculate offset for SQL query
// Offset = how many records to skip
// Page 1: offset 0 (skip 0, show first 10)
// Page 2: offset 10 (skip first 10, show next 10)
// Page 3: offset 20 (skip first 20, show next 10)
$offset = ($page - 1) * $limit;

// Get database connection
try {
    $pdo = getDB();
} catch (Exception $e) {
    Response::error('Database connection failed.', ['error' => $e->getMessage()], 500);
}

try {
    // ============================================
    // STEP 1: Count total orders for this user
    // ============================================
    // We need total count to calculate:
    // - Total pages = ceil(total_count / limit)
    // - Whether there are more pages
    // - Display "Showing X of Y orders"
    
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM orders
        WHERE user_id = :user_id
    ");
    $stmtCount->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmtCount->execute();
    $totalOrders = (int) $stmtCount->fetchColumn();
    
    // Calculate total pages
    $totalPages = ceil($totalOrders / $limit);
    
    // ============================================
    // STEP 2: Get orders with item counts
    // ============================================
    // We want to show: "Order #CT20251121 - 3 items - $150.00"
    // To get item count, we need to:
    // 1. JOIN with order_items table
    // 2. Use COUNT to count items per order
    // 3. Use GROUP BY to group by order_id
    // 
    // SQL CONCEPT: GROUP BY
    // Without GROUP BY:
    //   If order has 3 items, query returns 3 rows (one per item)
    // 
    // With GROUP BY order_id:
    //   Query returns 1 row per order, with aggregated data (COUNT, SUM, etc.)
    // 
    // Example:
    // Orders:
    // | order_id | total  |
    // |----------|--------|
    // | 1        | 100.00 |
    // | 2        | 200.00 |
    // 
    // Order Items:
    // | order_id | product_id | quantity |
    // |----------|------------|----------|
    // | 1        | 5          | 2        |
    // | 1        | 8          | 1        |
    // | 2        | 3          | 5        |
    // 
    // Query with GROUP BY:
    // SELECT order_id, COUNT(*) as item_count
    // FROM order_items
    // GROUP BY order_id
    // 
    // Result:
    // | order_id | item_count |
    // |----------|------------|
    // | 1        | 2          | (two different products)
    // | 2        | 1          | (one product)
    
    $stmt = $pdo->prepare("
        SELECT 
            o.id,
            o.order_number,
            o.subtotal,
            o.tax_amount,
            o.total_price,
            o.status,
            o.payment_method,
            o.payment_status,
            o.shipping_address,
            o.shipping_city,
            o.shipping_postal_code,
            o.created_at,
            o.updated_at,
            COUNT(oi.id) as item_count,
            SUM(oi.quantity) as total_quantity
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = :user_id
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    
    // WHY LEFT JOIN?
    // LEFT JOIN ensures we get orders even if they have no items (shouldn't happen, but safe)
    // INNER JOIN would exclude orders without items
    
    // ORDER BY created_at DESC:
    // DESC = descending order (newest first)
    // Most recent orders appear at the top
    // Customer wants to see latest order first, not oldest
    
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // STEP 3: Format order data for response
    // ============================================
    // Make data more user-friendly:
    // - Format dates (human-readable)
    // - Format prices (two decimals)
    // - Add status labels
    
    $formattedOrders = array_map(function($order) {
        return [
            'id' => (int) $order['id'],
            'order_number' => $order['order_number'],
            'subtotal' => number_format((float) $order['subtotal'], 2),
            'tax_amount' => number_format((float) $order['tax_amount'], 2),
            'total_price' => number_format((float) $order['total_price'], 2),
            'total_price_numeric' => (float) $order['total_price'],
            'status' => $order['status'],
            'status_label' => ucfirst($order['status']), // pending → Pending
            'payment_method' => $order['payment_method'],
            'payment_status' => $order['payment_status'],
            'shipping' => [
                'address' => $order['shipping_address'],
                'city' => $order['shipping_city'],
                'postal_code' => $order['shipping_postal_code']
            ],
            'item_count' => (int) $order['item_count'],        // Number of different products
            'total_quantity' => (int) $order['total_quantity'], // Total items (sum of quantities)
            'created_at' => $order['created_at'],
            'created_at_formatted' => date('F j, Y, g:i a', strtotime($order['created_at'])),
            'updated_at' => $order['updated_at']
        ];
    }, $orders);
    
    // ============================================
    // SUCCESS RESPONSE
    // ============================================
    
    Response::success(
        'Orders retrieved successfully.',
        [
            'orders' => $formattedOrders,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_orders' => $totalOrders,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
                'showing_from' => $offset + 1,
                'showing_to' => min($offset + $limit, $totalOrders)
            ]
        ]
    );
    
} catch (Exception $e) {
    Response::error(
        'Failed to retrieve orders.',
        ['error' => $e->getMessage()],
        500
    );
}

/**
 * EXAMPLE RESPONSE:
 * 
 * {
 *   "success": true,
 *   "message": "Orders retrieved successfully.",
 *   "data": {
 *     "orders": [
 *       {
 *         "id": 5,
 *         "order_number": "CT202511210015",
 *         "subtotal": "2499.00",
 *         "tax_amount": "474.81",
 *         "total_price": "2973.81",
 *         "total_price_numeric": 2973.81,
 *         "status": "pending",
 *         "status_label": "Pending",
 *         "payment_method": "cash_on_delivery",
 *         "payment_status": "pending",
 *         "shipping": {
 *           "address": "123 Main St",
 *           "city": "Tunis",
 *           "postal_code": "1000"
 *         },
 *         "item_count": 3,
 *         "total_quantity": 5,
 *         "created_at": "2025-11-21 14:30:00",
 *         "created_at_formatted": "November 21, 2025, 2:30 pm",
 *         "updated_at": "2025-11-21 14:30:00"
 *       }
 *     ],
 *     "pagination": {
 *       "current_page": 1,
 *       "per_page": 10,
 *       "total_orders": 15,
 *       "total_pages": 2,
 *       "has_more": true,
 *       "showing_from": 1,
 *       "showing_to": 10
 *     }
 *   }
 * }
 * 
 * FRONTEND USAGE:
 * 
 * // Get first page
 * fetch('/api/orders/list.php')
 * 
 * // Get page 2 with 20 orders per page
 * fetch('/api/orders/list.php?page=2&limit=20')
 * 
 * // Display in UI
 * data.orders.forEach(order => {
 *   console.log(`${order.order_number} - ${order.item_count} items - ${order.total_price}`);
 * });
 * 
 * // Pagination controls
 * if (data.pagination.has_more) {
 *   showNextPageButton();
 * }
 * console.log(`Showing ${data.pagination.showing_from} - ${data.pagination.showing_to} of ${data.pagination.total_orders}`);
 */

/**
 * SQL CONCEPTS EXPLAINED:
 * 
 * 1. GROUP BY:
 *    Groups rows that have the same values in specified columns
 *    Used with aggregate functions: COUNT(), SUM(), AVG(), MAX(), MIN()
 *    
 *    Example: Count items per order
 *    SELECT order_id, COUNT(*) FROM order_items GROUP BY order_id
 * 
 * 2. ORDER BY ... DESC:
 *    Sorts results in descending order (largest to smallest, newest to oldest)
 *    ASC = ascending (default)
 *    DESC = descending
 *    
 *    created_at DESC → newest orders first
 *    total_price DESC → most expensive orders first
 * 
 * 3. LIMIT and OFFSET:
 *    LIMIT: Maximum number of rows to return
 *    OFFSET: Number of rows to skip before returning results
 *    
 *    LIMIT 10 OFFSET 0 → rows 1-10 (page 1)
 *    LIMIT 10 OFFSET 10 → rows 11-20 (page 2)
 *    LIMIT 10 OFFSET 20 → rows 21-30 (page 3)
 * 
 * 4. LEFT JOIN:
 *    Returns all rows from left table, matching rows from right table
 *    If no match in right table, returns NULL for right table columns
 *    
 *    orders LEFT JOIN order_items
 *    → Returns all orders, even if they have no items
 *    
 *    vs INNER JOIN:
 *    → Only returns orders that have items
 * 
 * 5. COUNT(*) vs SUM(quantity):
 *    COUNT(*) → number of rows (different products)
 *    SUM(quantity) → sum of quantity column (total items)
 *    
 *    Example:
 *    | product | quantity |
 *    | Laptop  | 2        |
 *    | Mouse   | 3        |
 *    
 *    COUNT(*) = 2 (two products)
 *    SUM(quantity) = 5 (2 + 3 total items)
 */
