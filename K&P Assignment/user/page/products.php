<?php
require_once '../../_base.php';
// Set page variables
$page_title = "Products";
$gender = req('gender', 'Man'); // Default to Man if no gender specified

// Get category filter if any
$category_id = req('category', '');

// Build query to get products based on filters
$sql = "SELECT p.product_id, p.product_name, p.product_price, p.product_pic1, 
               c.category_name, c.category_id 
        FROM product p 
        JOIN category c ON p.category_id = c.category_id
        WHERE p.product_status = 'Available'";

// Apply filters
if ($category_id) {
    $stm = $_db->prepare($sql . " AND p.category_id = ? AND p.product_type = ?");
    $stm->execute([$category_id, $gender]);
} else {
    $stm = $_db->prepare($sql . " AND p.product_type = ?");
    $stm->execute([$gender]);
}

// Get the products
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
                    <li><a href="?gender=<?= $gender ?>" class="<?= $category_id === '' ? 'active' : '' ?>">All Products</a></li>
                    <?php foreach ($categories as $category): ?>
                    <li>
                        <a href="?gender=<?= $gender ?>&category=<?= $category->category_id ?>" 
                           class="<?= $category_id === $category->category_id ? 'active' : '' ?>">
                            <?= $category->category_name ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
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