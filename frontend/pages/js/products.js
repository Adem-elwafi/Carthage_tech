/**
 * Products Module for Carthage Tech E-commerce
 * 
 * This file handles all product-related functionality:
 * - Loading product lists with pagination
 * - Loading featured/bestseller/new products
 * - Product search
 * - Product detail display
 * - Category filtering
 * 
 * Dependencies: api.js (must be loaded first)
 */

// ============================================================================
// PRODUCT LISTING
// ============================================================================

/**
 * Loads products with pagination and optional category filter
 * 
 * @param {number} page - Page number (default: 1)
 * @param {number} limit - Products per page (default: 20)
 * @param {number|null} categoryId - Category ID to filter by (optional)
 * @param {string} containerId - ID of container element (default: 'products-container')
 * @returns {Promise<object>} - Product data and pagination info
 * 
 * Example usage:
 *   loadProducts(1, 20, null, 'products-container');
 */
async function loadProducts(page = 1, limit = 20, categoryId = null, containerId = 'products-container') {
    try {
        // Show loading indicator
        showLoading(containerId);
        
        // Build API endpoint with query parameters
        let endpoint = `/products/list.php?page=${page}&limit=${limit}`;
        if (categoryId) {
            endpoint += `&category_id=${categoryId}`;
        }
        
        // Call API
        const response = await apiCall(endpoint, 'GET');
        
        // Extract data
        const { products, pagination } = response.data;
        
        // Render products
        renderProducts(products, containerId);
        
        // Render pagination controls
        if (pagination) {
            renderPagination(pagination, categoryId, containerId);
        }
        
        return response.data;
        
    } catch (error) {
        console.error('Error loading products:', error);
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `
                <div class="error-message">
                    <p>Failed to load products. Please try again.</p>
                    <p>${error.message}</p>
                </div>
            `;
        }
        throw error;
    }
}

/**
 * Renders products as HTML cards
 * 
 * This function takes an array of products and generates HTML
 * to display them in a grid layout.
 * 
 * @param {array} products - Array of product objects
 * @param {string} containerId - ID of container element
 */
function renderProducts(products, containerId = 'products-container') {
    const container = document.getElementById(containerId);
    if (!container) {
        console.error('Container not found:', containerId);
        return;
    }
    
    // If no products, show message
    if (!products || products.length === 0) {
        container.innerHTML = '<p class="no-products">No products found.</p>';
        return;
    }
    
    // Generate HTML for each product
    const html = products.map(product => `
        <article class="product-card" data-product-id="${product.id}" aria-label="Produit ${product.name}">
            <div class="media" aria-hidden="true">
                ${product.image_url ? `
                    <img src="${product.image_url}" alt="${product.name}" 
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="placeholder-icon" style="display:none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    </div>
                ` : `
                    <div class="placeholder-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    </div>
                `}
                
                ${product.is_featured ? '<span class="badge badge-featured">★ Vedette</span>' : ''}
                ${product.is_bestseller ? '<span class="badge badge-bestseller">🔥 Best Seller</span>' : ''}
                ${product.is_new ? '<span class="badge badge-new">✨ Nouveau</span>' : ''}
            </div>
            
            <div class="content">
                <h3 class="name">${product.name}</h3>
                
                ${product.brand ? `<p class="brand" style="color: #6b7280; font-size: 0.9em; margin: 5px 0;">${product.brand}</p>` : ''}
                
                <div class="meta">
                    <span class="price">${formatPrice(product.price)}</span>
                </div>
                
                ${product.in_stock ? `
                    <button class="btn btn-primary add-to-cart-btn" 
                            onclick="addToCartFromList(${product.id}, 1)"
                            data-product-id="${product.id}"
                            aria-label="Ajouter ${product.name} au panier">
                        🛒 Ajouter au panier
                    </button>
                ` : `
                    <span class="out-of-stock" style="color: #dc3545; font-weight: 600;">Rupture de stock</span>
                `}
            </div>
        </article>
    `).join('');
    
    container.innerHTML = html;
}

/**
 * Renders pagination controls
 * 
 * @param {object} pagination - Pagination info from API
 * @param {number|null} categoryId - Category filter (if any)
 * @param {string} containerId - Products container ID
 */
function renderPagination(pagination, categoryId = null, containerId = 'products-container') {
    // Look for pagination container (create if doesn't exist)
    let paginationContainer = document.getElementById('pagination-container');
    if (!paginationContainer) {
        paginationContainer = document.createElement('div');
        paginationContainer.id = 'pagination-container';
        paginationContainer.className = 'pagination';
        
        const productsContainer = document.getElementById(containerId);
        if (productsContainer && productsContainer.parentNode) {
            productsContainer.parentNode.insertBefore(
                paginationContainer, 
                productsContainer.nextSibling
            );
        }
    }
    
    let html = '<div class="pagination-controls">';
    
    // Previous button
    if (pagination.has_prev) {
        html += `
            <button class="btn-pagination" 
                    onclick="loadProducts(${pagination.current_page - 1}, ${pagination.per_page}, ${categoryId}, '${containerId}')">
                Previous
            </button>
        `;
    }
    
    // Page info
    html += `
        <span class="page-info">
            Page ${pagination.current_page} of ${pagination.total_pages} 
            (${pagination.total} products)
        </span>
    `;
    
    // Next button
    if (pagination.has_next) {
        html += `
            <button class="btn-pagination" 
                    onclick="loadProducts(${pagination.current_page + 1}, ${pagination.per_page}, ${categoryId}, '${containerId}')">
                Next
            </button>
        `;
    }
    
    html += '</div>';
    paginationContainer.innerHTML = html;
}

// ============================================================================
// FEATURED PRODUCTS
// ============================================================================

/**
 * Loads featured, bestseller, or new products
 * 
 * @param {string} type - 'featured', 'bestseller', 'new', or null for all
 * @param {number} limit - Max products to load (default: 12)
 * @param {string} containerId - Container element ID
 * @returns {Promise<array>} - Array of products
 * 
 * Example usage:
 *   loadFeaturedProducts('featured', 8, 'featured-container');
 */
async function loadFeaturedProducts(type = null, limit = 12, containerId = 'featured-products') {
    try {
        showLoading(containerId);
        
        // Try featured.php endpoint first
        let endpoint = `/products/featured.php?limit=${limit}`;
        if (type) {
            endpoint += `&type=${type}`;
        }
        
        try {
            // Call API
            const response = await apiCall(endpoint, 'GET');
            const products = response.data.products || response.data || [];
            
            // Render products
            renderProducts(products, containerId);
            
            return products;
        } catch (featuredError) {
            console.warn('Featured endpoint not available, trying alternative...', featuredError);
            
            // Fallback: Use list.php with filters
            let fallbackEndpoint = `/products/list.php?limit=${limit}&page=1`;
            
            // Map type to filter parameter
            if (type === 'new') {
                fallbackEndpoint += '&is_new=1';
            } else if (type === 'bestseller') {
                fallbackEndpoint += '&is_bestseller=1';
            } else if (type === 'featured') {
                fallbackEndpoint += '&is_featured=1';
            }
            
            const fallbackResponse = await apiCall(fallbackEndpoint, 'GET');
            
            // Handle different response structures
            let products = [];
            if (fallbackResponse.data.products) {
                products = fallbackResponse.data.products;
            } else if (Array.isArray(fallbackResponse.data)) {
                products = fallbackResponse.data;
            } else if (fallbackResponse.products) {
                products = fallbackResponse.products;
            }
            
            // Render products
            renderProducts(products, containerId);
            
            return products;
        }
        
    } catch (error) {
        console.error('Error loading featured products:', error);
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #dc3545;">
                    <p class="error-message">⚠️ Impossible de charger les produits.</p>
                    <p style="font-size: 0.9em; color: #6b7280;">Vérifiez que le backend est démarré et que des produits avec le flag "${type || 'featured'}" existent dans la base de données.</p>
                    <p style="font-size: 0.85em; color: #9ca3af; margin-top: 10px;">Erreur: ${error.message}</p>
                </div>
            `;
        }
        throw error;
    }
}

// ============================================================================
// PRODUCT SEARCH
// ============================================================================

/**
 * Searches for products by query
 * 
 * @param {string} query - Search term
 * @param {number} limit - Max results (default: 50)
 * @param {string} containerId - Container element ID
 * @returns {Promise<array>} - Array of matching products
 * 
 * Example usage:
 *   searchProducts('laptop', 20, 'search-results');
 */
async function searchProducts(query, limit = 50, containerId = 'search-results') {
    try {
        // Validate query
        if (!query || query.trim().length < 2) {
            throw new Error('Search query must be at least 2 characters');
        }
        
        showLoading(containerId);
        
        // Encode query for URL
        const encodedQuery = encodeURIComponent(query.trim());
        const endpoint = `/products/search.php?q=${encodedQuery}&limit=${limit}`;
        
        // Call API
        const response = await apiCall(endpoint, 'GET');
        const products = response.data.products;
        
        // Show search info
        const container = document.getElementById(containerId);
        if (container) {
            const infoHtml = `
                <div class="search-info">
                    <p>Found ${response.data.count} result(s) for "${query}"</p>
                </div>
            `;
            container.innerHTML = infoHtml;
        }
        
        // Render products
        renderProducts(products, containerId);
        
        return products;
        
    } catch (error) {
        console.error('Error searching products:', error);
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `<p class="error-message">${error.message}</p>`;
        }
        throw error;
    }
}

/**
 * Sets up search form with debouncing
 * 
 * Debouncing delays API call until user stops typing.
 * This prevents too many API calls while typing.
 * 
 * @param {string} formId - Search form ID
 * @param {string} inputId - Search input ID
 * @param {string} resultsId - Results container ID
 */
function setupSearchForm(formId = 'search-form', inputId = 'search-input', resultsId = 'search-results') {
    const form = document.getElementById(formId);
    const input = document.getElementById(inputId);
    
    if (!form || !input) return;
    
    let debounceTimer;
    
    // Handle form submit
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const query = input.value;
        if (query.trim().length >= 2) {
            searchProducts(query, 50, resultsId);
        }
    });
    
    // Handle input with debounce (300ms delay)
    input.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        const query = e.target.value;
        
        if (query.trim().length >= 2) {
            debounceTimer = setTimeout(() => {
                searchProducts(query, 50, resultsId);
            }, 300); // Wait 300ms after user stops typing
        }
    });
}

// ============================================================================
// CATEGORY PRODUCTS
// ============================================================================

/**
 * Loads products by category slug
 * 
 * @param {string} slug - Category slug (e.g., 'ordinateurs')
 * @param {number} page - Page number
 * @param {number} limit - Products per page
 * @param {string} containerId - Container element ID
 * @returns {Promise<object>} - Category data and products
 * 
 * Example usage:
 *   loadProductsByCategory('ordinateurs', 1, 20, 'category-products');
 */
async function loadProductsByCategory(slug, page = 1, limit = 20, containerId = 'category-products') {
    try {
        showLoading(containerId);
        
        const endpoint = `/products/category.php?slug=${slug}&page=${page}&limit=${limit}`;
        
        // Call API
        const response = await apiCall(endpoint, 'GET');
        const { category, products, pagination } = response.data;
        
        // Show category info
        const container = document.getElementById(containerId);
        if (container && category) {
            const infoHtml = `
                <div class="category-info">
                    <h2>${category.name}</h2>
                    ${category.description ? `<p>${category.description}</p>` : ''}
                </div>
            `;
            container.innerHTML = infoHtml;
        }
        
        // Render products
        renderProducts(products, containerId);
        
        // Render pagination
        if (pagination) {
            renderPagination(pagination, null, containerId);
        }
        
        return response.data;
        
    } catch (error) {
        console.error('Error loading category products:', error);
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `<p class="error-message">${error.message}</p>`;
        }
        throw error;
    }
}

// ============================================================================
// PRODUCT DETAIL
// ============================================================================

/**
 * Loads and displays a single product's details
 * 
 * @param {number} productId - Product ID
 * @param {string} containerId - Container element ID
 * @returns {Promise<object>} - Product detail data
 * 
 * Example usage:
 *   loadProductDetail(1, 'product-detail-container');
 */
async function loadProductDetail(productId, containerId = 'product-detail') {
    try {
        showLoading(containerId);
        
        const endpoint = `/products/detail.php?id=${productId}`;
        
        // Call API
        const response = await apiCall(endpoint, 'GET');
        const product = response.data.product;
        
        // Render product detail
        renderProductDetail(product, containerId);
        
        return product;
        
    } catch (error) {
        console.error('Error loading product detail:', error);
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `<p class="error-message">${error.message}</p>`;
        }
        throw error;
    }
}

/**
 * Renders full product detail HTML
 * 
 * @param {object} product - Product data
 * @param {string} containerId - Container element ID
 */
function renderProductDetail(product, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const html = `
        <div class="product-detail-content">
            <div class="product-images">
                <div class="main-image">
                    <img src="${product.main_image}" alt="${product.name}" 
                         id="main-product-image"
                         onerror="this.src='images/placeholder.jpg'">
                </div>
                
                ${product.images && product.images.length > 1 ? `
                    <div class="image-thumbnails">
                        ${product.images.map((img, idx) => `
                            <img src="${img.url}" alt="${product.name} ${idx + 1}"
                                 onclick="document.getElementById('main-product-image').src='${img.url}'"
                                 class="thumbnail ${img.is_primary ? 'active' : ''}">
                        `).join('')}
                    </div>
                ` : ''}
            </div>
            
            <div class="product-details">
                <h1>${product.name}</h1>
                
                ${product.brand ? `<p class="brand">Brand: <strong>${product.brand}</strong></p>` : ''}
                
                ${product.category ? `<p class="category">Category: ${product.category.name}</p>` : ''}
                
                <div class="product-price-section">
                    <p class="price">${formatPrice(product.price)}</p>
                </div>
                
                <div class="product-stock">
                    ${product.stock.in_stock ? `
                        <span class="in-stock">✓ In Stock (${product.stock.quantity} available)</span>
                    ` : `
                        <span class="out-of-stock">✗ Out of Stock</span>
                    `}
                </div>
                
                ${product.rating ? `
                    <div class="product-rating">
                        <span class="stars">${'★'.repeat(Math.floor(product.rating.average))}${'☆'.repeat(5 - Math.floor(product.rating.average))}</span>
                        <span class="rating-text">${product.rating.average}/5 (${product.rating.count} reviews)</span>
                    </div>
                ` : ''}
                
                <div class="product-description">
                    <h3>Description</h3>
                    <p>${product.description}</p>
                </div>
                
                ${product.stock.in_stock ? `
                    <div class="add-to-cart-section">
                        <label for="quantity">Quantity:</label>
                        <input type="number" id="quantity" value="1" min="1" max="${product.stock.quantity}">
                        
                        <button class="btn btn-primary" 
                                onclick="addToCartFromDetail(${product.id})">
                            Add to Cart
                        </button>
                    </div>
                ` : ''}
            </div>
        </div>
    `;
    
    container.innerHTML = html;
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Truncates text to specified length
 * 
 * @param {string} text - Text to truncate
 * @param {number} maxLength - Maximum length
 * @returns {string} - Truncated text with ellipsis
 */
function truncateText(text, maxLength = 100) {
    if (!text) return '';
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

/**
 * Adds product to cart from product list
 * Called by "Add to Cart" button in product cards
 * 
 * @param {number} productId - Product ID
 * @param {number} quantity - Quantity to add
 */
async function addToCartFromList(productId, quantity) {
    // This function is defined in cart.js
    // It's called from the product card buttons
    if (typeof addToCart === 'function') {
        await addToCart(productId, quantity);
    } else {
        console.error('addToCart function not found. Make sure cart.js is loaded.');
    }
}

/**
 * Adds product to cart from product detail page
 * Gets quantity from input field
 * 
 * @param {number} productId - Product ID
 */
async function addToCartFromDetail(productId) {
    const quantityInput = document.getElementById('quantity');
    const quantity = quantityInput ? parseInt(quantityInput.value) : 1;
    
    if (typeof addToCart === 'function') {
        await addToCart(productId, quantity);
    } else {
        console.error('addToCart function not found. Make sure cart.js is loaded.');
    }
}

// ============================================================================
// AUTO-INITIALIZATION
// ============================================================================

/**
 * Auto-setup when DOM is ready
 */
document.addEventListener('DOMContentLoaded', function() {
    // Setup search form if it exists
    setupSearchForm();
    
    // Check if we're on product detail page
    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('id');
    
    if (productId && document.getElementById('product-detail')) {
        loadProductDetail(productId);
    }
    
    // Check if we're on category page
    const categorySlug = urlParams.get('category');
    if (categorySlug && document.getElementById('category-products')) {
        loadProductsByCategory(categorySlug);
    }
});
