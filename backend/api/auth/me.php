<?php
declare(strict_types=1);
/**
 * Get Current User Endpoint
 * 
 * Returns information about the currently logged-in user.
 * This endpoint requires authentication - user must be logged in.
 * 
 * Use this endpoint to:
 * - Check if user is still logged in
 * - Get current user data for displaying in UI
 * - Verify session is still valid
 * 
 * Expected: No parameters (reads from session)
 * 
 * Returns:
 * - Success: 200 with user data if authenticated
 * - Error: 401 if not authenticated
 */

// ============================================
// CORS AND HEADERS CONFIGURATION
// ============================================
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================
// INCLUDE REQUIRED FILES
// ============================================
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/auth.php';

// ============================================
// CHECK REQUEST METHOD
// ============================================
// This endpoint typically uses GET, but can accept POST too
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    Response::error('Method not allowed. Please use GET or POST request.', [], 405);
}

// ============================================
// REQUIRE AUTHENTICATION
// ============================================
// This will check if user is logged in
// If not, it will automatically send 401 error and exit
$currentUser = requireAuth();

// ============================================
// GET ADDITIONAL USER INFO (Optional)
// ============================================
// You can fetch additional data from database if needed
// For now, we'll just return what's in the session

// Optional: Connect to database to get fresh data
try {
    require_once __DIR__ . '/../../config/database.php';
    $pdo = getDatabaseConnection();
    
    if ($pdo !== null) {
        // Fetch fresh user data from database
        $sql = 'SELECT id, email, first_name, last_name, phone, address, city, postal_code, role, created_at 
                FROM users 
                WHERE id = :id 
                LIMIT 1';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $currentUser['id']]);
        
        $freshUserData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($freshUserData) {
            // Update with fresh data from database
            $currentUser = [
                'id' => (int) $freshUserData['id'],
                'email' => $freshUserData['email'],
                'first_name' => $freshUserData['first_name'],
                'last_name' => $freshUserData['last_name'],
                'phone' => $freshUserData['phone'],
                'address' => $freshUserData['address'],
                'city' => $freshUserData['city'],
                'postal_code' => $freshUserData['postal_code'],
                'role' => $freshUserData['role'],
                'created_at' => $freshUserData['created_at']
            ];
            
            // Update session with fresh data
            $_SESSION['user'] = $currentUser;
        }
    }
    
} catch (PDOException $e) {
    // If database query fails, just use session data
    // This is not critical, so don't fail the request
    error_log('Failed to fetch fresh user data: ' . $e->getMessage());
}

// ============================================
// CALCULATE SESSION INFO
// ============================================
$sessionInfo = [
    'session_id' => session_id(),
    'login_time' => $_SESSION['login_time'] ?? null,
    'session_duration' => isset($_SESSION['login_time']) 
        ? (time() - $_SESSION['login_time']) . ' seconds' 
        : 'unknown'
];

// ============================================
// SEND SUCCESS RESPONSE
// ============================================
Response::success(
    'User data retrieved successfully.',
    [
        'user' => $currentUser,
        'session' => $sessionInfo,
        'authenticated' => true
    ]
);

// End of me.php
