<?php
declare(strict_types=1);
/**
 * Category Products Endpoint
 * 
 * Returns all products in a specific category, filtered by category slug.
 * This endpoint demonstrates:
 * - Slug-based filtering (human-readable URLs)
 * - Joining tables on non-primary key columns
 * - Optional pagination for category pages
 * 
 * What is a SLUG?
 * A slug is a URL-friendly version of a name, typically used in URLs:
 * - Category name: "Ordinateurs & Laptops"
 * - Category slug: "ordinateurs-laptops"
 * 
 * Slugs are better for URLs because:
 * 1. No spaces or special characters
 * 2. SEO-friendly
 * 3. Human-readable (not just ID numbers)
 * 4. Can be updated without breaking links (if you store both)
 * 
 * URL Parameters:
 * - slug: Category slug (required, e.g., "ordinateurs", "accessoires")
 * - page: Page number for pagination (optional, default 1)
 * - limit: Items per page (optional, default 20)
 * 
 * Example URLs:
 * - /products/category.php?slug=ordinateurs
 * - /products/category.php?slug=accessoires&page=2
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
// INCLUDE REQUIRED FILES
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

// Get category slug from URL (e.g., ?slug=ordinateurs)
if (!isset($_GET['slug']) || empty(trim($_GET['slug']))) {
    Response::error(
        'Category slug is required.',
        ['slug' => 'Please provide a category slug (e.g., ?slug=ordinateurs)'],
        400
    );
}

$categorySlug = trim($_GET['slug']);

// Validate slug format (alphanumeric and hyphens only)
// This prevents SQL injection attempts and ensures clean URLs
if (!preg_match('/^[a-z0-9-]+$/i', $categorySlug)) {
    Response::error(
        'Invalid category slug format.',
        ['slug' => 'Slug must contain only letters, numbers, and hyphens.'],
        400
    );
}

// Get pagination parameters
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

// Validate pagination
if ($page < 1) {
    $page = 1;
}

if ($limit < 1) {
    $limit = 20;
} elseif ($limit > 100) {
    $limit = 100;
}

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
// VERIFY CATEGORY EXISTS
// ============================================

/**
 * TWO-STEP APPROACH:
 * 
 * 1. First, check if the category exists and is active
 * 2. Then, fetch products in that category
 * 
 * Why two queries?
 * - We can return a specific error if category doesn't exist
 * - We can show category info (name, description) in the response
 * - Better user experience than "0 products found"
 */

$categorySql = 'SELECT id, name, slug, description 
                FROM categories 
                WHERE slug = :slug AND is_active = 1 
                LIMIT 1';

try {
    $categoryStmt = $pdo->prepare($categorySql);
    $categoryStmt->bindValue(':slug', $categorySlug, PDO::PARAM_STR);
    $categoryStmt->execute();
    
    $category = $categoryStmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if category exists
    if (!$category) {
        Response::error(
            'Category not found.',
            ['slug' => "No active category found with slug '$categorySlug'"],
            404
        );
    }
    
    $categoryId = (int) $category['id'];
    
} catch (PDOException $e) {
    Response::error(
        'Database error while fetching category.',
        ['error' => $e->getMessage()],
        500
    );
}

// ============================================
// GET TOTAL PRODUCT COUNT IN CATEGORY
// ============================================

$countSql = 'SELECT COUNT(*) as total 
             FROM products 
             WHERE category_id = :category_id';

try {
    $countStmt = $pdo->prepare($countSql);
    $countStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    $countStmt->execute();
    
    $totalProducts = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = (int) ceil($totalProducts / $limit);
    
} catch (PDOException $e) {
    Response::error(
        'Database error while counting products.',
        ['error' => $e->getMessage()],
        500
    );
}

// ============================================
// GET PRODUCTS IN CATEGORY
// ============================================

/**
 * SLUG-BASED JOIN EXPLANATION:
 * 
 * Instead of joining on primary key (categories.id), we can join on the slug:
 * 
 * JOIN categories ON products.category_id = categories.id
 * WHERE categories.slug = 'ordinateurs'
 * 
 * This approach:
 * 1. Finds the category by slug
 * 2. Gets all products with matching category_id
 * 3. Returns products with category information
 * 
 * Alternative approach (what we're using):
 * 1. First query: Get category ID by slug
 * 2. Second query: Get products by category ID
 * 
 * This is more efficient because we can reuse the category ID
 * and return category info separately.
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
            products.created_at
        FROM products
        WHERE products.category_id = :category_id
        ORDER BY products.created_at DESC
        LIMIT :limit OFFSET :offset';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // FORMAT PRODUCTS
    // ============================================
    
    $formattedProducts = [];
    
    foreach ($products as $product) {
        $formattedProducts[] = [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => number_format((float) $product['price'], 2, '.', ''),
            'image_url' => $product['image_url'],
            'stock_quantity' => (int) $product['stock_quantity'],
            'in_stock' => (int) $product['stock_quantity'] > 0,
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
    // BUILD RESPONSE
    // ============================================
    
    $responseData = [
        'category' => [
            'id' => (int) $category['id'],
            'name' => $category['name'],
            'slug' => $category['slug'],
            'description' => $category['description']
        ],
        'products' => $formattedProducts,
        'pagination' => [
            'total' => $totalProducts,
            'count' => count($formattedProducts),
            'per_page' => $limit,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
    
    $message = sprintf(
        'Found %d product(s) in category "%s"',
        $totalProducts,
        $category['name']
    );
    
    Response::success($message, $responseData);
    
} catch (PDOException $e) {
    Response::error(
        'Database error while fetching products.',
        ['error' => $e->getMessage()],
        500
    );
}

// End of category.php
