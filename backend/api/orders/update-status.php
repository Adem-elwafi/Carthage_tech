<?php
/**
 * UPDATE ORDER STATUS ENDPOINT (ADMIN ONLY)
 * 
 * Allows administrators to update the status of orders.
 * Regular customers cannot change order statuses.
 * 
 * ROLE-BASED ACCESS CONTROL:
 * Only users with role='admin' can access this endpoint
 * Uses requireAdmin() middleware instead of requireAuth()
 * 
 * VALID ORDER STATUSES:
 * - pending: Order placed, waiting for confirmation
 * - confirmed: Order confirmed by admin/system
 * - processing: Order is being prepared/packaged
 * - shipped: Order has been shipped to customer
 * - delivered: Order received by customer
 * - cancelled: Order was cancelled
 * 
 * STATUS WORKFLOW (typical):
 * pending → confirmed → processing → shipped → delivered
 *                                 ↓
 *                            cancelled (can happen at any stage)
 * 
 * WHY ADMIN ONLY?
 * - Prevents customers from marking their own orders as "delivered"
 * - Ensures proper workflow and tracking
 * - Maintains data integrity
 * - Creates audit trail (admin actions can be logged)
 */

declare(strict_types=1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once '../../config/database.php';
require_once '../../utils/Response.php';
require_once '../../middleware/admin.php';  // Admin middleware, not regular auth!

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed. Use POST.', null, 405);
}

// ============================================
// REQUIRE ADMIN AUTHORIZATION
// ============================================
// This line does TWO checks:
// 1. Is user logged in? (authentication)
// 2. Is user an admin? (authorization)
// 
// If either check fails, requireAdmin() will:
// - Return 401 if not logged in
// - Return 403 if logged in but not admin
// - Exit script (code below never runs)
// 
// Only admin users reach the code below this line

$admin = requireAdmin();
$adminId = $admin['id'];
$adminEmail = $admin['email'];

// ============================================
// PARSE AND VALIDATE REQUEST DATA
// ============================================

// Get JSON request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if JSON is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    Response::error('Invalid JSON data.', ['error' => json_last_error_msg()]);
}

$errors = [];

// Validate order_id
if (!isset($data['order_id']) || empty($data['order_id'])) {
    $errors['order_id'] = 'Order ID is required.';
} else {
    $orderId = intval($data['order_id']);
    if ($orderId <= 0) {
        $errors['order_id'] = 'Invalid order ID.';
    }
}

// Validate status
if (!isset($data['status']) || empty($data['status'])) {
    $errors['status'] = 'Status is required.';
} else {
    $status = strtolower(trim($data['status']));
    
    // Define valid statuses
    $validStatuses = [
        'pending',
        'confirmed',
        'processing',
        'shipped',
        'delivered',
        'cancelled'
    ];
    
    if (!in_array($status, $validStatuses)) {
        $errors['status'] = 'Invalid status. Valid statuses: ' . implode(', ', $validStatuses);
    }
}

// If validation errors, return them
if (!empty($errors)) {
    Response::error('Validation failed.', $errors, 400);
}

// Get database connection
try {
    $pdo = getDB();
} catch (Exception $e) {
    Response::error('Database connection failed.', ['error' => $e->getMessage()], 500);
}

try {
    // ============================================
    // STEP 1: Check if order exists
    // ============================================
    // Before updating, verify order exists
    // Get current status and order details for response
    
    $stmtCheck = $pdo->prepare("
        SELECT 
            id,
            order_number,
            user_id,
            status as current_status,
            total_price
        FROM orders
        WHERE id = :order_id
    ");
    $stmtCheck->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmtCheck->execute();
    $order = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        Response::error(
            'Order not found.',
            ['order_id' => $orderId],
            404
        );
    }
    
    $currentStatus = $order['current_status'];
    
    // Check if status is actually changing
    if ($currentStatus === $status) {
        Response::error(
            'Order already has this status.',
            [
                'order_id' => $orderId,
                'order_number' => $order['order_number'],
                'current_status' => $currentStatus,
                'requested_status' => $status
            ],
            400
        );
    }
    
    // ============================================
    // STEP 2: Update order status
    // ============================================
    // Update status and updated_at timestamp
    // updated_at is automatically updated by MySQL (ON UPDATE CURRENT_TIMESTAMP)
    
    $stmtUpdate = $pdo->prepare("
        UPDATE orders 
        SET status = :status
        WHERE id = :order_id
    ");
    
    $stmtUpdate->execute([
        ':status' => $status,
        ':order_id' => $orderId
    ]);
    
    // Check if update was successful
    $rowsAffected = $stmtUpdate->rowCount();
    
    if ($rowsAffected === 0) {
        // This shouldn't happen (we checked order exists above)
        // But handle it just in case
        Response::error(
            'Failed to update order status.',
            ['message' => 'No rows were affected by the update.'],
            500
        );
    }
    
    // ============================================
    // STEP 3: Log the admin action (optional but recommended)
    // ============================================
    // In a production system, you should log admin actions:
    // - Who made the change (admin_id)
    // - What was changed (order_id, old status, new status)
    // - When it was changed (timestamp)
    // 
    // This creates an audit trail for accountability
    // 
    // Example log table:
    // CREATE TABLE admin_logs (
    //     id INT AUTO_INCREMENT PRIMARY KEY,
    //     admin_id INT,
    //     action VARCHAR(50),
    //     resource_type VARCHAR(50),
    //     resource_id INT,
    //     old_value VARCHAR(255),
    //     new_value VARCHAR(255),
    //     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    // );
    // 
    // Then insert:
    // INSERT INTO admin_logs (admin_id, action, resource_type, resource_id, old_value, new_value)
    // VALUES ($adminId, 'update_status', 'order', $orderId, $currentStatus, $status)
    
    // For now, we'll just log to PHP error log
    error_log("Admin action: User $adminEmail (ID: $adminId) changed order #{$order['order_number']} status from '$currentStatus' to '$status'");
    
    // ============================================
    // OPTIONAL: Send email notification to customer
    // ============================================
    // When order status changes, notify customer via email
    // Example: "Your order #CT20251121 has been shipped!"
    // 
    // This would typically be done here:
    // if ($status === 'shipped') {
    //     sendShippedNotification($order['user_id'], $order['order_number']);
    // }
    
    // ============================================
    // OPTIONAL: Update payment status
    // ============================================
    // Some status changes might affect payment status
    // For example:
    // - If order is delivered, mark payment as completed
    // - If order is cancelled, mark payment as refunded
    // 
    // Example:
    // if ($status === 'delivered' && $order['payment_status'] === 'pending') {
    //     UPDATE orders SET payment_status = 'completed' WHERE id = $orderId
    // }
    
    // ============================================
    // SUCCESS RESPONSE
    // ============================================
    
    Response::success(
        'Order status updated successfully.',
        [
            'order_id' => $orderId,
            'order_number' => $order['order_number'],
            'previous_status' => $currentStatus,
            'new_status' => $status,
            'updated_by' => [
                'admin_id' => $adminId,
                'admin_email' => $adminEmail
            ],
            'message' => "Order #{$order['order_number']} status changed from '$currentStatus' to '$status'"
        ]
    );
    
} catch (Exception $e) {
    Response::error(
        'Failed to update order status.',
        ['error' => $e->getMessage()],
        500
    );
}

/**
 * EXAMPLE USAGE:
 * 
 * // Admin marks order as shipped
 * fetch('/api/orders/update-status.php', {
 *   method: 'POST',
 *   headers: { 'Content-Type': 'application/json' },
 *   credentials: 'include',
 *   body: JSON.stringify({
 *     order_id: 5,
 *     status: 'shipped'
 *   })
 * });
 * 
 * SUCCESS RESPONSE:
 * {
 *   "success": true,
 *   "message": "Order status updated successfully.",
 *   "data": {
 *     "order_id": 5,
 *     "order_number": "CT202511210015",
 *     "previous_status": "confirmed",
 *     "new_status": "shipped",
 *     "updated_by": {
 *       "admin_id": 1,
 *       "admin_email": "admin@carthagetech.com"
 *     },
 *     "message": "Order #CT202511210015 status changed from 'confirmed' to 'shipped'"
 *   }
 * }
 * 
 * ERROR RESPONSES:
 * 
 * 1. Not logged in:
 * {
 *   "success": false,
 *   "message": "Authentication required. Please log in first.",
 *   "data": null,
 *   "status_code": 401
 * }
 * 
 * 2. Not admin:
 * {
 *   "success": false,
 *   "message": "Access denied. This action requires administrator privileges.",
 *   "data": {
 *     "required_role": "admin",
 *     "current_role": "customer"
 *   },
 *   "status_code": 403
 * }
 * 
 * 3. Order not found:
 * {
 *   "success": false,
 *   "message": "Order not found.",
 *   "data": { "order_id": 999 },
 *   "status_code": 404
 * }
 */

/**
 * ADMIN AUTHORIZATION CONCEPTS:
 * 
 * 1. Why separate admin endpoints?
 *    - Security: Prevents customers from accessing admin functions
 *    - Clarity: Clear separation between customer and admin actions
 *    - Logging: Easy to track admin actions separately
 * 
 * 2. HTTP Status Codes:
 *    - 401 Unauthorized: Not logged in
 *    - 403 Forbidden: Logged in but insufficient privileges
 *    - 404 Not Found: Resource doesn't exist
 *    - 400 Bad Request: Invalid input data
 *    - 200 OK: Success
 * 
 * 3. Security Best Practices:
 *    ✅ Check authentication (logged in?)
 *    ✅ Check authorization (admin role?)
 *    ✅ Validate input (valid status?)
 *    ✅ Verify resource exists (order exists?)
 *    ✅ Log admin actions (audit trail)
 *    ✅ Return appropriate error codes
 * 
 * 4. Order Status Workflow:
 *    Different businesses have different workflows
 *    
 *    Simple workflow:
 *    pending → shipped → delivered
 *    
 *    Complex workflow:
 *    pending → payment_received → confirmed → processing → 
 *    → quality_check → packaged → shipped → in_transit → 
 *    → out_for_delivery → delivered
 *    
 *    With cancellation:
 *    Any status → cancelled → refunded
 * 
 * 5. Additional Features (for production):
 *    - Status change reasons: "Why was order cancelled?"
 *    - Status history: Track all status changes with timestamps
 *    - Email notifications: Notify customer of status changes
 *    - SMS notifications: Send tracking updates
 *    - Webhooks: Notify external systems (shipping provider)
 *    - Conditional logic: Only allow certain status transitions
 *      Example: Can't go from 'delivered' back to 'pending'
 */

/**
 * EXTENDING WITH STATUS HISTORY (ADVANCED):
 * 
 * To track full history of status changes, create a table:
 * 
 * CREATE TABLE order_status_history (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     order_id INT NOT NULL,
 *     old_status VARCHAR(50),
 *     new_status VARCHAR(50) NOT NULL,
 *     changed_by INT NOT NULL COMMENT 'admin_id who made the change',
 *     reason TEXT COMMENT 'Why status was changed',
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *     FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
 *     FOREIGN KEY (changed_by) REFERENCES users(id)
 * );
 * 
 * Then after updating order status, insert history:
 * 
 * INSERT INTO order_status_history (order_id, old_status, new_status, changed_by)
 * VALUES ($orderId, $currentStatus, $status, $adminId)
 * 
 * This gives you complete audit trail:
 * - When was order status changed?
 * - Who changed it?
 * - What was previous status?
 * - Why was it changed?
 */
