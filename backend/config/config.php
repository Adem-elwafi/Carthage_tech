<?php
declare(strict_types=1);
/**
 * Application Configuration - Carthage Tech Backend
 * 
 * This file defines all application-wide constants including:
 * - Base URLs and API endpoints
 * - Database connection settings
 * - Timezone and app metadata
 * - Debug settings for local development
 * 
 * @version 0.1.0
 * @author Carthage Tech Team
 */

// ============================================
// TIMEZONE CONFIGURATION
// ============================================
// Set the default timezone for all date/time functions
// This ensures consistent timestamps across the application
date_default_timezone_set('Africa/Tunis');

// ============================================
// APPLICATION METADATA
// ============================================
// Basic information about your application
define('APP_NAME', 'Carthage Tech Backend');
define('APP_VERSION', '0.1.0');

// ============================================
// URL CONFIGURATION
// ============================================
// Base URL for the application
// Adjust this if your project folder name is different
define('BASE_URL', 'http://localhost/charthage_tech/backend');

// API endpoint URL - where your API routes are mounted
define('API_URL', BASE_URL . '/api');

// ============================================
// DATABASE CONFIGURATION
// ============================================
// MySQL/MariaDB connection settings for local development
// These constants are used by database.php to create the PDO connection
define('DB_HOST', '127.0.0.1');           // Database server address
define('DB_NAME', 'carthage_tech_bd');    // Your database name
define('DB_USER', 'root');                // Database username (default for WAMP/XAMPP)
define('DB_PASS', '');                    // Database password (empty for default local setup)
define('DB_CHARSET', 'utf8mb4');          // Character encoding (supports emojis and international characters)

// ============================================
// APPLICATION SETTINGS
// ============================================
// Debug mode - set to true during development to see detailed error messages
// IMPORTANT: Set to false in production!
define('APP_DEBUG', true);

// Default content type for API responses
define('DEFAULT_CONTENT_TYPE', 'application/json; charset=utf-8');

// ============================================
// ENVIRONMENT
// ============================================
// Define the current environment (development, staging, production)
define('APP_ENV', 'development');

// End of configuration file
