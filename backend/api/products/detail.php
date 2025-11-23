<?php
declare(strict_types=1);
/**
 * Product Detail Endpoint
 * 
 * Returns detailed information about a single product including:
 * - All product fields (name, price, description, etc.)
 * - Category information (joined from categories table)
 * - Product images (joined from product_images table)
 * 
 * This endpoint demonstrates:
 * - Multiple table JOINs (products → categories → product_images)
 * - One-to-many relationships (one product can have many images)
 * - Grouping related data (images array inside product object)
 * 
 * URL Parameters:
 * - id: Product ID (required)
 * 
 * Example URL:
 * - /products/detail.php?id=5
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

// Get product ID from URL (e.g., ?id=5)
if (!isset($_GET['id']) || empty($_GET['id'])) {
    Response::error(
        'Product ID is required.',
        ['id' => 'Please provide a product ID in the URL (e.g., ?id=5)'],
        400
    );
}

$productId = (int) $_GET['id'];

// Validate ID is a positive number
if ($productId <= 0) {
    Response::error('Invalid product ID.', ['id' => 'Product ID must be a positive number.'], 400);
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
// GET PRODUCT DETAILS
// ============================================

/**
 * JOIN EXPLANATION FOR THIS QUERY:
 * 
 * We're joining two tables:
 * 1. products → categories: To get the category name
 * 
 * The JOIN connects rows where products.category_id matches categories.id
 * 
 * Visual example:
 * 
 * products table:
 * | id | name      | category_id |
 * | 5  | Laptop HP | 1           |
 * 
 * categories table:
 * | id | name        |
 * | 1  | Ordinateurs |
 * 
 * After JOIN:
 * | products.id | products.name | categories.name |
 * | 5           | Laptop HP     | Ordinateurs     |
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
            products.updated_at,
            categories.name AS category_name,
            categories.slug AS category_slug
        FROM products
        LEFT JOIN categories ON products.category_id = categories.id
        WHERE products.id = :id
        LIMIT 1';

try {
    // Prepare and execute query for product details
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $productId, PDO::PARAM_INT);
    $stmt->execute();
    
    // Fetch the product
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if product exists
    if (!$product) {
        Response::error(
            'Product not found.',
            ['id' => "No product found with ID $productId"],
            404 // 404 Not Found
        );
    }
    
} catch (PDOException $e) {
    Response::error(
        'Database error while fetching product.',
        ['error' => $e->getMessage()],
        500
    );
}

// ============================================
// GET PRODUCT IMAGES
// ============================================

/**
 * ONE-TO-MANY RELATIONSHIP EXPLANATION:
 * 
 * A product can have multiple images. This is called a one-to-many relationship:
 * - One product (id=5)
 * - Many images (image1.jpg, image2.jpg, image3.jpg)
 * 
 * We store images in a separate table (product_images) with a foreign key
 * pointing back to the product:
 * 
 * product_images table:
 * | id | product_id | image_url      | is_primary |
 * | 1  | 5          | laptop-1.jpg   | 1          |
 * | 2  | 5          | laptop-2.jpg   | 0          |
 * | 3  | 5          | laptop-3.jpg   | 0          |
 * 
 * We'll fetch all images for this product and include them in the response.
 */

$imagesSql = 'SELECT 
                id,
                image_url,
                is_primary,
                display_order
              FROM product_images
              WHERE product_id = :product_id
              ORDER BY is_primary DESC, display_order ASC';

$images = [];

try {
    $imagesStmt = $pdo->prepare($imagesSql);
    $imagesStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
    $imagesStmt->execute();
    
    $imagesResult = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format images array
    foreach ($imagesResult as $img) {
        $images[] = [
            'id' => (int) $img['id'],
            'url' => $img['image_url'],
            'is_primary' => (bool) $img['is_primary'],
            'display_order' => (int) $img['display_order']
        ];
    }
    
} catch (PDOException $e) {
    // If fetching images fails, continue with empty array
    // This is not critical - product can still be displayed without gallery
    error_log('Failed to fetch product images: ' . $e->getMessage());
}

// ============================================
// FORMAT PRODUCT DATA
// ============================================

/**
 * DATA FORMATTING BEST PRACTICES:
 * 
 * 1. Type casting: Convert strings to proper types (int, float, bool)
 * 2. Number formatting: Format prices to 2 decimal places
 * 3. Null handling: Use null for missing data (not empty strings)
 * 4. Nested objects: Group related data (category object, images array)
 * 5. Calculated fields: Add convenience fields (in_stock, discount_percent)
 */

$formattedProduct = [
    'id' => (int) $product['id'],
    'name' => $product['name'],
    'description' => $product['description'],
    'price' => number_format((float) $product['price'], 2, '.', ''),
    'price_numeric' => (float) $product['price'], // For calculations
    'category' => [
        'id' => (int) $product['category_id'],
        'name' => $product['category_name'],
        'slug' => $product['category_slug']
    ],
    'main_image' => $product['image_url'], // Primary product image
    'images' => $images, // Array of all product images
    'stock' => [
        'quantity' => (int) $product['stock_quantity'],
        'in_stock' => (int) $product['stock_quantity'] > 0,
        'status' => (int) $product['stock_quantity'] > 0 ? 'available' : 'out_of_stock'
    ],
    'flags' => [
        'is_featured' => (bool) $product['is_featured'],
        'is_bestseller' => (bool) $product['is_bestseller'],
        'is_new' => (bool) $product['is_new']
    ],
    'brand' => $product['brand'],
    'rating' => [
        'average' => $product['rating'] ? (float) $product['rating'] : null,
        'count' => (int) $product['review_count']
    ],
    'timestamps' => [
        'created_at' => $product['created_at'],
        'updated_at' => $product['updated_at']
    ]
];

// ============================================
// SEND SUCCESS RESPONSE
// ============================================

Response::success(
    'Product details retrieved successfully.',
    ['product' => $formattedProduct]
);

// End of detail.php
