<?php
declare(strict_types=1);
/**
 * Response Helper Class
 * 
 * A simple utility class for sending standardized JSON responses from your API.
 * This class provides static methods to send success and error responses with
 * consistent formatting, proper HTTP headers, and status codes.
 * 
 * Features:
 * - Consistent JSON response structure
 * - Automatic HTTP status code setting
 * - UTF-8 encoding support
 * - Pretty-printed JSON for readability
 * - Includes app metadata and timestamps
 * 
 * Usage Examples:
 * 
 * Success Response:
 * ```php
 * Response::success('User created successfully', [
 *     'user_id' => 123,
 *     'username' => 'john_doe'
 * ]);
 * ```
 * 
 * Error Response:
 * ```php
 * Response::error('Validation failed', [
 *     'email' => 'Invalid email format',
 *     'password' => 'Password too short'
 * ], 422);
 * ```
 */

// Load configuration if available (for app metadata)
if (file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
}

class Response
{
    /**
     * Send a successful JSON response
     * 
     * This method sends an HTTP 200 status code along with a JSON response
     * containing the success message and any optional data payload.
     * 
     * Response Structure:
     * {
     *   "success": true,
     *   "message": "Operation completed successfully",
     *   "data": { ... },
     *   "app": { "name": "...", "version": "..." },
     *   "timestamp": "2025-11-21T10:30:00+01:00"
     * }
     * 
     * @param string $message Human-readable success message
     * @param mixed|null $data Optional data to include in the response (array, object, etc.)
     * @return void This method outputs JSON and terminates script execution
     */
    public static function success(string $message, $data = null): void
    {
        // Set the Content-Type header to JSON
        self::setJsonHeader();
        
        // Set HTTP status code to 200 (OK)
        http_response_code(200);
        
        // Build the response array
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'app' => self::getAppMetadata(),
            'timestamp' => self::getCurrentTimestamp(),
        ];
        
        // Output the JSON response
        self::outputJson($response);
    }
    
    /**
     * Send an error JSON response
     * 
     * This method sends an HTTP error status code (default 400) along with
     * a JSON response containing the error message and any validation errors.
     * 
     * Response Structure:
     * {
     *   "success": false,
     *   "message": "An error occurred",
     *   "errors": { ... },
     *   "app": { "name": "...", "version": "..." },
     *   "timestamp": "2025-11-21T10:30:00+01:00"
     * }
     * 
     * @param string $message Human-readable error message
     * @param array $errors Optional array of detailed errors (e.g., validation errors)
     * @param int $httpStatus HTTP status code (default 400 Bad Request)
     * @return void This method outputs JSON and terminates script execution
     */
    public static function error(string $message, array $errors = [], int $httpStatus = 400): void
    {
        // Set the Content-Type header to JSON
        self::setJsonHeader();
        
        // Set the HTTP status code
        http_response_code($httpStatus);
        
        // Build the response array
        $response = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'app' => self::getAppMetadata(),
            'timestamp' => self::getCurrentTimestamp(),
        ];
        
        // Output the JSON response
        self::outputJson($response);
    }
    
    /**
     * Set JSON Content-Type header
     * 
     * @return void
     */
    private static function setJsonHeader(): void
    {
        $contentType = defined('DEFAULT_CONTENT_TYPE') 
            ? DEFAULT_CONTENT_TYPE 
            : 'application/json; charset=utf-8';
            
        header('Content-Type: ' . $contentType);
    }
    
    /**
     * Get application metadata for inclusion in responses
     * 
     * @return array Array containing app name and version
     */
    private static function getAppMetadata(): array
    {
        return [
            'name' => defined('APP_NAME') ? APP_NAME : null,
            'version' => defined('APP_VERSION') ? APP_VERSION : null,
        ];
    }
    
    /**
     * Get current timestamp in ISO 8601 format
     * 
     * @return string Formatted timestamp (e.g., "2025-11-21T10:30:00+01:00")
     */
    private static function getCurrentTimestamp(): string
    {
        return date('c'); // ISO 8601 format
    }
    
    /**
     * Output JSON and terminate script execution
     * 
     * @param array $data Data to encode as JSON
     * @return void
     */
    private static function outputJson(array $data): void
    {
        // Encode with options for better readability and UTF-8 support
        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE |  // Don't escape Unicode characters
            JSON_UNESCAPED_SLASHES |  // Don't escape forward slashes
            JSON_PRETTY_PRINT         // Format with indentation
        );
        
        // Output the JSON
        echo $json;
        
        // Terminate script execution
        // Note: Remove this exit() if you need to continue execution after sending response
        exit;
    }
}

// End of Response.php
