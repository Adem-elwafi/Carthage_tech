<?php
/**
 * ADMIN MIDDLEWARE
 * 
 * This middleware extends authentication with role-based access control.
 * It ensures that only users with 'admin' role can access certain endpoints.
 * 
 * ROLE-BASED ACCESS CONTROL (RBAC) EXPLAINED:
 * - Some actions should only be available to administrators
 * - Examples: updating order status, deleting products, viewing all users
 * - Regular users should be blocked from these admin functions
 * - This middleware checks TWO things: 1) Is user logged in? 2) Is user an admin?
 * 
 * HOW IT WORKS:
 * 1. Start session (to access user data)
 * 2. Check if user is logged in (authentication)
 * 3. Check if user role is 'admin' (authorization)
 * 4. If both pass: allow access and return user data
 * 5. If either fails: return error and stop execution
 * 
 * USAGE IN ENDPOINTS:
 * Instead of: require_once '../../middleware/auth.php';
 * Use: require_once '../../middleware/admin.php';
 * Then call: $admin = requireAdmin();
 */

declare(strict_types=1);

// Include Response utility for sending JSON error responses
require_once __DIR__ . '/../utils/Response.php';

/**
 * Start PHP session if not already started
 * Sessions store user information across HTTP requests
 */
function startSessionIfNotStarted(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Set session cookie parameters for security
        session_set_cookie_params([
            'lifetime' => 86400,    // 24 hours
            'path' => '/',
            'domain' => '',
            'secure' => false,      // Set true if using HTTPS
            'httponly' => true,     // Prevent JavaScript access to session cookie
            'samesite' => 'Lax'     // CSRF protection
        ]);
        
        session_start();
    }
}

/**
 * Check if user is authenticated (logged in)
 * Returns true if user data exists in session
 */
function isAuthenticated(): bool {
    startSessionIfNotStarted();
    return isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
}

/**
 * Get current authenticated user data from session
 * Returns user array or null if not logged in
 */
function getCurrentUser(): ?array {
    startSessionIfNotStarted();
    
    if (!isAuthenticated()) {
        return null;
    }
    
    // Return user data stored in session
    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'],
        'first_name' => $_SESSION['user_first_name'] ?? null,
        'last_name' => $_SESSION['user_last_name'] ?? null,
        'role' => $_SESSION['user_role'] ?? 'customer'  // Default to customer if role not set
    ];
}

/**
 * MAIN ADMIN MIDDLEWARE FUNCTION
 * 
 * Requires user to be logged in AND have admin role.
 * If user is not logged in: Returns 401 Unauthorized
 * If user is not admin: Returns 403 Forbidden
 * If user is admin: Returns user data and continues execution
 * 
 * HTTP STATUS CODES EXPLAINED:
 * - 401 Unauthorized: User needs to log in first
 * - 403 Forbidden: User is logged in but doesn't have permission
 * 
 * RETURN VALUE:
 * Returns user array if admin, otherwise exits with error response
 */
function requireAdmin(): array {
    startSessionIfNotStarted();
    
    // STEP 1: Check if user is logged in (authentication)
    if (!isAuthenticated()) {
        // User not logged in - return 401 Unauthorized
        Response::error(
            'Authentication required. Please log in first.',
            null,
            401
        );
        // Response::error() calls exit(), so code stops here
    }
    
    $user = getCurrentUser();
    
    // STEP 2: Check if user has admin role (authorization)
    // Authorization = checking what the authenticated user is allowed to do
    if ($user['role'] !== 'admin') {
        // User is logged in but not an admin - return 403 Forbidden
        Response::error(
            'Access denied. This action requires administrator privileges.',
            [
                'required_role' => 'admin',
                'current_role' => $user['role'],
                'message' => 'Contact administrator if you believe this is an error.'
            ],
            403
        );
        // Response::error() calls exit(), so code stops here
    }
    
    // User is authenticated AND has admin role - allow access
    return $user;
}

/**
 * Check if current user is an admin (without stopping execution)
 * 
 * DIFFERENCE FROM requireAdmin():
 * - requireAdmin(): Stops execution if not admin (used in endpoints)
 * - isAdmin(): Returns true/false, continues execution (used for conditional logic)
 * 
 * USAGE EXAMPLE:
 * if (isAdmin()) {
 *     // Show admin features
 * } else {
 *     // Show regular user features
 * }
 */
function isAdmin(): bool {
    startSessionIfNotStarted();
    
    if (!isAuthenticated()) {
        return false;
    }
    
    $user = getCurrentUser();
    return $user !== null && $user['role'] === 'admin';
}

/**
 * EXAMPLE USAGE IN ENDPOINT:
 * 
 * // File: backend/api/admin/delete-product.php
 * require_once '../../middleware/admin.php';
 * 
 * // This line checks if user is admin
 * // If not admin: returns error and stops
 * // If admin: continues and returns admin user data
 * $admin = requireAdmin();
 * 
 * // Only admins reach this point
 * // $admin contains: id, email, first_name, last_name, role
 * 
 * // Proceed with admin action
 * deleteProduct($_POST['product_id']);
 * Response::success('Product deleted by ' . $admin['email']);
 */

/**
 * WHY ROLE-BASED ACCESS CONTROL IS IMPORTANT:
 * 
 * 1. SECURITY: Prevents regular users from performing privileged actions
 *    Example: A customer shouldn't be able to change order statuses
 * 
 * 2. DATA INTEGRITY: Protects critical data from unauthorized modifications
 *    Example: Only admins should delete products from catalog
 * 
 * 3. AUDIT TRAIL: Know which admin performed which action
 *    You can log: "Admin john@example.com deleted product #123"
 * 
 * 4. COMPLIANCE: Many regulations require access controls
 *    Example: GDPR requires restricting access to personal data
 * 
 * COMMON ROLES IN E-COMMERCE:
 * - customer: Browse products, place orders, view own orders
 * - admin: Manage products, view all orders, change order statuses
 * - vendor: Manage own products only
 * - moderator: Manage reviews, handle support tickets
 * 
 * HOW TO EXTEND WITH MORE ROLES:
 * 1. Add role column values in users table (already done)
 * 2. Create requireRole($roleName) function
 * 3. Check: if ($user['role'] !== $roleName) { return 403; }
 * 4. Or use array: if (!in_array($user['role'], ['admin', 'vendor'])) { ... }
 */
