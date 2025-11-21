/**
 * Shopping Cart Module for Carthage Tech E-commerce
 * 
 * This file handles all shopping cart functionality:
 * - Adding products to cart
 * - Viewing cart contents
 * - Updating item quantities
 * - Removing items
 * - Clearing cart
 * - Getting cart count for badge
 * 
 * Dependencies: api.js, ui.js (must be loaded first)
 */

// ============================================================================
// ADD TO CART
// ============================================================================

/**
 * Adds a product to the shopping cart
 * 
 * If product already exists in cart, increases quantity.
 * Requires user to be logged in.
 * 
 * @param {number} productId - Product ID to add
 * @param {number} quantity - Quantity to add (default: 1)
 * @returns {Promise<object>} - API response data
 * 
 * Example usage:
 *   await addToCart(1, 2); // Add product ID 1, quantity 2
 */
async function addToCart(productId, quantity = 1) {
    try {
        // Check if user is logged in
        if (!isLoggedIn()) {
            // Show login required message
            if (typeof showNotification === 'function') {
                showNotification('Please log in to add items to cart', 'error');
            }
            
            // Redirect to login with return URL
            const currentUrl = encodeURIComponent(window.location.href);
            setTimeout(() => {
                window.location.href = `login.html?redirect=${currentUrl}`;
            }, 1500);
            return;
        }
        
        // Validate inputs
        if (!productId || productId <= 0) {
            throw new Error('Invalid product ID');
        }
        
        if (!quantity || quantity <= 0) {
            throw new Error('Quantity must be at least 1');
        }
        
        // Prepare data for API
        const data = {
            product_id: productId,
            quantity: quantity
        };
        
        // Call API
        const response = await apiCall('/cart/add.php', 'POST', data);
        
        // Show success notification
        const message = response.data.action === 'added' 
            ? `${response.data.product.name} added to cart!`
            : `Cart updated! ${response.data.product.name} quantity: ${response.data.new_quantity}`;
        
        if (typeof showNotification === 'function') {
            showNotification(message, 'success');
        }
        
        // Update cart count badge
        await updateCartBadge();
        
        return response.data;
        
    } catch (error) {
        console.error('Error adding to cart:', error);
        
        if (typeof showNotification === 'function') {
            showNotification(error.message || 'Failed to add to cart', 'error');
        }
        
        throw error;
    }
}

// ============================================================================
// VIEW CART
// ============================================================================

/**
 * Loads and displays cart contents
 * 
 * @param {string} containerId - Container element ID (default: 'cart-container')
 * @returns {Promise<object>} - Cart data with items and summary
 * 
 * Example usage:
 *   await loadCart('cart-container');
 */
async function loadCart(containerId = 'cart-container') {
    try {
        // Check if user is logged in
        if (!isLoggedIn()) {
            if (typeof protectPage === 'function') {
                protectPage(); // Redirect to login
            }
            return;
        }
        
        showLoading(containerId);
        
        // Call API to get cart
        const response = await apiCall('/cart/view.php', 'GET');
        const { cart_items, summary } = response.data;
        
        // Render cart
        renderCart(cart_items, summary, containerId);
        
        // Update cart badge
        await updateCartBadge();
        
        return response.data;
        
    } catch (error) {
        console.error('Error loading cart:', error);
        
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `
                <div class="error-message">
                    <p>Failed to load cart. Please try again.</p>
                    <p>${error.message}</p>
                </div>
            `;
        }
        
        throw error;
    }
}

/**
 * Renders cart items and summary
 * 
 * @param {array} cartItems - Array of cart item objects
 * @param {object} summary - Cart summary (totals, counts)
 * @param {string} containerId - Container element ID
 */
function renderCart(cartItems, summary, containerId = 'cart-container') {
    const container = document.getElementById(containerId);
    if (!container) {
        console.error('Cart container not found:', containerId);
        return;
    }
    
    // If cart is empty
    if (!cartItems || cartItems.length === 0) {
        container.innerHTML = `
            <div class="empty-cart">
                <h2>Your cart is empty</h2>
                <p>Start shopping to add items to your cart!</p>
                <a href="index.html" class="btn btn-primary">Continue Shopping</a>
            </div>
        `;
        return;
    }
    
    // Generate cart HTML
    const html = `
        <div class="cart-content">
            <div class="cart-items">
                <h2>Shopping Cart (${summary.total_items} items)</h2>
                
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${cartItems.map(item => `
                            <tr class="cart-item" data-cart-id="${item.cart_id}">
                                <td class="product-info">
                                    <img src="${item.product.image_url}" 
                                         alt="${item.product.name}"
                                         onerror="this.src='images/placeholder.jpg'">
                                    <div>
                                        <h4>${item.product.name}</h4>
                                        ${item.product.brand ? `<p class="brand">${item.product.brand}</p>` : ''}
                                        ${!item.in_stock ? '<span class="out-of-stock-warning">Out of Stock</span>' : ''}
                                    </div>
                                </td>
                                
                                <td class="price">${formatPrice(item.product.price)}</td>
                                
                                <td class="quantity">
                                    <div class="quantity-controls">
                                        <button onclick="updateQuantity(${item.cart_id}, ${item.quantity - 1})"
                                                ${item.quantity <= 1 ? 'disabled' : ''}>
                                            -
                                        </button>
                                        <input type="number" 
                                               value="${item.quantity}" 
                                               min="1" 
                                               max="${item.stock_available}"
                                               onchange="updateQuantity(${item.cart_id}, this.value)"
                                               id="qty-${item.cart_id}">
                                        <button onclick="updateQuantity(${item.cart_id}, ${item.quantity + 1})"
                                                ${item.quantity >= item.stock_available ? 'disabled' : ''}>
                                            +
                                        </button>
                                    </div>
                                    <small>${item.stock_available} available</small>
                                </td>
                                
                                <td class="subtotal">
                                    <strong>${formatPrice(item.subtotal)}</strong>
                                </td>
                                
                                <td class="actions">
                                    <button class="btn-remove" 
                                            onclick="removeFromCart(${item.cart_id})"
                                            title="Remove from cart">
                                        🗑️ Remove
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                
                <div class="cart-actions">
                    <button class="btn btn-secondary" onclick="clearCart()">
                        Clear Cart
                    </button>
                    <a href="index.html" class="btn btn-secondary">Continue Shopping</a>
                </div>
            </div>
            
            <div class="cart-summary">
                <h3>Order Summary</h3>
                
                <div class="summary-row">
                    <span>Items (${summary.total_unique_products}):</span>
                    <span>${summary.total_items} total</span>
                </div>
                
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span>${formatPrice(summary.cart_total)}</span>
                </div>
                
                <div class="summary-row total">
                    <strong>Total:</strong>
                    <strong>${formatPrice(summary.cart_total)}</strong>
                </div>
                
                <button class="btn btn-primary btn-checkout" 
                        onclick="proceedToCheckout()">
                    Proceed to Checkout
                </button>
            </div>
        </div>
    `;
    
    container.innerHTML = html;
}

// ============================================================================
// UPDATE CART ITEM
// ============================================================================

/**
 * Updates the quantity of a cart item
 * 
 * @param {number} cartId - Cart item ID (not product ID)
 * @param {number} newQuantity - New quantity value
 * @returns {Promise<object>} - Updated cart item data
 * 
 * Example usage:
 *   await updateQuantity(5, 3); // Set cart item 5 to quantity 3
 */
async function updateQuantity(cartId, newQuantity) {
    try {
        // Validate quantity
        newQuantity = parseInt(newQuantity);
        if (isNaN(newQuantity) || newQuantity < 1) {
            throw new Error('Quantity must be at least 1');
        }
        
        // Prepare data
        const data = {
            cart_id: cartId,
            quantity: newQuantity
        };
        
        // Call API
        const response = await apiCall('/cart/update.php', 'POST', data);
        
        // Show success notification
        if (typeof showNotification === 'function') {
            showNotification('Cart updated successfully', 'success');
        }
        
        // Reload cart display
        await loadCart();
        
        return response.data;
        
    } catch (error) {
        console.error('Error updating cart:', error);
        
        if (typeof showNotification === 'function') {
            showNotification(error.message || 'Failed to update cart', 'error');
        }
        
        throw error;
    }
}

// ============================================================================
// REMOVE FROM CART
// ============================================================================

/**
 * Removes an item from the cart
 * 
 * @param {number} cartId - Cart item ID to remove
 * @returns {Promise<object>} - API response
 * 
 * Example usage:
 *   await removeFromCart(5);
 */
async function removeFromCart(cartId) {
    try {
        // Confirm removal
        const confirmed = confirm('Are you sure you want to remove this item from your cart?');
        if (!confirmed) return;
        
        // Prepare data
        const data = { cart_id: cartId };
        
        // Call API
        const response = await apiCall('/cart/remove.php', 'POST', data);
        
        // Show success notification
        if (typeof showNotification === 'function') {
            showNotification('Item removed from cart', 'success');
        }
        
        // Reload cart display
        await loadCart();
        
        // Update cart badge
        await updateCartBadge();
        
        return response.data;
        
    } catch (error) {
        console.error('Error removing from cart:', error);
        
        if (typeof showNotification === 'function') {
            showNotification(error.message || 'Failed to remove item', 'error');
        }
        
        throw error;
    }
}

// ============================================================================
// CLEAR CART
// ============================================================================

/**
 * Clears all items from the cart
 * 
 * @returns {Promise<object>} - API response
 * 
 * Example usage:
 *   await clearCart();
 */
async function clearCart() {
    try {
        // Confirm action
        const confirmed = confirm('Are you sure you want to clear your entire cart?');
        if (!confirmed) return;
        
        // Call API
        const response = await apiCall('/cart/clear.php', 'POST');
        
        // Show success notification
        if (typeof showNotification === 'function') {
            showNotification('Cart cleared successfully', 'success');
        }
        
        // Reload cart display
        await loadCart();
        
        // Update cart badge
        await updateCartBadge();
        
        return response.data;
        
    } catch (error) {
        console.error('Error clearing cart:', error);
        
        if (typeof showNotification === 'function') {
            showNotification(error.message || 'Failed to clear cart', 'error');
        }
        
        throw error;
    }
}

// ============================================================================
// CART COUNT BADGE
// ============================================================================

/**
 * Gets the current cart count
 * 
 * @returns {Promise<number>} - Number of items in cart
 * 
 * Example usage:
 *   const count = await getCartCount();
 */
async function getCartCount() {
    try {
        // Only check if user is logged in
        if (!isLoggedIn()) {
            return 0;
        }
        
        // Call API
        const response = await apiCall('/cart/count.php', 'GET');
        return response.data.count || 0;
        
    } catch (error) {
        console.error('Error getting cart count:', error);
        return 0;
    }
}

/**
 * Updates the cart count badge in the UI
 * 
 * This function looks for an element with id="cart-count" 
 * and updates its text content.
 * 
 * Example usage:
 *   await updateCartBadge();
 */
async function updateCartBadge() {
    try {
        const count = await getCartCount();
        
        // Find cart badge element
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
// CHECKOUT
// ============================================================================

/**
 * Proceeds to checkout page
 * 
 * Validates that cart is not empty before redirecting.
 * 
 * Example usage:
 *   proceedToCheckout();
 */
async function proceedToCheckout() {
    try {
        // Get cart count
        const count = await getCartCount();
        
        if (count === 0) {
            if (typeof showNotification === 'function') {
                showNotification('Your cart is empty', 'error');
            }
            return;
        }
        
        // Redirect to checkout page
        window.location.href = 'checkout.html';
        
    } catch (error) {
        console.error('Error proceeding to checkout:', error);
        
        if (typeof showNotification === 'function') {
            showNotification('Failed to proceed to checkout', 'error');
        }
    }
}

// ============================================================================
// AUTO-INITIALIZATION
// ============================================================================

/**
 * Initialize cart functionality when DOM is ready
 */
document.addEventListener('DOMContentLoaded', function() {
    // Update cart badge on all pages
    updateCartBadge();
    
    // If we're on the cart page, load cart contents
    if (document.getElementById('cart-container')) {
        loadCart();
    }
});

// ============================================================================
// USAGE NOTES
// ============================================================================

/**
 * HOW TO USE IN HTML:
 * 
 * 1. Include required scripts:
 *    <script src="js/api.js"></script>
 *    <script src="js/ui.js"></script>
 *    <script src="js/cart.js"></script>
 * 
 * 2. Add cart badge to header:
 *    <a href="cart.html">
 *        Cart <span id="cart-count" class="badge">0</span>
 *    </a>
 * 
 * 3. Add "Add to Cart" buttons:
 *    <button onclick="addToCart(1, 1)">Add to Cart</button>
 * 
 * 4. Create cart page with:
 *    <div id="cart-container"></div>
 * 
 * The cart will load automatically when the page loads.
 */
