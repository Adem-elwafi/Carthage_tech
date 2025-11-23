<?php
declare(strict_types=1);
/**
 * Authentication Middleware
 * 
 * This middleware checks if a user is authenticated by verifying their session.
 * It should be included at the top of any protected API endpoint that requires
 * the user to be logged in.
 * 
 * How it works:
 * 1. Starts a session if not already started
 * 2. Checks if user data exists in the session
 * 3. Returns user data if authenticated
 * 4. Sends an error response and exits if not authenticated
 * 
 * Usage:
 * ```php
 * require_once __DIR__ . '/../middleware/auth.php';
 * $currentUser = requireAuth();
 * // Now you can use $currentUser['id'], $currentUser['email'], etc.
 * ```
 */

// Load the Response helper class for error responses
require_once __DIR__ . '/../utils/Response.php';

/**
 * Start session if not already started
 * 
 * This function safely starts a session only if one hasn't been started yet.
 * It prevents the "session already started" warning.
 * 
 * @return void
 */
function startSessionIfNotStarted(): void
{
    // Check if session is not already active
    if (session_status() === PHP_SESSION_NONE) {
        // Configure session settings for security
        ini_set('session.cookie_httponly', '1'); // Prevent JavaScript access to session cookie
        ini_set('session.use_only_cookies', '1'); // Only use cookies, not URL parameters
        ini_set('session.cookie_samesite', 'Lax'); // CSRF protection
        
        // Start the session
        session_start();
    }
}

/**
 * Check if user is authenticated
 * 
 * This function checks if a user is logged in by verifying that user data
 * exists in the session. Returns true if authenticated, false otherwise.
 * 
 * @return bool True if user is authenticated, false otherwise
 */
function isAuthenticated(): bool
{
    // Ensure session is started
    startSessionIfNotStarted();
    
    // Check if 'user' key exists in session and has an ID
    return isset($_SESSION['user']) && isset($_SESSION['user']['id']);
}

/**
 * Get current authenticated user data
 * 
 * Returns the user data stored in the session, or null if not authenticated.
 * 
 * @return array|null User data array or null if not logged in
 */
function getCurrentUser(): ?array
{
    // Ensure session is started
    startSessionIfNotStarted();
    
    // Return user data if it exists, otherwise null
    return $_SESSION['user'] ?? null;
}

/**
 * Require authentication (middleware function)
 * 
 * This is the main middleware function that should be called at the beginning
 * of protected endpoints. It checks if the user is authenticated and either:
 * - Returns the user data if authenticated
 * - Sends an error response and exits if not authenticated
 * 
 * @return array User data from session
 */
function requireAuth(): array
{
    // Ensure session is started
    startSessionIfNotStarted();
    
    // Check if user is authenticated
    if (!isAuthenticated()) {
        // User is not logged in - send 401 Unauthorized response
        Response::error(
            'Authentication required. Please log in to access this resource.',
            ['auth' => 'No valid session found'],
            401 // 401 Unauthorized
        );
        // Response::error() will exit, but just in case:
        exit;
    }
    
    // User is authenticated - return their data
    return $_SESSION['user'];
}

/**
 * Check if user has a specific role
 * 
 * Useful for role-based access control (e.g., admin-only endpoints).
 * 
 * @param string $requiredRole The role required to access the resource
 * @return bool True if user has the required role
 */
function hasRole(string $requiredRole): bool
{
    $user = getCurrentUser();
    
    if ($user === null) {
        return false;
    }
    
    return isset($user['role']) && $user['role'] === $requiredRole;
}

/**
 * Require a specific role (middleware function)
 * 
 * Use this to protect endpoints that require specific permissions.
 * Example: Only admins can delete products.
 * 
 * @param string $requiredRole The role required to access the resource
 * @return array User data from session
 */
function requireRole(string $requiredRole): array
{
    // First check if user is authenticated
    $user = requireAuth();
    
    // Check if user has the required role
    if (!hasRole($requiredRole)) {
        Response::error(
            'Access denied. You do not have permission to access this resource.',
            [
                'required_role' => $requiredRole,
                'user_role' => $user['role'] ?? 'none'
            ],
            403 // 403 Forbidden
        );
        exit;
    }
    
    return $user;
}

// End of auth.php
