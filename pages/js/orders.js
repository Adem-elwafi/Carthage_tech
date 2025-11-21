/**
 * Orders Module for Carthage Tech E-commerce
 * 
 * This file handles order-related functionality:
 * - Creating orders (checkout)
 * - Viewing order history
 * - Viewing order details
 * - Order status management
 * 
 * Dependencies: api.js, ui.js (must be loaded first)
 */

// ============================================================================
// CREATE ORDER (CHECKOUT)
// ============================================================================

/**
 * Creates an order from current cart
 * 
 * This is the main checkout function. It validates shipping data,
 * calls the API to create the order, and redirects to confirmation.
 * 
 * @param {object} shippingData - Shipping and payment information
 * @returns {Promise<object>} - Created order data
 * 
 * Example usage:
 *   await createOrder({
 *     shipping_address: '123 Main St',
 *     shipping_city: 'Tunis',
 *     shipping_postal_code: '1000',
 *     payment_method: 'cash_on_delivery'
 *   });
 */
async function createOrder(shippingData) {
    try {
        // Check if user is logged in
        if (!isLoggedIn()) {
            if (typeof protectPage === 'function') {
                protectPage();
            }
            return;
        }
        
        // Validate shipping data
        const validation = validateShippingData(shippingData);
        if (!validation.valid) {
            throw new Error(validation.errors.join(', '));
        }
        
        // Show loading state
        if (typeof showNotification === 'function') {
            showNotification('Creating your order...', 'info');
        }
        
        // Call API to create order
        const response = await apiCall('/orders/create.php', 'POST', shippingData);
        const order = response.data;
        
        // Show success message
        if (typeof showNotification === 'function') {
            showNotification(
                `Order ${order.order_number} created successfully!`, 
                'success'
            );
        }
        
        // Clear cart badge since cart is now empty
        if (typeof updateCartBadge === 'function') {
            await updateCartBadge();
        }
        
        // Redirect to order confirmation page after 2 seconds
        setTimeout(() => {
            window.location.href = `order-confirmation.html?order_id=${order.order_id}`;
        }, 2000);
        
        return order;
        
    } catch (error) {
        console.error('Error creating order:', error);
        
        // Handle specific error cases
        let errorMessage = error.message;
        
        if (error.message.includes('Cart is empty')) {
            errorMessage = 'Your cart is empty. Please add items before checkout.';
        } else if (error.message.includes('Insufficient stock')) {
            errorMessage = 'Some items in your cart are out of stock. Please update your cart.';
        } else if (error.message.includes('Authentication required')) {
            errorMessage = 'Please log in to complete your order.';
            setTimeout(() => {
                window.location.href = 'login.html?redirect=checkout.html';
            }, 2000);
        }
        
        if (typeof showNotification === 'function') {
            showNotification(errorMessage, 'error');
        }
        
        throw error;
    }
}

/**
 * Validates shipping data before order creation
 * 
 * @param {object} data - Shipping data object
 * @returns {object} - { valid: boolean, errors: array }
 */
function validateShippingData(data) {
    const errors = [];
    
    // Validate shipping address
    if (!data.shipping_address || data.shipping_address.trim().length < 10) {
        errors.push('Shipping address must be at least 10 characters');
    }
    
    // Validate city
    if (!data.shipping_city || data.shipping_city.trim().length < 2) {
        errors.push('Please enter a valid city');
    }
    
    // Validate postal code (4 digits for Tunisia)
    const postalCodeRegex = /^\d{4}$/;
    if (!data.shipping_postal_code || !postalCodeRegex.test(data.shipping_postal_code)) {
        errors.push('Postal code must be 4 digits');
    }
    
    // Validate payment method
    const validPaymentMethods = ['cash_on_delivery', 'bank_transfer', 'card'];
    if (!data.payment_method || !validPaymentMethods.includes(data.payment_method)) {
        errors.push('Please select a valid payment method');
    }
    
    return {
        valid: errors.length === 0,
        errors: errors
    };
}

/**
 * Handles checkout form submission
 * 
 * @param {Event} event - Form submit event
 */
async function handleCheckout(event) {
    // Prevent default form submission
    event.preventDefault();
    
    // Get form element
    const form = event.target;
    
    // Collect form data
    const formDataObj = new FormData(form);
    const shippingData = {
        shipping_address: formDataObj.get('shipping_address'),
        shipping_city: formDataObj.get('shipping_city'),
        shipping_postal_code: formDataObj.get('shipping_postal_code'),
        payment_method: formDataObj.get('payment_method') || 'cash_on_delivery'
    };
    
    // Disable submit button
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Processing...';
    
    try {
        // Create order
        await createOrder(shippingData);
        
    } catch (error) {
        // Re-enable button on error
        submitBtn.disabled = false;
        submitBtn.textContent = originalBtnText;
    }
}

// ============================================================================
// ORDER HISTORY
// ============================================================================

/**
 * Loads user's order history
 * 
 * @param {number} page - Page number (default: 1)
 * @param {number} limit - Orders per page (default: 10)
 * @param {string} containerId - Container element ID
 * @returns {Promise<object>} - Orders data with pagination
 * 
 * Example usage:
 *   await loadOrderHistory(1, 10, 'orders-container');
 */
async function loadOrderHistory(page = 1, limit = 10, containerId = 'orders-container') {
    try {
        // Check if user is logged in
        if (!isLoggedIn()) {
            if (typeof protectPage === 'function') {
                protectPage();
            }
            return;
        }
        
        showLoading(containerId);
        
        // Build endpoint
        const endpoint = `/orders/list.php?page=${page}&limit=${limit}`;
        
        // Call API
        const response = await apiCall(endpoint, 'GET');
        const { orders, pagination } = response.data;
        
        // Render orders
        renderOrderHistory(orders, pagination, containerId);
        
        return response.data;
        
    } catch (error) {
        console.error('Error loading order history:', error);
        
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `
                <div class="error-message">
                    <p>Failed to load order history. Please try again.</p>
                    <p>${error.message}</p>
                </div>
            `;
        }
        
        throw error;
    }
}

/**
 * Renders order history table
 * 
 * @param {array} orders - Array of order objects
 * @param {object} pagination - Pagination info
 * @param {string} containerId - Container element ID
 */
function renderOrderHistory(orders, pagination, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    // If no orders
    if (!orders || orders.length === 0) {
        container.innerHTML = `
            <div class="no-orders">
                <h2>No orders yet</h2>
                <p>You haven't placed any orders yet.</p>
                <a href="index.html" class="btn btn-primary">Start Shopping</a>
            </div>
        `;
        return;
    }
    
    // Generate HTML
    const html = `
        <div class="orders-content">
            <h2>My Orders</h2>
            
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${orders.map(order => `
                        <tr class="order-row" data-order-id="${order.id}">
                            <td class="order-number">
                                <strong>${order.order_number}</strong>
                            </td>
                            
                            <td class="order-date">
                                ${formatDate(order.created_at)}
                            </td>
                            
                            <td class="order-items">
                                ${order.item_count} item(s)
                                <br>
                                <small>Qty: ${order.total_quantity}</small>
                            </td>
                            
                            <td class="order-total">
                                <strong>${formatPrice(order.total_price)}</strong>
                            </td>
                            
                            <td class="order-status">
                                <span class="status-badge status-${order.status}">
                                    ${order.status_label}
                                </span>
                            </td>
                            
                            <td class="order-payment">
                                ${formatPaymentMethod(order.payment_method)}
                                <br>
                                <small class="payment-status">${order.payment_status}</small>
                            </td>
                            
                            <td class="order-actions">
                                <a href="order-detail.html?id=${order.id}" 
                                   class="btn btn-sm btn-primary">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            
            ${renderOrdersPagination(pagination)}
        </div>
    `;
    
    container.innerHTML = html;
}

/**
 * Renders pagination for orders
 * 
 * @param {object} pagination - Pagination info
 * @returns {string} - HTML string
 */
function renderOrdersPagination(pagination) {
    if (!pagination || pagination.total_pages <= 1) return '';
    
    let html = '<div class="pagination-controls">';
    
    // Previous button
    if (pagination.current_page > 1) {
        html += `
            <button class="btn-pagination" 
                    onclick="loadOrderHistory(${pagination.current_page - 1})">
                Previous
            </button>
        `;
    }
    
    // Page info
    html += `
        <span class="page-info">
            Page ${pagination.current_page} of ${pagination.total_pages}
            (${pagination.total_orders} orders)
        </span>
    `;
    
    // Next button
    if (pagination.has_more) {
        html += `
            <button class="btn-pagination" 
                    onclick="loadOrderHistory(${pagination.current_page + 1})">
                Next
            </button>
        `;
    }
    
    html += '</div>';
    return html;
}

// ============================================================================
// ORDER DETAIL
// ============================================================================

/**
 * Loads detailed information for a single order
 * 
 * @param {number} orderId - Order ID
 * @param {string} containerId - Container element ID
 * @returns {Promise<object>} - Order detail data
 * 
 * Example usage:
 *   await loadOrderDetail(15, 'order-detail-container');
 */
async function loadOrderDetail(orderId, containerId = 'order-detail') {
    try {
        // Check if user is logged in
        if (!isLoggedIn()) {
            if (typeof protectPage === 'function') {
                protectPage();
            }
            return;
        }
        
        showLoading(containerId);
        
        // Call API
        const endpoint = `/orders/detail.php?id=${orderId}`;
        const response = await apiCall(endpoint, 'GET');
        const order = response.data.order;
        
        // Render order detail
        renderOrderDetail(order, containerId);
        
        return order;
        
    } catch (error) {
        console.error('Error loading order detail:', error);
        
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `
                <div class="error-message">
                    <p>Failed to load order details. Please try again.</p>
                    <p>${error.message}</p>
                </div>
            `;
        }
        
        throw error;
    }
}

/**
 * Renders full order detail page
 * 
 * @param {object} order - Order data
 * @param {string} containerId - Container element ID
 */
function renderOrderDetail(order, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const html = `
        <div class="order-detail-content">
            <div class="order-header">
                <h1>Order ${order.order_number}</h1>
                <p class="order-date">
                    Placed on ${order.dates.created_at_formatted}
                </p>
            </div>
            
            <div class="order-status-section">
                <h3>Order Status</h3>
                <span class="status-badge status-${order.status.order_status}">
                    ${order.status.order_status_label}
                </span>
                
                <p class="payment-info">
                    Payment: ${formatPaymentMethod(order.status.payment_method)}
                    <span class="payment-status">(${order.status.payment_status})</span>
                </p>
            </div>
            
            <div class="order-shipping-section">
                <h3>Shipping Address</h3>
                <p>${order.shipping.full_address}</p>
            </div>
            
            <div class="order-items-section">
                <h3>Order Items</h3>
                
                <table class="order-items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${order.items.map(item => `
                            <tr>
                                <td class="product-info">
                                    <img src="${item.product.image_url}" 
                                         alt="${item.product.name}"
                                         onerror="this.src='images/placeholder.jpg'">
                                    <div>
                                        <h4>${item.product.name}</h4>
                                        ${item.product.brand ? `<p class="brand">${item.product.brand}</p>` : ''}
                                    </div>
                                </td>
                                
                                <td class="price">
                                    ${formatPrice(item.price_at_purchase)}
                                </td>
                                
                                <td class="quantity">
                                    ${item.quantity}
                                </td>
                                
                                <td class="subtotal">
                                    <strong>${formatPrice(item.subtotal)}</strong>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            
            <div class="order-summary-section">
                <h3>Order Summary</h3>
                
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span>${formatPrice(order.pricing.subtotal)}</span>
                </div>
                
                <div class="summary-row">
                    <span>Tax (${order.pricing.tax_rate}):</span>
                    <span>${formatPrice(order.pricing.tax_amount)}</span>
                </div>
                
                <div class="summary-row total">
                    <strong>Total:</strong>
                    <strong>${formatPrice(order.pricing.total_price)}</strong>
                </div>
            </div>
            
            <div class="order-actions">
                <a href="orders.html" class="btn btn-secondary">Back to Orders</a>
                <button onclick="window.print()" class="btn btn-secondary">Print Order</button>
            </div>
        </div>
    `;
    
    container.innerHTML = html;
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Formats payment method for display
 * 
 * @param {string} method - Payment method code
 * @returns {string} - Formatted payment method
 */
function formatPaymentMethod(method) {
    const methods = {
        'cash_on_delivery': 'Cash on Delivery',
        'bank_transfer': 'Bank Transfer',
        'card': 'Credit/Debit Card'
    };
    
    return methods[method] || method;
}

/**
 * Gets order status color class
 * 
 * @param {string} status - Order status
 * @returns {string} - CSS class name
 */
function getStatusClass(status) {
    const classes = {
        'pending': 'status-pending',
        'confirmed': 'status-confirmed',
        'processing': 'status-processing',
        'shipped': 'status-shipped',
        'delivered': 'status-delivered',
        'cancelled': 'status-cancelled'
    };
    
    return classes[status] || 'status-default';
}

// ============================================================================
// AUTO-INITIALIZATION
// ============================================================================

/**
 * Initialize orders functionality when DOM is ready
 */
document.addEventListener('DOMContentLoaded', function() {
    // If we're on checkout page, attach form handler
    const checkoutForm = document.getElementById('checkout-form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', handleCheckout);
    }
    
    // If we're on orders history page, load orders
    if (document.getElementById('orders-container')) {
        loadOrderHistory();
    }
    
    // If we're on order detail page, load order
    const urlParams = new URLSearchParams(window.location.search);
    const orderId = urlParams.get('id') || urlParams.get('order_id');
    
    if (orderId && document.getElementById('order-detail')) {
        loadOrderDetail(orderId);
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
 *    <script src="js/orders.js"></script>
 * 
 * 2. Checkout form (checkout.html):
 *    <form id="checkout-form">
 *        <input type="text" name="shipping_address" required>
 *        <input type="text" name="shipping_city" required>
 *        <input type="text" name="shipping_postal_code" required>
 *        <select name="payment_method" required>
 *            <option value="cash_on_delivery">Cash on Delivery</option>
 *            <option value="bank_transfer">Bank Transfer</option>
 *            <option value="card">Credit/Debit Card</option>
 *        </select>
 *        <button type="submit">Place Order</button>
 *    </form>
 * 
 * 3. Orders history page (orders.html):
 *    <div id="orders-container"></div>
 * 
 * 4. Order detail page (order-detail.html):
 *    <div id="order-detail"></div>
 * 
 * The scripts will automatically load data when the page loads.
 */
