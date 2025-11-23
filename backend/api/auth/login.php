<?php
declare(strict_types=1);
/**
 * User Login Endpoint
 * 
 * Handles user authentication for the Carthage Tech e-commerce platform.
 * Accepts POST requests with email and password, validates credentials,
 * and creates a session for authenticated users.
 * 
 * Expected POST data:
 * - email: User's email address (required)
 * - password: User's password (required)
 * 
 * Returns:
 * - Success: 200 with user data and session created
 * - Error: 401 for invalid credentials, 400 for validation errors
 */

// ============================================
// CORS AND HEADERS CONFIGURATION
// ============================================
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/auth.php';

// ============================================
// CHECK REQUEST METHOD
// ============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed. Please use POST request.', [], 405);
}

// ============================================
// START SESSION
// ============================================
// Start session to store user data after successful login
startSessionIfNotStarted();

// ============================================
// GET AND PARSE INPUT DATA
// ============================================
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// If JSON decode failed, try $_POST
if (json_last_error() !== JSON_ERROR_NONE) {
    $data = $_POST;
}

// ============================================
// EXTRACT USER INPUT
// ============================================
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

// ============================================
// VALIDATION
// ============================================
$errors = [];

// Validate email
if (empty($email)) {
    $errors['email'] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Invalid email format.';
}

// Validate password
if (empty($password)) {
    $errors['password'] = 'Password is required.';
}

// Return validation errors if any
if (!empty($errors)) {
    Response::error('Validation failed. Please check your input.', $errors, 400);
}

// ============================================
// DATABASE CONNECTION
// ============================================
try {
    $pdo = getDatabaseConnection();
    
    if ($pdo === null) {
        Response::error('Database connection failed.', [], 500);
    }
    
} catch (Exception $e) {
    Response::error('Server error: Unable to connect to database.', [], 500);
}

// ============================================
// FIND USER BY EMAIL
// ============================================
try {
    // Prepare SQL query to find user by email
    // We need: id, email, password (hash), first_name, last_name, role
    $sql = 'SELECT id, email, password, first_name, last_name, phone, role 
            FROM users 
            WHERE email = :email 
            LIMIT 1';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['email' => $email]);
    
    // Fetch user data
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If user not found, return error
    if (!$user) {
        Response::error(
            'Invalid credentials.',
            ['auth' => 'No account found with this email address.'],
            401 // 401 Unauthorized
        );
    }
    
} catch (PDOException $e) {
    Response::error('Database error while finding user.', ['error' => $e->getMessage()], 500);
}

// ============================================
// VERIFY PASSWORD
// ============================================
// Use password_verify() to check if the provided password matches the hash
$passwordIsValid = password_verify($password, $user['password']);

if (!$passwordIsValid) {
    // Password doesn't match - return error
    Response::error(
        'Invalid credentials.',
        ['auth' => 'The password you entered is incorrect.'],
        401 // 401 Unauthorized
    );
}

// ============================================
// PASSWORD IS VALID - CREATE SESSION
// ============================================
// Store user information in session (exclude password hash!)
$_SESSION['user'] = [
    'id' => (int) $user['id'],
    'email' => $user['email'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'phone' => $user['phone'],
    'role' => $user['role']
];

// Optional: Store login time
$_SESSION['login_time'] = time();

// Regenerate session ID for security (prevents session fixation attacks)
session_regenerate_id(true);

// ============================================
// UPDATE LAST LOGIN TIME (Optional)
// ============================================
try {
    // You can add a 'last_login' column to track when users log in
    $updateStmt = $pdo->prepare('UPDATE users SET updated_at = NOW() WHERE id = :id');
    $updateStmt->execute(['id' => $user['id']]);
} catch (PDOException $e) {
    // This is not critical, so just log it (don't fail the login)
    error_log('Failed to update last login time: ' . $e->getMessage());
}

// ============================================
// PREPARE SUCCESS RESPONSE
// ============================================
// Return user data (without password) and session info
$userData = [
    'id' => (int) $user['id'],
    'email' => $user['email'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'phone' => $user['phone'],
    'role' => $user['role']
];

Response::success(
    'Login successful! Welcome back, ' . $user['first_name'] . '!',
    [
        'user' => $userData,
        'session_id' => session_id() // Include session ID for reference
    ]
);

// End of login.php
