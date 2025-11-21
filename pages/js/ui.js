/**
 * UI Utilities Module for Carthage Tech E-commerce
 * 
 * This file provides UI-related utility functions:
 * - Authentication state UI updates
 * - Cart badge management
 * - Toast notifications
 * - Page protection (login required)
 * - Loading indicators
 * 
 * Dependencies: api.js (must be loaded first)
 */

// ============================================================================
// AUTHENTICATION UI UPDATES
// ============================================================================

/**
 * Updates UI elements based on authentication state
 * 
 * This function should be called on every page load.
 * It updates the header to show/hide login/logout buttons
 * and displays the user's name if logged in.
 * 
 * Example usage:
 *   updateAuthUI();
 */
async function updateAuthUI() {
    try {
        // Check if user is logged in
        const user = getCurrentUser();
        
        // Find UI elements (these IDs should exist in your HTML)
        const loginBtn = document.getElementById('login-btn') || 
                        document.querySelector('.login-btn');
        const registerBtn = document.getElementById('register-btn') || 
                           document.querySelector('.register-btn');
        const logoutBtn = document.getElementById('logout-btn') || 
                         document.querySelector('.logout-btn');
        const userNameEl = document.getElementById('user-name') || 
                          document.querySelector('.user-name');
        const userMenuEl = document.getElementById('user-menu') || 
                          document.querySelector('.user-menu');
        
        if (user) {
            // USER IS LOGGED IN
            
            // Hide login and register buttons
            if (loginBtn) loginBtn.style.display = 'none';
            if (registerBtn) registerBtn.style.display = 'none';
            
            // Show logout button
            if (logoutBtn) logoutBtn.style.display = 'block';
            
            // Display user name
            if (userNameEl) {
                userNameEl.textContent = `Hello, ${user.first_name}!`;
                userNameEl.style.display = 'inline-block';
            }
            
            // Show user menu (if exists)
            if (userMenuEl) {
                userMenuEl.style.display = 'block';
            }
            
            // Update cart badge
            if (typeof updateCartBadge === 'function') {
                await updateCartBadge();
            }
            
        } else {
            // USER IS NOT LOGGED IN
            
            // Show login and register buttons
            if (loginBtn) loginBtn.style.display = 'inline-block';
            if (registerBtn) registerBtn.style.display = 'inline-block';
            
            // Hide logout button
            if (logoutBtn) logoutBtn.style.display = 'none';
            
            // Hide user name
            if (userNameEl) userNameEl.style.display = 'none';
            
            // Hide user menu
            if (userMenuEl) userMenuEl.style.display = 'none';
            
            // Hide cart badge
            const cartBadge = document.getElementById('cart-count');
            if (cartBadge) cartBadge.style.display = 'none';
        }
        
    } catch (error) {
        console.error('Error updating auth UI:', error);
    }
}

/**
 * Attaches logout functionality to logout button
 * 
 * Call this function on page load to enable logout button.
 */
function setupLogoutButton() {
    const logoutBtn = document.getElementById('logout-btn') || 
                     document.querySelector('.logout-btn');
    
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            
            const confirmed = confirm('Are you sure you want to log out?');
            if (confirmed) {
                await logout(true); // Defined in api.js
            }
        });
    }
}

// ============================================================================
// TOAST NOTIFICATIONS
// ============================================================================

/**
 * Displays a toast notification to the user
 * 
 * Creates a temporary message that appears on screen
 * and automatically disappears after a few seconds.
 * 
 * @param {string} message - Message text
 * @param {string} type - 'success', 'error', 'info', 'warning'
 * @param {number} duration - How long to show (milliseconds, default: 4000)
 * 
 * Example usage:
 *   showNotification('Product added to cart!', 'success');
 *   showNotification('Please log in first', 'error');
 */
function showNotification(message, type = 'info', duration = 4000) {
    // Check if notification container exists
    let container = document.getElementById('notification-container');
    
    // Create container if it doesn't exist
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
        `;
        document.body.appendChild(container);
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    // Set colors based on type
    let bgColor, textColor, borderColor;
    switch (type) {
        case 'success':
            bgColor = '#d4edda';
            textColor = '#155724';
            borderColor = '#c3e6cb';
            break;
        case 'error':
            bgColor = '#f8d7da';
            textColor = '#721c24';
            borderColor = '#f5c6cb';
            break;
        case 'warning':
            bgColor = '#fff3cd';
            textColor = '#856404';
            borderColor = '#ffeaa7';
            break;
        case 'info':
        default:
            bgColor = '#d1ecf1';
            textColor = '#0c5460';
            borderColor = '#bee5eb';
    }
    
    // Style the notification
    notification.style.cssText = `
        background-color: ${bgColor};
        color: ${textColor};
        border: 1px solid ${borderColor};
        border-radius: 5px;
        padding: 15px 20px;
        margin-bottom: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        animation: slideInRight 0.3s ease-out;
        display: flex;
        justify-content: space-between;
        align-items: center;
    `;
    
    // Set message content
    notification.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" 
                style="background: none; border: none; color: ${textColor}; 
                       font-size: 18px; cursor: pointer; margin-left: 15px;">
            ✕
        </button>
    `;
    
    // Add to container
    container.appendChild(notification);
    
    // Auto-remove after duration
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-out';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, duration);
}

// Add CSS animations for notifications
if (!document.getElementById('notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

// ============================================================================
// PAGE PROTECTION
// ============================================================================

/**
 * Protects a page by requiring authentication
 * 
 * Call this function at the top of pages that require login
 * (e.g., cart, checkout, orders pages).
 * Redirects to login if user is not authenticated.
 * 
 * @param {string} redirectUrl - URL to return to after login
 * 
 * Example usage:
 *   protectPage(); // Uses current page URL
 *   protectPage('checkout.html'); // Custom redirect
 */
function protectPage(redirectUrl = null) {
    if (!isLoggedIn()) {
        // Build redirect URL
        const returnUrl = redirectUrl || window.location.pathname + window.location.search;
        const encodedReturnUrl = encodeURIComponent(returnUrl);
        
        // Show notification
        showNotification('Please log in to access this page', 'error', 3000);
        
        // Redirect to login after 1 second
        setTimeout(() => {
            window.location.href = `login.html?redirect=${encodedReturnUrl}`;
        }, 1000);
        
        return false;
    }
    
    return true;
}

/**
 * Protects admin-only pages
 * 
 * @returns {boolean} - true if user is admin, false otherwise
 */
function protectAdminPage() {
    const user = getCurrentUser();
    
    if (!user) {
        protectPage();
        return false;
    }
    
    if (user.role !== 'admin') {
        showNotification('Access denied. Admin privileges required.', 'error');
        setTimeout(() => {
            window.location.href = 'index.html';
        }, 2000);
        return false;
    }
    
    return true;
}

// ============================================================================
// LOADING INDICATORS
// ============================================================================

/**
 * Shows a loading spinner in a container
 * 
 * @param {string} containerId - Container element ID
 * @param {string} message - Loading message (optional)
 * 
 * Example usage:
 *   showLoading('products-container', 'Loading products...');
 */
function showLoading(containerId, message = 'Loading...') {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = `
        <div class="loading-container" style="
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            min-height: 200px;
        ">
            <div class="spinner" style="
                border: 4px solid #f3f3f3;
                border-top: 4px solid #3498db;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
            "></div>
            <p style="margin-top: 15px; color: #666;">${message}</p>
        </div>
    `;
}

// Add spinner animation
if (!document.getElementById('spinner-styles')) {
    const style = document.createElement('style');
    style.id = 'spinner-styles';
    style.textContent = `
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
}

/**
 * Hides loading indicator
 * 
 * @param {string} containerId - Container element ID
 */
function hideLoading(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const loading = container.querySelector('.loading-container');
    if (loading) {
        loading.remove();
    }
}

// ============================================================================
// CART BADGE
// ============================================================================

/**
 * Updates cart count badge in header
 * 
 * This function is also defined in cart.js but included here
 * for pages that don't load cart.js.
 */
async function updateCartBadge() {
    try {
        // Only update if user is logged in
        if (!isLoggedIn()) {
            const badge = document.getElementById('cart-count');
            if (badge) badge.style.display = 'none';
            return;
        }
        
        // Get cart count (requires cart.js or direct API call)
        let count = 0;
        
        if (typeof getCartCount === 'function') {
            // Use cart.js function if available
            count = await getCartCount();
        } else {
            // Direct API call
            try {
                const response = await apiCall('/cart/count.php', 'GET');
                count = response.data.count || 0;
            } catch (error) {
                console.error('Error getting cart count:', error);
                count = 0;
            }
        }
        
        // Update badge
        const badge = document.getElementById('cart-count') || 
                     document.querySelector('.cart-count');
        
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'inline-block';
            } else {
                badge.textContent = '0';
                badge.style.display = 'none';
            }
        }
        
    } catch (error) {
        console.error('Error updating cart badge:', error);
    }
}

// ============================================================================
// MODAL UTILITIES
// ============================================================================

/**
 * Shows a modal dialog
 * 
 * @param {string} title - Modal title
 * @param {string} content - Modal content (HTML)
 * @param {array} buttons - Array of button objects { text, onClick, className }
 * 
 * Example usage:
 *   showModal('Confirm Delete', 'Are you sure?', [
 *       { text: 'Cancel', onClick: () => closeModal() },
 *       { text: 'Delete', onClick: () => deleteItem(), className: 'btn-danger' }
 *   ]);
 */
function showModal(title, content, buttons = []) {
    // Remove existing modal if any
    const existingModal = document.getElementById('custom-modal');
    if (existingModal) existingModal.remove();
    
    // Create modal
    const modal = document.createElement('div');
    modal.id = 'custom-modal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10001;
    `;
    
    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: white;
        padding: 30px;
        border-radius: 8px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    `;
    
    modalContent.innerHTML = `
        <h2 style="margin-top: 0;">${title}</h2>
        <div class="modal-body">${content}</div>
        <div class="modal-buttons" style="
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        "></div>
    `;
    
    // Add buttons
    const buttonsContainer = modalContent.querySelector('.modal-buttons');
    buttons.forEach(btn => {
        const button = document.createElement('button');
        button.textContent = btn.text;
        button.className = btn.className || 'btn btn-secondary';
        button.onclick = () => {
            if (btn.onClick) btn.onClick();
            closeModal();
        };
        buttonsContainer.appendChild(button);
    });
    
    modal.appendChild(modalContent);
    document.body.appendChild(modal);
    
    // Close on background click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
}

/**
 * Closes the current modal
 */
function closeModal() {
    const modal = document.getElementById('custom-modal');
    if (modal) modal.remove();
}

// ============================================================================
// AUTO-INITIALIZATION
// ============================================================================

/**
 * Initialize UI utilities when DOM is ready
 * 
 * This function runs automatically on every page.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Update authentication UI
    updateAuthUI();
    
    // Setup logout button
    setupLogoutButton();
    
    // Update cart badge
    updateCartBadge();
});

// ============================================================================
// USAGE NOTES
// ============================================================================

/**
 * HOW TO USE IN HTML:
 * 
 * 1. Include this file in all pages:
 *    <script src="js/api.js"></script>
 *    <script src="js/ui.js"></script>
 * 
 * 2. Add these elements to your header/navbar:
 *    <a href="login.html" id="login-btn">Login</a>
 *    <a href="register.html" id="register-btn">Register</a>
 *    <button id="logout-btn" style="display:none;">Logout</button>
 *    <span id="user-name" style="display:none;"></span>
 *    <a href="cart.html">Cart <span id="cart-count" class="badge">0</span></a>
 * 
 * 3. For protected pages (cart, checkout, orders), add at top of page:
 *    <script>protectPage();</script>
 * 
 * 4. Show notifications from any page:
 *    showNotification('Item added!', 'success');
 * 
 * All UI updates happen automatically when the page loads.
 */
