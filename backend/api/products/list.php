<?php
declare(strict_types=1);
/**
 * Products List Endpoint
 * 
 * Returns a paginated list of all products with optional category filtering.
 * This endpoint demonstrates key concepts:
 * - Pagination: Splitting large datasets into smaller pages
 * - SQL JOINs: Connecting products with their category information
 * - Query parameters: Accepting user input to customize results
 * 
 * URL Parameters (all optional):
 * - page: Which page to display (default: 1)
 * - limit: How many items per page (default: 20)
 * - category_id: Filter by specific category (optional)
 * 
 * Example URLs:
 * - /products/list.php (first 20 products)
 * - /products/list.php?page=2 (second page)
 * - /products/list.php?limit=50 (first 50 products)
 * - /products/list.php?category_id=1 (only products in category 1)
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
// This endpoint only accepts GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed. Please use GET request.', [], 405);
}

// ============================================
// GET AND VALIDATE QUERY PARAMETERS
// ============================================

/**
 * PAGINATION EXPLANATION:
 * 
 * Pagination divides large result sets into smaller "pages" to improve:
 * 1. Performance: Loading fewer records is faster
 * 2. User Experience: Easier to browse through products
 * 3. Bandwidth: Less data transferred per request
 * 
 * Example: If you have 100 products and limit=20:
 * - Page 1: Products 1-20 (offset 0)
 * - Page 2: Products 21-40 (offset 20)
 * - Page 3: Products 41-60 (offset 40)
 * 
 * Offset formula: offset = (page - 1) * limit
 */

// Get page number from URL (e.g., ?page=2), default to page 1
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Get items per page from URL (e.g., ?limit=50), default to 20
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

// Get optional category filter (e.g., ?category_id=3)
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;

// Validate page number (must be at least 1)
if ($page < 1) {
    $page = 1;
}

// Validate limit (must be between 1 and 100)
if ($limit < 1) {
    $limit = 20;
} elseif ($limit > 100) {
    $limit = 100; // Maximum 100 items per page for performance
}

// Calculate offset for SQL query
// Offset = how many records to skip before starting to return results
$offset = ($page - 1) * $limit;

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
 * SQL JOIN EXPLANATION:
 * 
 * JOIN combines rows from two or more tables based on a related column.
 * 
 * In our case:
 * - products table has: id, name, price, category_id (foreign key)
 * - categories table has: id, name, slug
 * 
 * LEFT JOIN means: Get all products, and IF they have a matching category,
 * include the category info. If no match, category fields will be NULL.
 * 
 * Syntax: LEFT JOIN categories ON products.category_id = categories.id
 *                                    ^                      ^
 *                              Foreign key          Primary key
 */

// Base SQL query with JOIN to get category information
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
        WHERE 1=1'; // WHERE 1=1 is a trick to easily add more conditions

// Array to hold parameters for prepared statement
$params = [];

// Add category filter if provided
if ($categoryId !== null) {
    $sql .= ' AND products.category_id = :category_id';
    $params['category_id'] = $categoryId;
}

// Add ordering (newest products first)
$sql .= ' ORDER BY products.created_at DESC';

// Add pagination using LIMIT and OFFSET
// LIMIT: Maximum number of rows to return
// OFFSET: Number of rows to skip before starting to return rows
$sql .= ' LIMIT :limit OFFSET :offset';

// ============================================
// GET TOTAL COUNT (for pagination info)
// ============================================
// We need to know the total number of products to calculate total pages

$countSql = 'SELECT COUNT(*) as total FROM products WHERE 1=1';

if ($categoryId !== null) {
    $countSql .= ' AND category_id = :category_id';
}

try {
    $countStmt = $pdo->prepare($countSql);
    
    // Bind category parameter if filtering
    if ($categoryId !== null) {
        $countStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    }
    
    $countStmt->execute();
    $totalProducts = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calculate total pages
    // Example: 95 products ÷ 20 per page = 4.75 → ceil() = 5 pages
    $totalPages = (int) ceil($totalProducts / $limit);
    
} catch (PDOException $e) {
    Response::error('Database error while counting products.', ['error' => $e->getMessage()], 500);
}

// ============================================
// EXECUTE MAIN QUERY TO GET PRODUCTS
// ============================================
try {
    $stmt = $pdo->prepare($sql);
    
    // Bind pagination parameters
    // PDO::PARAM_INT tells PDO this is an integer (important for LIMIT/OFFSET)
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    // Bind category filter if provided
    if ($categoryId !== null) {
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    }
    
    // Execute the query
    $stmt->execute();
    
    // Fetch all matching products
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // FORMAT PRODUCT DATA
    // ============================================
    // Clean up and format the data before sending to client
    
    $formattedProducts = [];
    
    foreach ($products as $product) {
        $formattedProducts[] = [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => number_format((float) $product['price'], 2, '.', ''), // Format to 2 decimals
            'category' => [
                'id' => (int) $product['category_id'],
                'name' => $product['category_name'],
                'slug' => $product['category_slug']
            ],
            'image_url' => $product['image_url'],
            'stock_quantity' => (int) $product['stock_quantity'],
            'in_stock' => (int) $product['stock_quantity'] > 0, // Boolean: is product available?
            'is_featured' => (bool) $product['is_featured'],
            'is_bestseller' => (bool) $product['is_bestseller'],
            'is_new' => (bool) $product['is_new'],
            'brand' => $product['brand'],
            'rating' => $product['rating'] ? (float) $product['rating'] : null,
            'review_count' => (int) $product['review_count'],
            'created_at' => $product['created_at']
        ];
    }
    
    // ============================================
    // BUILD RESPONSE WITH PAGINATION INFO
    // ============================================
    
    /**
     * PAGINATION RESPONSE STRUCTURE:
     * 
     * We return not just the products, but also metadata about the pagination:
     * - total: Total number of products matching the filter
     * - page: Current page number
     * - limit: Items per page
     * - total_pages: How many pages exist
     * - has_next: Can user go to next page?
     * - has_prev: Can user go to previous page?
     * 
     * This helps the frontend build pagination controls (previous/next buttons)
     */
    
    $responseData = [
        'products' => $formattedProducts,
        'pagination' => [
            'total' => $totalProducts,
            'count' => count($formattedProducts), // Number of products in this response
            'per_page' => $limit,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
    
    // Add filter info if category was specified
    if ($categoryId !== null) {
        $responseData['filters'] = [
            'category_id' => $categoryId
        ];
    }
    
    // Send success response
    Response::success(
        'Products retrieved successfully.',
        $responseData
    );
    
} catch (PDOException $e) {
    Response::error(
        'Database error while fetching products.',
        ['error' => $e->getMessage()],
        500
    );
}

// End of list.php
