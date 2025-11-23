<?php
declare(strict_types=1);
/**
 * Product Search Endpoint
 * 
 * Searches for products by name or description using SQL LIKE operator.
 * This endpoint demonstrates:
 * - Full-text search using LIKE with wildcards
 * - Searching across multiple columns (name AND description)
 * - SQL OR operator to match either column
 * - Prepared statements with wildcards for security
 * 
 * URL Parameters:
 * - q: Search query (required, minimum 2 characters)
 * - limit: Maximum results to return (optional, default 50)
 * 
 * Example URLs:
 * - /products/search.php?q=laptop
 * - /products/search.php?q=gaming&limit=20
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

// Get search query from URL (e.g., ?q=laptop)
if (!isset($_GET['q']) || empty(trim($_GET['q']))) {
    Response::error(
        'Search query is required.',
        ['q' => 'Please provide a search term (e.g., ?q=laptop)'],
        400
    );
}

// Clean and prepare search query
$searchQuery = trim($_GET['q']);

// Validate minimum search length (prevents searching for single characters)
if (strlen($searchQuery) < 2) {
    Response::error(
        'Search query too short.',
        ['q' => 'Please enter at least 2 characters to search.'],
        400
    );
}

// Get optional limit parameter (default 50, max 100)
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;

if ($limit < 1) {
    $limit = 50;
} elseif ($limit > 100) {
    $limit = 100;
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
// PREPARE SEARCH QUERY
// ============================================

/**
 * SQL LIKE OPERATOR EXPLANATION:
 * 
 * LIKE is used for pattern matching in SQL. It supports wildcards:
 * 
 * Wildcards:
 * - % : Matches any sequence of characters (including zero characters)
 * - _ : Matches exactly one character
 * 
 * Examples:
 * - 'laptop%'    → Matches: "laptop", "laptop HP", "laptop gaming"
 * - '%laptop'    → Matches: "gaming laptop", "new laptop"
 * - '%laptop%'   → Matches: "gaming laptop HP", "buy laptop online"
 * - 'lap_op'     → Matches: "laptop" (one character between 'p' and 'o')
 * 
 * For search functionality, we typically use '%search%' to find the term
 * anywhere in the text.
 * 
 * SECURITY NOTE:
 * We use prepared statements with placeholders (:search) to prevent SQL injection.
 * Even though we're adding wildcards, the user input is still safely escaped.
 */

// Add wildcards to search query
// If user searches "laptop", we search for "%laptop%"
$searchPattern = '%' . $searchQuery . '%';

/**
 * SEARCHING MULTIPLE COLUMNS:
 * 
 * We use OR to search in both name AND description columns:
 * 
 * WHERE name LIKE '%laptop%' OR description LIKE '%laptop%'
 * 
 * This matches products where EITHER:
 * - The name contains "laptop", OR
 * - The description contains "laptop", OR
 * - Both contain "laptop"
 * 
 * Example results for search "gaming":
 * ✓ Product 1: name="Gaming Laptop" (matched in name)
 * ✓ Product 2: name="Dell XPS", description="Perfect for gaming" (matched in description)
 * ✗ Product 3: name="Office Mouse" (no match)
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
            categories.name AS category_name,
            categories.slug AS category_slug
        FROM products
        LEFT JOIN categories ON products.category_id = categories.id
        WHERE (products.name LIKE :search OR products.description LIKE :search)
        ORDER BY 
            CASE 
                WHEN products.name LIKE :search_exact THEN 1
                WHEN products.name LIKE :search THEN 2
                ELSE 3
            END,
            products.created_at DESC
        LIMIT :limit';

/**
 * ORDERING SEARCH RESULTS:
 * 
 * The ORDER BY uses a CASE statement to prioritize results:
 * 1. Exact matches in name (name = "laptop")
 * 2. Partial matches in name (name contains "laptop")
 * 3. Matches only in description
 * 
 * Then within each group, sort by newest first (created_at DESC)
 */

// ============================================
// EXECUTE SEARCH QUERY
// ============================================
try {
    $stmt = $pdo->prepare($sql);
    
    // Bind search parameter (used multiple times in query)
    // When a placeholder appears multiple times, PDO uses the same value for all
    $stmt->bindValue(':search', $searchPattern, PDO::PARAM_STR);
    $stmt->bindValue(':search_exact', $searchQuery, PDO::PARAM_STR); // For exact match check
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    
    $stmt->execute();
    
    // Fetch all matching products
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // FORMAT RESULTS
    // ============================================
    
    $formattedProducts = [];
    
    foreach ($products as $product) {
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
            'is_featured' => (bool) $product['is_featured'],
            'is_bestseller' => (bool) $product['is_bestseller'],
            'is_new' => (bool) $product['is_new'],
            'brand' => $product['brand'],
            'rating' => $product['rating'] ? (float) $product['rating'] : null,
            'review_count' => (int) $product['review_count']
        ];
    }
    
    // ============================================
    // SEND RESPONSE
    // ============================================
    
    /**
     * EMPTY RESULTS HANDLING:
     * 
     * If no products match the search, we return an empty array (not an error).
     * This is the correct behavior because:
     * - The search worked correctly
     * - There just aren't any matching results
     * - The frontend can show "No results found" message
     * 
     * We only return errors for actual problems (missing parameters, database errors, etc.)
     */
    
    $message = count($formattedProducts) > 0
        ? sprintf('Found %d product(s) matching "%s"', count($formattedProducts), $searchQuery)
        : sprintf('No products found matching "%s"', $searchQuery);
    
    Response::success(
        $message,
        [
            'query' => $searchQuery,
            'count' => count($formattedProducts),
            'products' => $formattedProducts
        ]
    );
    
} catch (PDOException $e) {
    Response::error(
        'Database error while searching products.',
        ['error' => $e->getMessage()],
        500
    );
}

// End of search.php
