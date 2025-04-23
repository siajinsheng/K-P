<?php
require_once '../../_base.php';
// Set page variables
$page_title = "Products";
$gender = req('gender', 'Man'); // Default to Man if no gender specified

// Get category filter if any
$category_id = req('category', '');

// Get sorting parameter (if any)
$sort = req('sort', ''); // Default to no specific sorting

// Build query to get products based on filters
$sql = "SELECT p.product_id, p.product_name, p.product_price, p.product_pic1, 
               c.category_name, c.category_id 
        FROM product p 
        JOIN category c ON p.category_id = c.category_id
        WHERE p.product_status = 'Available'";

// Apply filters for gender and category
$params = [];
$conditions = [];

// Add gender condition
$conditions[] = "p.product_type = ?";
$params[] = $gender;

// Add category condition if specified
if ($category_id) {
    $conditions[] = "p.category_id = ?";
    $params[] = $category_id;
}

// Add conditions to SQL query
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Apply sorting
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY p.product_price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.product_price DESC";
        break;
    default:
        // Default sorting (e.g., by product name or ID)
        $sql .= " ORDER BY p.product_name ASC";
        break;
}

// Prepare and execute query
$stm = $_db->prepare($sql);
$stm->execute($params);
$products = $stm->fetchAll();

// Get categories for filter menu
$cat_stm = $_db->prepare("SELECT * FROM category");
$cat_stm->execute();
$categories = $cat_stm->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - <?= $page_title ?></title>
    <link rel="stylesheet" href="../css/products.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="product-hero">
        <div class="hero-content">
            <h1>Our Collection</h1>
            <p>Quality fashion for every style</p>
        </div>
    </div>

    <div class="container">
        <div class="product-filters">
            <div class="gender-filter">
                <h3>Gender</h3>
                <div class="gender-options">
                    <label class="gender-option <?= $gender === 'Man' ? 'active' : '' ?>">
                        <input type="radio" name="gender" value="Man" <?= $gender === 'Man' ? 'checked' : '' ?>>
                        <span>Men</span>
                    </label>
                    <label class="gender-option <?= $gender === 'Women' ? 'active' : '' ?>">
                        <input type="radio" name="gender" value="Women" <?= $gender === 'Women' ? 'checked' : '' ?>>
                        <span>Women</span>
                    </label>
                </div>
            </div>
            
            <div class="category-filter">
                <h3>Categories</h3>
                <ul>
                    <li><a href="?gender=<?= $gender ?>&sort=<?= $sort ?>" class="<?= $category_id === '' ? 'active' : '' ?>">All Products</a></li>
                    <?php foreach ($categories as $category): ?>
                    <li>
                        <a href="?gender=<?= $gender ?>&category=<?= $category->category_id ?>&sort=<?= $sort ?>" 
                           class="<?= $category_id === $category->category_id ? 'active' : '' ?>">
                            <?= $category->category_name ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="sort-filter">
                <h3>Sort By</h3>
                <div class="sort-options">
                    <select id="sort-select" class="sort-select">
                        <option value="">Default</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="product-grid">
            <?php if (count($products) > 0): ?>
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="../../img/<?= $product->product_pic1 ?>" alt="<?= $product->product_name ?>">
                            <div class="product-overlay">
                                <a href="product-details.php?id=<?= $product->product_id ?>" class="view-details">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name"><?= $product->product_name ?></h3>
                            <p class="product-price">RM <?= number_format($product->product_price, 2) ?></p>
                            <button class="add-to-cart" data-product="<?= $product->product_id ?>">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-products">
                    <p>No products found in this category.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>

    <script src="../js/products.js"></script>
</body>
</html>