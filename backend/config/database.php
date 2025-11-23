<?php
declare(strict_types=1);
/**
 * Database Connection Factory
 * 
 * This file provides a function to create and return a PDO database connection.
 * It uses the configuration constants defined in config.php and includes
 * comprehensive error handling for local development.
 * 
 * Features:
 * - PDO-based connection with prepared statements support
 * - Exception-based error handling
 * - Detailed debugging information in development mode
 * - Recommended PDO options for security and performance
 * 
 * Usage Example:
 * ```php
 * require_once __DIR__ . '/config.php';
 * require_once __DIR__ . '/database.php';
 * 
 * $pdo = getDatabaseConnection();
 * if ($pdo) {
 *     // Use the connection
 *     $stmt = $pdo->query('SELECT * FROM users');
 *     $users = $stmt->fetchAll();
 * }
 * ```
 */

// Load the configuration file (contains DB constants)
require_once __DIR__ . '/config.php';

// Load the Response helper class (for error responses)
require_once __DIR__ . '/../utils/Response.php';

/**
 * Create and return a PDO database connection
 * 
 * This function attempts to establish a connection to the MySQL database
 * using the constants defined in config.php. If the connection fails and
 * APP_DEBUG is enabled, it will send a JSON error response and exit.
 * 
 * @return PDO|null Returns PDO instance on success, null on failure (when debug is off)
 * @throws PDOException When connection fails (if not caught internally)
 */
function getDatabaseConnection(): ?PDO
{
    // Build the Data Source Name (DSN) string
    // Format: mysql:host=127.0.0.1;dbname=your_db;charset=utf8mb4
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    // Configure PDO options for security and best practices
    $options = [
        // Throw exceptions on errors (easier to debug and handle)
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        
        // Return associative arrays by default (['column' => 'value'])
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        
        // Use real prepared statements (more secure, prevents SQL injection)
        PDO::ATTR_EMULATE_PREPARES => false,
        
        // Don't convert numeric values to strings
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];

    try {
        // Attempt to create the PDO connection
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Connection successful - return the PDO instance
        return $pdo;
        
    } catch (PDOException $e) {
        // Connection failed - handle the error
        
        // Create a detailed error message for debugging
        $errorMessage = 'Database connection failed: ' . $e->getMessage();
        
        // Check if we're in debug mode
        if (defined('APP_DEBUG') && APP_DEBUG) {
            // Development mode: Send detailed error as JSON and stop execution
            Response::error(
                $errorMessage,
                [
                    'error_code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'dsn' => sprintf('mysql:host=%s;dbname=%s', DB_HOST, DB_NAME) // Don't expose password
                ],
                500 // Internal Server Error
            );
            // Response::error() calls exit(), but just in case:
            exit;
        } else {
            // Production mode: Log the error (you can implement logging here)
            // and return null to let the application handle it gracefully
            error_log($errorMessage);
            return null;
        }
    }
}

// Optional: Create a global connection function that caches the connection
// This prevents creating multiple connections in the same request
$GLOBALS['db_connection'] = null;

/**
 * Get a singleton database connection
 * 
 * This function returns the same PDO instance for all calls in a single request,
 * which is more efficient than creating multiple connections.
 * 
 * @return PDO|null
 */
function getDB(): ?PDO
{
    if ($GLOBALS['db_connection'] === null) {
        $GLOBALS['db_connection'] = getDatabaseConnection();
    }
    return $GLOBALS['db_connection'];
}

// End of database.php
