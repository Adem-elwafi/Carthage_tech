<?php
declare(strict_types=1);
/**
 * Featured Products Endpoint
 * 
 * Returns products marked as featured, bestsellers, or new arrivals.
 * This endpoint is perfect for homepage displays, promotional sections,
 * and highlighting special products.
 * 
 * This endpoint demonstrates:
 * - Boolean field filtering (is_featured, is_bestseller, is_new)
 * - OR conditions in SQL (match ANY of the conditions)
 * - Limiting results for performance
 * - Ordering by date for freshness
 * 
 * BOOLEAN FIELDS EXPLANATION:
 * 
 * Boolean (true/false) fields are stored in MySQL as:
 * - TINYINT(1): 0 = false, 1 = true
 * - BOOLEAN: Actually stored as TINYINT(1)
 * 
 * In PHP:
 * - Database returns: "0" or "1" (strings)
 * - We convert to: false or true (booleans)
 * 
 * Common uses:
 * - is_active: Is this item visible?
 * - is_featured: Should this be highlighted?
 * - is_published: Is this ready for public viewing?
 * 
 */

// ============================================
// CORS AND HEADERS CONFIGURATION
// ============================================
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================
// CORS AND HEADERS CONFIGURATION
// ============================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
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
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed. Please use GET request.', [], 405);
}

// ============================================
// GET AND VALIDATE PARAMETERS
// ============================================

// Get limit parameter (default 12, max 50)
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 12;

if ($limit < 1) {
    $limit = 12;
} elseif ($limit > 50) {
    $limit = 50; // Limit to 50 for performance
}

// Get optional type filter
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : null;

// Validate type if provided
$validTypes = ['featured', 'bestseller', 'new'];
if ($type !== null && !in_array($type, $validTypes)) {
    Response::error(
        'Invalid type parameter.',
        ['type' => 'Type must be one of: featured, bestseller, new'],
        400
    );
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
// BUILD SQL QUERY
// ============================================

/**
 * OR CONDITION EXPLANATION:
 * 
 * The OR operator matches rows where ANY condition is true:
 * 
 * WHERE is_featured = 1 OR is_bestseller = 1 OR is_new = 1
 * 
 * This returns products where:
 * ✓ is_featured = 1, is_bestseller = 0, is_new = 0  (featured only)
 * ✓ is_featured = 0, is_bestseller = 1, is_new = 0  (bestseller only)
 * ✓ is_featured = 1, is_bestseller = 1, is_new = 1  (all flags)
 * ✗ is_featured = 0, is_bestseller = 0, is_new = 0  (no flags)
 * 
 * Compare to AND operator:
 * WHERE is_featured = 1 AND is_bestseller = 1 AND is_new = 1
 * 
 * This would only return products where ALL conditions are true.
 * That would be very restrictive!
 */

$sql = 'SELECT 
            products.id,
            products.name,
            products.description,
            products.price,
            products.category_id,
            products.image_url,
            products.stock_quantity,
            products.is_featured,
            products.is_bestseller,
            products.is_new,
            products.brand,
            products.rating,
            products.review_count,
            products.created_at,
            categories.name AS category_name,
            categories.slug AS category_slug
        FROM products
        LEFT JOIN categories ON products.category_id = categories.id
        WHERE ';

// Build WHERE clause based on type filter
if ($type === 'featured') {
    $sql .= 'products.is_featured = 1';
} elseif ($type === 'bestseller') {
    $sql .= 'products.is_bestseller = 1';
} elseif ($type === 'new') {
    $sql .= 'products.is_new = 1';
} else {
    // No specific type - get all featured products (any flag)
    $sql .= '(products.is_featured = 1 OR products.is_bestseller = 1 OR products.is_new = 1)';
}

/**
 * ORDERING STRATEGY:
 * 
 * We order by created_at DESC to show newest products first.
 * This ensures the featured section stays fresh and relevant.
 * 
 * Alternative ordering strategies:
 * - ORDER BY rating DESC: Show highest-rated first
 * - ORDER BY review_count DESC: Show most-reviewed first
 * - ORDER BY RAND(): Random order (slow on large tables!)
 * - ORDER BY is_featured DESC, is_bestseller DESC: Prioritize featured
 */

$sql .= ' ORDER BY products.created_at DESC
          LIMIT :limit';

// ============================================
// EXECUTE QUERY
// ============================================
try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // FORMAT PRODUCTS
    // ============================================
    
    /**
     * TYPE CASTING BOOLEAN FIELDS:
     * 
     * Database returns: "0" or "1" (string)
     * PHP receives: "0" or "1"
     * We convert: (bool) "1" → true, (bool) "0" → false
     * JSON output: true or false (not "1" or "0")
     * 
     * Why this matters:
     * - JavaScript can use: if (product.is_featured) { ... }
     * - Without casting: "0" would be truthy in JavaScript!
     * - Proper booleans make frontend code cleaner
     */
    
    $formattedProducts = [];
    
    foreach ($products as $product) {
        // Determine which flags are set for this product
        $flags = [];
        if ($product['is_featured']) $flags[] = 'featured';
        if ($product['is_bestseller']) $flags[] = 'bestseller';
        if ($product['is_new']) $flags[] = 'new';
        
        $formattedProducts[] = [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => number_format((float) $product['price'], 2, '.', ''),
            'category' => [
                'id' => (int) $product['category_id'],
                'name' => $product['category_name'],
                'slug' => $product['category_slug']
            ],
            'image_url' => $product['image_url'],
            'stock_quantity' => (int) $product['stock_quantity'],
            'in_stock' => (int) $product['stock_quantity'] > 0,
            'flags' => [
                'is_featured' => (bool) $product['is_featured'],
                'is_bestseller' => (bool) $product['is_bestseller'],
                'is_new' => (bool) $product['is_new'],
                'labels' => $flags // Array of active labels for display
            ],
            'brand' => $product['brand'],
            'rating' => $product['rating'] ? (float) $product['rating'] : null,
            'review_count' => (int) $product['review_count'],
            'created_at' => $product['created_at']
        ];
    }
    
    // ============================================
    // BUILD RESPONSE
    // ============================================
    
    // Create descriptive message based on filter
    $typeLabel = $type ? ucfirst($type) : 'Featured';
    $message = sprintf(
        'Found %d %s product(s)',
        count($formattedProducts),
        strtolower($typeLabel)
    );
    
    $responseData = [
        'products' => $formattedProducts,
        'count' => count($formattedProducts),
        'filter' => [
            'type' => $type,
            'limit' => $limit
        ]
    ];
    
    Response::success($message, $responseData);
    
} catch (PDOException $e) {
    Response::error(
        'Database error while fetching featured products.',
        ['error' => $e->getMessage()],
        500
    );
}

// End of featured.php
