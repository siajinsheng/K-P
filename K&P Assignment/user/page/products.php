<?php
require_once '../../_base.php';

// Start session
safe_session_start();

// Get category filter if exists
$category_filter = isset($_GET['category']) ? $_GET['category'] : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get sorting option
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Prepare the SQL query for products
try {
    $params = [];
    
    // Simplified SQL query
    $sql = "SELECT * FROM product WHERE product_status = 'Available'";
    
    // Add category filter if selected
    if ($category_filter) {
        $sql .= " AND product_category = ?";
        $params[] = $category_filter;
    }
    
    // Add search query if provided
    if (!empty($search_query)) {
        $sql .= " AND (product_name LIKE ? OR product_description LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }
    
    // Add sorting
    switch($sort_by) {
        case 'price_low':
            $sql .= " ORDER BY product_price ASC";
            break;
        case 'price_high':
            $sql .= " ORDER BY product_price DESC";
            break;
        case 'newest':
        default:
            $sql .= " ORDER BY created_at DESC";
            break;
    }
    
    $stm = $_db->prepare($sql);
    $stm->execute($params);
    $products = $stm->fetchAll();
    
    // Log the result
    error_log("Products query returned " . count($products) . " results");
    
    // Get all categories for the filter
    $stm = $_db->prepare("SELECT DISTINCT product_category FROM product WHERE product_status = 'Available'");
    $stm->execute();
    $categories = $stm->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
    $products = [];
    $categories = [];
    temp('error', 'An error occurred while retrieving products. Please try again.');
}

// Get any messages from session
$success_message = temp('success');
$error_message = temp('error');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - Products</title>
    <link rel="stylesheet" href="../css/products.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <div class="search-bar-container">
            <form method="get" action="products.php" class="search-form">
                <div class="search-input-container">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="Search products..." 
                        value="<?= htmlspecialchars($search_query) ?>"
                        class="search-input"
                    >
                    <button type="submit" class="search-button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                
                <?php if (!empty($search_query)): ?>
                    <div class="search-results-info">
                        Showing results for: <strong>"<?= htmlspecialchars($search_query) ?>"</strong>
                        <a href="products.php" class="clear-search">Clear</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success_message ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
            </div>
        <?php endif; ?>

        <div class="products-container">
            <div class="filters-container">
                <h2>Filters</h2>
                
                <div class="filter-section">
                    <h3>Categories</h3>
                    <ul class="category-filter">
                        <li class="<?= !$category_filter ? 'active' : '' ?>">
                            <a href="products.php<?= $search_query ? "?search=".urlencode($search_query) : "" ?>">All Categories</a>
                        </li>
                        <?php foreach($categories as $category): ?>
                            <li class="<?= $category_filter == $category ? 'active' : '' ?>">
                                <a href="products.php?category=<?= urlencode($category) ?><?= $search_query ? "&search=".urlencode($search_query) : "" ?>">
                                    <?= htmlspecialchars($category) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="filter-section">
                    <h3>Sort By</h3>
                    <ul class="sort-options">
                        <li class="<?= $sort_by == 'newest' ? 'active' : '' ?>">
                            <a href="products.php?sort=newest<?= $category_filter ? "&category=".urlencode($category_filter) : "" ?><?= $search_query ? "&search=".urlencode($search_query) : "" ?>">Newest</a>
                        </li>
                        <li class="<?= $sort_by == 'price_low' ? 'active' : '' ?>">
                            <a href="products.php?sort=price_low<?= $category_filter ? "&category=".urlencode($category_filter) : "" ?><?= $search_query ? "&search=".urlencode($search_query) : "" ?>">Price: Low to High</a>
                        </li>
                        <li class="<?= $sort_by == 'price_high' ? 'active' : '' ?>">
                            <a href="products.php?sort=price_high<?= $category_filter ? "&category=".urlencode($category_filter) : "" ?><?= $search_query ? "&search=".urlencode($search_query) : "" ?>">Price: High to Low</a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="products-grid">
                <?php if (empty($products)): ?>
                    <div class="no-products-found">
                        <i class="fas fa-search"></i>
                        <h3>No products found</h3>
                        <?php if ($search_query || $category_filter): ?>
                            <p>Try adjusting your search or filter criteria</p>
                            <a href="products.php" class="reset-filters-btn">Reset All Filters</a>
                        <?php else: ?>
                            <p>We'll be adding new products soon!</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach($products as $product): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <a href="product-details.php?id=<?= $product->product_id ?>">
                                    <img src="../../img/<?= $product->product_pic1 ?>" alt="<?= htmlspecialchars($product->product_name) ?>">
                                </a>
                            </div>
                            <div class="product-info">
                                <h3 class="product-name">
                                    <a href="product-details.php?id=<?= $product->product_id ?>"><?= htmlspecialchars($product->product_name) ?></a>
                                </h3>
                                <div class="product-category"><?= htmlspecialchars($product->product_category) ?></div>
                                <div class="product-price">RM <?= number_format($product->product_price, 2) ?></div>
                                <a href="product-details.php?id=<?= $product->product_id ?>" class="view-product-btn">View Product</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Mobile filters toggle
        const filterToggle = document.querySelector('.filter-toggle');
        if (filterToggle) {
            filterToggle.addEventListener('click', function() {
                const filtersContainer = document.querySelector('.filters-container');
                filtersContainer.classList.toggle('active');
                
                // Change icon and text
                if (filtersContainer.classList.contains('active')) {
                    this.innerHTML = '<i class="fas fa-times"></i> Close Filters';
                } else {
                    this.innerHTML = '<i class="fas fa-filter"></i> Show Filters';
                }
            });
        }
    });
    </script>
</body>
</html>