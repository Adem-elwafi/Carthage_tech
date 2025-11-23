/**
 * Product Page Loader Helper
 * 
 * This file contains simple functions to load products on different category pages.
 * Include this file AFTER api.js, ui.js, and products.js
 */

/**
 * Load products for a specific page based on URL
 * Automatically detects which page you're on and loads appropriate products
 */
function autoLoadProducts() {
    // Get current page filename
    const currentPage = window.location.pathname.split('/').pop();
    
    // Map pages to category slugs or product types
    const pageMap = {
        'ordinateurs.html': { type: 'category', slug: 'ordinateurs', container: 'products-container' },
        'accessoires.html': { type: 'category', slug: 'accessoires', container: 'products-container' },
        'reseaux.html': { type: 'category', slug: 'reseaux', container: 'products-container' },
        'nouveautes.html': { type: 'featured', filter: 'new', container: 'products-container' },
        'meilleures-ventes.html': { type: 'featured', filter: 'bestseller', container: 'products-container' }
    };
    
    const pageConfig = pageMap[currentPage];
    
    if (!pageConfig) {
        console.log('No product configuration for this page');
        return;
    }
    
    // Check if container exists, create if not
    let container = document.getElementById(pageConfig.container);
    if (!container) {
        // Try to find a products-grid div
        container = document.querySelector('.products-grid');
        if (container) {
            container.id = pageConfig.container;
        } else {
            console.error('Products container not found');
            return;
        }
    }
    
    // Load products based on page type
    if (pageConfig.type === 'category') {
        console.log('Loading category:', pageConfig.slug);
        loadProductsByCategory(pageConfig.slug, 1, 20, pageConfig.container);
    } else if (pageConfig.type === 'featured') {
        console.log('Loading featured:', pageConfig.filter);
        loadFeaturedProducts(pageConfig.filter, 20, pageConfig.container);
    }
}

// Auto-run when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    autoLoadProducts();
});
