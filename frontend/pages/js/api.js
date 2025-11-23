/**
 * API Helper Module for Carthage Tech E-commerce
 * 
 * This file contains all the core functions for communicating with the backend API.
 * It handles HTTP requests, session management, and provides utility functions
 * for authentication and user management.
 * 
 * Key Concepts:
 * - fetch(): Modern JavaScript API for making HTTP requests
 * - async/await: Syntax for handling asynchronous operations
 * - localStorage: Browser storage for persisting user data
 * - credentials: 'include' - Sends cookies with requests (needed for PHP sessions)
 */

// ============================================================================
// CONFIGURATION
// ============================================================================

/**
 * Base URL for all API endpoints
 * Change this if your backend is hosted elsewhere
 */
const BASE_URL = 'http://localhost/charthage_tech/backend/api';

// ============================================================================
// CORE API FUNCTIONS
// ============================================================================

/**
 * Makes an API call to the backend
 * 
 * This is the main function used by all other API functions.
 * It handles the HTTP request, response parsing, and error handling.
 * 
 * @param {string} endpoint - The API endpoint (e.g., '/auth/login.php')
 * @param {string} method - HTTP method: 'GET', 'POST', 'PUT', 'DELETE'
 * @param {object|null} data - Data to send in request body (for POST/PUT/DELETE)
 * @returns {Promise<object>} - Parsed JSON response from API
 * @throws {Error} - Throws error if request fails or API returns error
 * 
 * Example usage:
 *   const user = await apiCall('/auth/login.php', 'POST', { email, password });
 */
async function apiCall(endpoint, method = 'GET', data = null) {
    try {
        // Construct full URL by combining base URL and endpoint
        const url = BASE_URL + endpoint;
        
        // Configure fetch options
        const options = {
            method: method, // HTTP method (GET, POST, PUT, DELETE, etc.)
            headers: {
                'Content-Type': 'application/json', // Tell server we're sending JSON
            },
            credentials: 'include' // IMPORTANT: Include cookies for PHP session management
        };
        
        // Add request body for POST/PUT/DELETE requests with data
        // JSON.stringify() converts JavaScript object to JSON string
        if (data && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
            options.body = JSON.stringify(data);
        }
        
        // Make the HTTP request
        // fetch() returns a Promise, await waits for it to complete
        const response = await fetch(url, options);
        
        // Handle 401 Unauthorized - redirect to login
        if (response.status === 401) {
            console.warn('Authentication required - session expired or invalid');
            localStorage.removeItem('user');
            window.location.href = 'login.html?redirect=' + encodeURIComponent(window.location.href);
            throw new Error('Authentication required');
        }
        
        // Parse JSON response
        // await waits for the JSON parsing to complete
        const result = await response.json();
        
        // Check if API returned an error
        if (!result.success) {
            // Throw error with the message from API
            throw new Error(result.message || 'API request failed');
        }
        
        // Return the successful response
        return result;
        
    } catch (error) {
        // Handle CORS errors specifically
        if (error.message.includes('CORS') || error.name === 'TypeError' && error.message.includes('fetch')) {
            console.error('CORS Error: Make sure you are accessing the page via http://localhost (not file://)');
            console.error('Backend must allow Origin: http://localhost with credentials');
        }
        
        // Log error to console for debugging
        console.error('API Call Error:', error);
        // Re-throw error so calling code can handle it
        throw error;
    }
}

// ============================================================================
// AUTHENTICATION & SESSION MANAGEMENT
// ============================================================================

/**
 * Checks if a user is currently logged in
 * 
 * This function checks localStorage for saved user data.
 * localStorage persists data even after browser is closed.
 * 
 * @returns {boolean} - true if user is logged in, false otherwise
 * 
 * Example usage:
 *   if (isLoggedIn()) {
 *       console.log('User is logged in');
 *   }
 */
function isLoggedIn() {
    // localStorage.getItem() retrieves data by key
    // Returns null if key doesn't exist
    const user = localStorage.getItem('user');
    
    // Convert to boolean: returns true if user exists, false if null
    return user !== null;
}

/**
 * Gets the current logged-in user's data
 * 
 * Retrieves and parses user data from localStorage.
 * 
 * @returns {object|null} - User object if logged in, null otherwise
 * 
 * Example usage:
 *   const user = getCurrentUser();
 *   if (user) {
 *       console.log('Welcome, ' + user.first_name);
 *   }
 */
function getCurrentUser() {
    try {
        // Get user data from localStorage
        const userJson = localStorage.getItem('user');
        
        // Return null if no user data
        if (!userJson) return null;
        
        // Parse JSON string back to JavaScript object
        // JSON.parse() converts JSON string to object
        return JSON.parse(userJson);
        
    } catch (error) {
        // If parsing fails (corrupted data), log error and return null
        console.error('Error parsing user data:', error);
        return null;
    }
}

/**
 * Saves user data to localStorage after login
 * 
 * @param {object} user - User object from API response
 * 
 * Example usage:
 *   saveUserData({ id: 1, email: 'user@example.com', first_name: 'John' });
 */
function saveUserData(user) {
    // JSON.stringify() converts JavaScript object to JSON string
    // localStorage only stores strings
    localStorage.setItem('user', JSON.stringify(user));
}

/**
 * Logs out the current user
 * 
 * This function:
 * 1. Calls the backend logout API to destroy PHP session
 * 2. Clears user data from localStorage
 * 3. Optionally redirects to login page
 * 
 * @param {boolean} redirect - Whether to redirect to login page after logout
 * @returns {Promise<void>}
 * 
 * Example usage:
 *   await logout(true); // Logout and redirect to login page
 */
async function logout(redirect = true) {
    try {
        // Call backend logout API to destroy PHP session
        await apiCall('/auth/logout.php', 'POST');
        
        // Clear all user data from localStorage
        localStorage.removeItem('user');
        
        // Optional: Redirect to login page
        if (redirect) {
            window.location.href = 'login.html';
        }
        
        console.log('Logout successful');
        
    } catch (error) {
        console.error('Logout error:', error);
        
        // Even if API call fails, clear local data
        localStorage.removeItem('user');
        
        if (redirect) {
            window.location.href = 'login.html';
        }
    }
}

/**
 * Checks authentication status with the backend
 * 
 * Unlike isLoggedIn() which only checks localStorage,
 * this function verifies the session with the backend server.
 * 
 * @returns {Promise<object|null>} - User object if authenticated, null otherwise
 * 
 * Example usage:
 *   const user = await checkAuthStatus();
 *   if (user) {
 *       console.log('Server confirmed you are logged in');
 *   }
 */
async function checkAuthStatus() {
    try {
        // Call backend to verify PHP session
        const response = await apiCall('/auth/me.php', 'GET');
        
        // If successful, save/update user data in localStorage
        if (response.data && response.data.user) {
            saveUserData(response.data.user);
            return response.data.user;
        }
        
        return null;
        
    } catch (error) {
        // If API returns 401 (unauthorized), user is not logged in
        console.log('Not authenticated');
        // Clear any stale localStorage data
        localStorage.removeItem('user');
        return null;
    }
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Shows a loading indicator
 * 
 * @param {string} elementId - ID of element to show loading state
 * 
 * Example usage:
 *   showLoading('products-container');
 */
function showLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = '<div class="loading">Loading...</div>';
    }
}

/**
 * Hides a loading indicator
 * 
 * @param {string} elementId - ID of element to hide loading state
 */
function hideLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        const loading = element.querySelector('.loading');
        if (loading) {
            loading.remove();
        }
    }
}

/**
 * Formats price with currency
 * 
 * @param {number|string} price - Price value
 * @param {string} currency - Currency symbol (default: 'TND')
 * @returns {string} - Formatted price string
 * 
 * Example usage:
 *   formatPrice(2499.00); // Returns "2499.00 TND"
 */
function formatPrice(price, currency = 'TND') {
    const numPrice = typeof price === 'string' ? parseFloat(price) : price;
    return `${numPrice.toFixed(2)} ${currency}`;
}

/**
 * Formats date to readable string
 * 
 * @param {string} dateString - ISO date string
 * @returns {string} - Formatted date
 * 
 * Example usage:
 *   formatDate('2025-11-21 14:30:00'); // Returns "November 21, 2025"
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// ============================================================================
// EXPORT NOTE
// ============================================================================

/**
 * NOTE: All functions in this file are available globally.
 * Include this file in your HTML with:
 * <script src="js/api.js"></script>
 * 
 * Then you can use any function like:
 * apiCall('/products/list.php', 'GET')
 * isLoggedIn()
 * getCurrentUser()
 * etc.
 */
