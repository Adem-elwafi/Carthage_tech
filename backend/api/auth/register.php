<?php
declare(strict_types=1);
/**
 * User Registration Endpoint
 * 
 * Handles new user registration for the Carthage Tech e-commerce platform.
 * Accepts POST requests with user details, validates them, and creates a new account.
 * 
 * Expected POST data:
 * - email: User's email address (required, must be valid format)
 * - password: User's password (required, minimum 8 characters)
 * - first_name: User's first name (required)
 * - last_name: User's last name (required)
 * - phone: User's phone number (required)
 * 
 * Returns:
 * - Success: 200 with user data (without password)
 * - Error: 400/422 with validation errors or 500 for server errors
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

// ============================================
// CHECK REQUEST METHOD
// ============================================
// Only accept POST requests for registration
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed. Please use POST request.', [], 405);
}

// ============================================
// GET AND PARSE INPUT DATA
// ============================================
// Get raw POST data (works for both form-data and JSON)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// If JSON decode failed, try to get data from $_POST (form-data)
if (json_last_error() !== JSON_ERROR_NONE) {
    $data = $_POST;
}

// ============================================
// EXTRACT USER INPUT
// ============================================
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$firstName = trim($data['first_name'] ?? '');
$lastName = trim($data['last_name'] ?? '');
$phone = trim($data['phone'] ?? '');

// ============================================
// VALIDATION
// ============================================
$errors = [];

// Validate email (required and must be valid format)
if (empty($email)) {
    $errors['email'] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Invalid email format.';
}

// Validate password (required and minimum length)
if (empty($password)) {
    $errors['password'] = 'Password is required.';
} elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters long.';
}

// Validate first name (required)
if (empty($firstName)) {
    $errors['first_name'] = 'First name is required.';
}

// Validate last name (required)
if (empty($lastName)) {
    $errors['last_name'] = 'Last name is required.';
}

// Validate phone (required)
if (empty($phone)) {
    $errors['phone'] = 'Phone number is required.';
}

// If there are validation errors, return them
if (!empty($errors)) {
    Response::error('Validation failed. Please check your input.', $errors, 422);
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
// CHECK IF EMAIL ALREADY EXISTS
// ============================================
try {
    // Prepare SQL query to check for existing email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    
    // If email exists, return error
    if ($stmt->fetch()) {
        Response::error(
            'Registration failed.',
            ['email' => 'This email is already registered. Please use a different email or try logging in.'],
            422
        );
    }
    
} catch (PDOException $e) {
    Response::error('Database error while checking email.', ['error' => $e->getMessage()], 500);
}

// ============================================
// HASH PASSWORD
// ============================================
// Use bcrypt algorithm (default) to securely hash the password
// This creates a one-way hash that cannot be reversed
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

if ($hashedPassword === false) {
    Response::error('Failed to hash password. Please try again.', [], 500);
}

// ============================================
// INSERT NEW USER INTO DATABASE
// ============================================
try {
    // Prepare SQL insert statement
    $sql = 'INSERT INTO users (email, password, first_name, last_name, phone, role, created_at, updated_at) 
            VALUES (:email, :password, :first_name, :last_name, :phone, :role, NOW(), NOW())';
    
    $stmt = $pdo->prepare($sql);
    
    // Execute with user data
    $stmt->execute([
        'email' => $email,
        'password' => $hashedPassword,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => $phone,
        'role' => 'customer' // Default role for new registrations
    ]);
    
    // Get the ID of the newly created user
    $userId = (int) $pdo->lastInsertId();
    
    // ============================================
    // PREPARE SUCCESS RESPONSE
    // ============================================
    // Return user data WITHOUT the password hash
    $userData = [
        'id' => $userId,
        'email' => $email,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => $phone,
        'role' => 'customer'
    ];
    
    // Send success response
    Response::success(
        'Registration successful! You can now log in with your credentials.',
        ['user' => $userData]
    );
    
} catch (PDOException $e) {
    // Handle database errors
    Response::error(
        'Failed to create user account.',
        ['error' => 'Database error: ' . $e->getMessage()],
        500
    );
}

// End of register.php
