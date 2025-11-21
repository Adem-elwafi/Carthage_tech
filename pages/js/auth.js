/**
 * Authentication Module for Carthage Tech E-commerce
 * 
 * This file handles user registration and login functionality.
 * It includes form validation, API calls, and session management.
 * 
 * Dependencies: api.js (must be loaded first)
 */

// ============================================================================
// USER REGISTRATION
// ============================================================================

/**
 * Validates registration form data
 * 
 * Checks that all required fields are filled and meet requirements.
 * 
 * @param {object} formData - Form data object
 * @returns {object} - { valid: boolean, errors: array }
 */
function validateRegistrationForm(formData) {
    const errors = [];
    
    // Email validation
    // Regular expression to check valid email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!formData.email || !emailRegex.test(formData.email)) {
        errors.push('Please enter a valid email address');
    }
    
    // Password validation
    if (!formData.password || formData.password.length < 6) {
        errors.push('Password must be at least 6 characters long');
    }
    
    // Confirm password validation
    if (formData.password !== formData.confirmPassword) {
        errors.push('Passwords do not match');
    }
    
    // First name validation
    if (!formData.first_name || formData.first_name.trim().length < 2) {
        errors.push('First name must be at least 2 characters');
    }
    
    // Last name validation
    if (!formData.last_name || formData.last_name.trim().length < 2) {
        errors.push('Last name must be at least 2 characters');
    }
    
    // Phone validation (8 digits for Tunisia)
    const phoneRegex = /^\d{8}$/;
    if (!formData.phone || !phoneRegex.test(formData.phone)) {
        errors.push('Phone number must be 8 digits');
    }
    
    return {
        valid: errors.length === 0,
        errors: errors
    };
}

/**
 * Handles user registration
 * 
 * Steps:
 * 1. Get form data
 * 2. Validate data
 * 3. Call registration API
 * 4. Show success/error messages
 * 5. Redirect to login on success
 * 
 * @param {Event} event - Form submit event
 */
async function handleRegister(event) {
    // Prevent default form submission (page reload)
    event.preventDefault();
    
    // Get form element
    const form = event.target;
    
    // Collect form data
    // FormData is a Web API for collecting form values
    const formDataObj = new FormData(form);
    const formData = {
        email: formDataObj.get('email'),
        password: formDataObj.get('password'),
        confirmPassword: formDataObj.get('confirm-password'), // Not sent to API
        first_name: formDataObj.get('first_name'),
        last_name: formDataObj.get('last_name'),
        phone: formDataObj.get('phone')
    };
    
    // Validate form data
    const validation = validateRegistrationForm(formData);
    if (!validation.valid) {
        // Show all validation errors
        showMessage(validation.errors.join('<br>'), 'error');
        return;
    }
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Registering...';
    
    try {
        // Prepare data for API (exclude confirmPassword)
        const apiData = {
            email: formData.email,
            password: formData.password,
            first_name: formData.first_name,
            last_name: formData.last_name,
            phone: formData.phone
        };
        
        // Call registration API
        const response = await apiCall('/auth/register.php', 'POST', apiData);
        
        // Show success message
        showMessage(response.message || 'Registration successful! Redirecting to login...', 'success');
        
        // Redirect to login page after 2 seconds
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 2000);
        
    } catch (error) {
        // Show error message from API
        showMessage(error.message || 'Registration failed. Please try again.', 'error');
        
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.textContent = originalBtnText;
    }
}

// ============================================================================
// USER LOGIN
// ============================================================================

/**
 * Validates login form data
 * 
 * @param {object} formData - Form data object
 * @returns {object} - { valid: boolean, errors: array }
 */
function validateLoginForm(formData) {
    const errors = [];
    
    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!formData.email || !emailRegex.test(formData.email)) {
        errors.push('Please enter a valid email address');
    }
    
    // Password validation
    if (!formData.password || formData.password.length === 0) {
        errors.push('Please enter your password');
    }
    
    return {
        valid: errors.length === 0,
        errors: errors
    };
}

/**
 * Handles user login
 * 
 * Steps:
 * 1. Get form data
 * 2. Validate data
 * 3. Call login API
 * 4. Save user data to localStorage
 * 5. Redirect to homepage
 * 
 * @param {Event} event - Form submit event
 */
async function handleLogin(event) {
    // Prevent default form submission
    event.preventDefault();
    
    // Get form element
    const form = event.target;
    
    // Collect form data
    const formDataObj = new FormData(form);
    const formData = {
        email: formDataObj.get('email'),
        password: formDataObj.get('password')
    };
    
    // Validate form data
    const validation = validateLoginForm(formData);
    if (!validation.valid) {
        showMessage(validation.errors.join('<br>'), 'error');
        return;
    }
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Logging in...';
    
    try {
        // Call login API
        const response = await apiCall('/auth/login.php', 'POST', formData);
        
        // Save user data to localStorage
        // This allows us to check login status without calling API every time
        if (response.data && response.data.user) {
            saveUserData(response.data.user);
        }
        
        // Show success message
        showMessage(response.message || 'Login successful! Redirecting...', 'success');
        
        // Redirect to homepage after 1 second
        setTimeout(() => {
            // Check if there's a redirect URL in query params
            const urlParams = new URLSearchParams(window.location.search);
            const redirectUrl = urlParams.get('redirect') || 'index.html';
            window.location.href = redirectUrl;
        }, 1000);
        
    } catch (error) {
        // Show error message
        showMessage(error.message || 'Login failed. Please check your credentials.', 'error');
        
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.textContent = originalBtnText;
    }
}

// ============================================================================
// PASSWORD RESET (Placeholder for future implementation)
// ============================================================================

/**
 * Handles forgot password request
 * 
 * NOTE: This is a placeholder. Backend API doesn't have this endpoint yet.
 * 
 * @param {Event} event - Form submit event
 */
async function handleForgotPassword(event) {
    event.preventDefault();
    
    const form = event.target;
    const email = new FormData(form).get('email');
    
    if (!email) {
        showMessage('Please enter your email address', 'error');
        return;
    }
    
    // TODO: Implement when backend API is ready
    showMessage('Password reset functionality will be available soon', 'info');
}

// ============================================================================
// MESSAGE DISPLAY HELPER
// ============================================================================

/**
 * Displays a message to the user
 * 
 * This function looks for an element with id="message" or id="notification"
 * and displays the message there.
 * 
 * @param {string} message - Message text (can include HTML)
 * @param {string} type - Message type: 'success', 'error', 'info'
 */
function showMessage(message, type = 'info') {
    // Look for message container
    let messageEl = document.getElementById('message') || 
                    document.getElementById('notification');
    
    // If no message container exists, create one
    if (!messageEl) {
        messageEl = document.createElement('div');
        messageEl.id = 'notification';
        messageEl.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            max-width: 400px;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 9999;
            animation: slideIn 0.3s ease-out;
        `;
        document.body.appendChild(messageEl);
    }
    
    // Set message content
    messageEl.innerHTML = message;
    
    // Set styling based on type
    if (type === 'success') {
        messageEl.style.backgroundColor = '#d4edda';
        messageEl.style.color = '#155724';
        messageEl.style.border = '1px solid #c3e6cb';
    } else if (type === 'error') {
        messageEl.style.backgroundColor = '#f8d7da';
        messageEl.style.color = '#721c24';
        messageEl.style.border = '1px solid #f5c6cb';
    } else {
        messageEl.style.backgroundColor = '#d1ecf1';
        messageEl.style.color = '#0c5460';
        messageEl.style.border = '1px solid #bee5eb';
    }
    
    // Show message
    messageEl.style.display = 'block';
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        messageEl.style.display = 'none';
    }, 5000);
}

// ============================================================================
// AUTO-INITIALIZATION
// ============================================================================

/**
 * Initialize authentication forms when DOM is ready
 * 
 * This function runs automatically when the page loads.
 * It attaches event listeners to login and registration forms.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Find and attach login form handler
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    // Find and attach registration form handler
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }
    
    // Find and attach forgot password form handler
    const forgotPasswordForm = document.getElementById('forgot-password-form');
    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', handleForgotPassword);
    }
    
    // If user is already logged in, redirect to homepage
    if (isLoggedIn() && (loginForm || registerForm)) {
        const user = getCurrentUser();
        if (user) {
            console.log('User already logged in, redirecting...');
            window.location.href = 'index.html';
        }
    }
});

// ============================================================================
// USAGE NOTES
// ============================================================================

/**
 * HOW TO USE IN HTML:
 * 
 * 1. Include required scripts in <head> or before </body>:
 *    <script src="js/api.js"></script>
 *    <script src="js/auth.js"></script>
 * 
 * 2. Create login form with id="login-form":
 *    <form id="login-form">
 *        <input type="email" name="email" required>
 *        <input type="password" name="password" required>
 *        <button type="submit">Login</button>
 *    </form>
 * 
 * 3. Create registration form with id="register-form":
 *    <form id="register-form">
 *        <input type="email" name="email" required>
 *        <input type="password" name="password" required>
 *        <input type="password" name="confirm-password" required>
 *        <input type="text" name="first_name" required>
 *        <input type="text" name="last_name" required>
 *        <input type="tel" name="phone" required>
 *        <button type="submit">Register</button>
 *    </form>
 * 
 * 4. Add message container (optional):
 *    <div id="notification"></div>
 * 
 * The scripts will automatically attach event handlers when page loads.
 */
