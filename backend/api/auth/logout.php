<?php
declare(strict_types=1);
/**
 * User Logout Endpoint
 * 
 * Handles user logout by destroying the session and clearing all session data.
 * This endpoint can be accessed with any HTTP method (GET, POST, etc.) for flexibility.
 * 
 * Expected: No parameters required
 * 
 * Returns:
 * - Success: 200 with logout confirmation
 */

// ============================================
// CORS AND HEADERS CONFIGURATION
// ============================================
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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
// START SESSION
// ============================================
// We need to start the session to be able to destroy it
startSessionIfNotStarted();

// ============================================
// CHECK IF USER WAS LOGGED IN
// ============================================
$wasLoggedIn = isAuthenticated();

// ============================================
// DESTROY SESSION
// ============================================
// Step 1: Unset all session variables
$_SESSION = [];

// Step 2: If using cookies for session, delete the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),           // Session cookie name
        '',                       // Empty value
        time() - 42000,          // Expire in the past
        $params['path'],         // Cookie path
        $params['domain'],       // Cookie domain
        $params['secure'],       // Secure flag
        $params['httponly']      // HTTP only flag
    );
}

// Step 3: Destroy the session completely
session_destroy();

// ============================================
// SEND SUCCESS RESPONSE
// ============================================
if ($wasLoggedIn) {
    Response::success(
        'Logout successful. You have been logged out.',
        [
            'logged_out' => true,
            'message' => 'Your session has been terminated. Come back soon!'
        ]
    );
} else {
    // User wasn't logged in, but that's okay
    Response::success(
        'No active session found.',
        [
            'logged_out' => false,
            'message' => 'You were not logged in.'
        ]
    );
}

// End of logout.php
